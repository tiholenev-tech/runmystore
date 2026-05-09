#!/usr/bin/env python3
"""
tools/stress/alerts/telegram_bot.py

Phase M1 (S130 extension). Централизиран Telegram alerter.

Resolves OQ-01: STRESS_HANDOFF_20260508.md OQ-01 искаше единен helper
за Telegram нотификации с 3 severity levels и rate limiting.

Усеща се ENV/файл по този приоритет:
  1. /etc/runmystore/telegram.env (TELEGRAM_BOT_TOKEN + TELEGRAM_CHAT_ID
     или TIHOL_CHAT_ID)
  2. Същите ENV променливи (за crontab usage)

Severity levels:
  - info     — без префикс, тихо
  - warning  — ⚠️ префикс
  - critical — 🚨 префикс, винаги изпратено (rate limiter го прескача)

Rate limiting: state-file `tools/stress/data/telegram_state.json`
съдържа последно-изпратен timestamp per topic. По default 60 секунди
между две `info`/`warning` за същия topic. `critical` винаги излиза.

Usage CLI:
    python3 telegram_bot.py --severity warning --topic balance \
        --message "5 balance failures last 24h"
    python3 telegram_bot.py --send-test
    python3 telegram_bot.py --dry-run --severity info --message "hello"

Usage Python:
    from telegram_bot import send_alert
    send_alert("warning", "Balance check failed", topic="balance")
"""

import argparse
import json
import os
import sys
import time
import urllib.parse
import urllib.request
from datetime import datetime
from pathlib import Path

ENV_PATH = "/etc/runmystore/telegram.env"
STATE_FILE = (Path(__file__).resolve().parent.parent / "data" /
              "telegram_state.json")
DEFAULT_RATE_LIMIT_SECONDS = 60
TG_API_BASE = "https://api.telegram.org"

SEVERITY_PREFIX = {
    "info":     "",
    "warning":  "⚠️ ",
    "critical": "🚨 ",
}
SEVERITY_LEVELS = ("info", "warning", "critical")


def load_telegram_config(env_path: str = ENV_PATH) -> dict:
    """Чете /etc/runmystore/telegram.env. Fallback към ENV vars."""
    cfg: dict[str, str] = {}
    if os.path.exists(env_path) and os.access(env_path, os.R_OK):
        with open(env_path) as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                k, _, v = line.partition("=")
                cfg[k.strip()] = v.strip().strip("'\"")
    # ENV override / fallback
    for k in ("TELEGRAM_BOT_TOKEN", "TELEGRAM_CHAT_ID", "TIHOL_CHAT_ID"):
        if os.environ.get(k):
            cfg.setdefault(k, os.environ[k])
    # TIHOL_CHAT_ID ↔ TELEGRAM_CHAT_ID alias
    if "TELEGRAM_CHAT_ID" not in cfg and "TIHOL_CHAT_ID" in cfg:
        cfg["TELEGRAM_CHAT_ID"] = cfg["TIHOL_CHAT_ID"]
    return cfg


def _load_state() -> dict:
    if not STATE_FILE.exists():
        return {}
    try:
        with open(STATE_FILE) as f:
            return json.load(f)
    except Exception:
        return {}


def _save_state(state: dict) -> None:
    STATE_FILE.parent.mkdir(parents=True, exist_ok=True)
    tmp = STATE_FILE.with_suffix(".json.tmp")
    tmp.write_text(json.dumps(state, ensure_ascii=False, indent=2))
    tmp.replace(STATE_FILE)


def _rate_limit_ok(topic: str, severity: str,
                   limit_seconds: int) -> tuple[bool, int]:
    """Връща (ok, seconds_until_next). critical винаги ok."""
    if severity == "critical":
        return True, 0
    state = _load_state()
    now = int(time.time())
    last = int(state.get(topic, {}).get("last_sent", 0))
    if last == 0:
        return True, 0
    elapsed = now - last
    if elapsed >= limit_seconds:
        return True, 0
    return False, limit_seconds - elapsed


def _record_send(topic: str, severity: str, message: str) -> None:
    state = _load_state()
    state.setdefault(topic, {})
    state[topic]["last_sent"] = int(time.time())
    state[topic]["last_severity"] = severity
    state[topic]["last_message"] = message[:200]
    state.setdefault("_history", [])
    state["_history"] = (state["_history"] + [{
        "ts": datetime.now().isoformat(timespec="seconds"),
        "topic": topic, "severity": severity,
        "msg": message[:200],
    }])[-200:]  # keep last 200 entries
    _save_state(state)


def format_message(severity: str, message: str, context: dict | None) -> str:
    prefix = SEVERITY_PREFIX.get(severity, "")
    parts = [f"{prefix}STRESS [{severity.upper()}] {message}".strip()]
    if context:
        ctx_lines = [f"  • {k}: {v}" for k, v in context.items()]
        parts.append("\n".join(ctx_lines))
    parts.append(f"  ⏱ {datetime.now().isoformat(timespec='seconds')}")
    return "\n".join(parts)


def _http_post(url: str, data: dict, timeout: int = 5) -> tuple[int, str]:
    body = urllib.parse.urlencode(data).encode()
    req = urllib.request.Request(url, data=body, method="POST")
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            return resp.status, resp.read().decode("utf-8", "replace")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace")
    except Exception as e:
        return 0, f"{type(e).__name__}: {e}"


def send_alert(severity: str, message: str, *,
               topic: str = "default",
               context: dict | None = None,
               rate_limit_seconds: int = DEFAULT_RATE_LIMIT_SECONDS,
               dry_run: bool = False,
               cfg: dict | None = None) -> dict:
    """
    Изпраща Telegram алерт.

    Връща dict с keys: sent (bool), reason (str), severity, topic.
    Никога не throws — failure-ите се връщат в return value за да не
    спират извикващия cron.
    """
    if severity not in SEVERITY_LEVELS:
        return {"sent": False, "reason": f"invalid severity: {severity}",
                "severity": severity, "topic": topic}

    if cfg is None:
        cfg = load_telegram_config()
    token = cfg.get("TELEGRAM_BOT_TOKEN")
    chat_id = cfg.get("TELEGRAM_CHAT_ID")

    text = format_message(severity, message, context)

    if dry_run:
        print(f"[DRY-RUN][telegram] severity={severity} topic={topic}")
        print(text)
        return {"sent": False, "reason": "dry-run", "text": text,
                "severity": severity, "topic": topic}

    if not token or not chat_id:
        return {"sent": False,
                "reason": "missing TELEGRAM_BOT_TOKEN or TELEGRAM_CHAT_ID",
                "severity": severity, "topic": topic}

    ok, wait = _rate_limit_ok(topic, severity, rate_limit_seconds)
    if not ok:
        return {"sent": False,
                "reason": f"rate-limited ({wait}s remaining)",
                "severity": severity, "topic": topic}

    url = f"{TG_API_BASE}/bot{token}/sendMessage"
    status, body = _http_post(url, {
        "chat_id": chat_id,
        "text": text,
        "disable_web_page_preview": "true",
    })
    if status == 200:
        _record_send(topic, severity, message)
        return {"sent": True, "reason": "ok", "status": status,
                "severity": severity, "topic": topic}
    return {"sent": False, "reason": f"HTTP {status}: {body[:200]}",
            "severity": severity, "topic": topic}


def main():
    ap = argparse.ArgumentParser(description="STRESS Telegram alerter")
    ap.add_argument("--severity", choices=list(SEVERITY_LEVELS),
                    default="info")
    ap.add_argument("--topic", default="default",
                    help="Тема за rate limiting (напр. 'balance', 'cron').")
    ap.add_argument("--message", default=None)
    ap.add_argument("--context-json", default=None,
                    help="Допълнителен context в JSON формат.")
    ap.add_argument("--rate-limit", type=int,
                    default=DEFAULT_RATE_LIMIT_SECONDS,
                    help="Секунди между две info/warning за същия topic.")
    ap.add_argument("--dry-run", action="store_true",
                    help="Само принтира — не изпраща.")
    ap.add_argument("--send-test", action="store_true",
                    help="Изпраща тестов info alert и излиза.")
    args = ap.parse_args()

    if args.send_test:
        result = send_alert("info",
                            "Test alert — STRESS Telegram bot OK",
                            topic="self_test",
                            dry_run=args.dry_run)
        print(json.dumps(result, ensure_ascii=False, indent=2))
        return 0 if result["sent"] or args.dry_run else 1

    if not args.message:
        print("ERROR: --message е задължителен (или ползвай --send-test).",
              file=sys.stderr)
        return 2

    ctx = None
    if args.context_json:
        try:
            ctx = json.loads(args.context_json)
        except json.JSONDecodeError as e:
            print(f"ERROR: invalid --context-json: {e}", file=sys.stderr)
            return 2

    result = send_alert(args.severity, args.message,
                        topic=args.topic, context=ctx,
                        rate_limit_seconds=args.rate_limit,
                        dry_run=args.dry_run)
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0 if result["sent"] or args.dry_run else 1


if __name__ == "__main__":
    sys.exit(main())
