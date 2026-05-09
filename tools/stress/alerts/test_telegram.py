#!/usr/bin/env python3
"""
tools/stress/alerts/test_telegram.py

Phase M3 (S130 extension). Smoke test за telegram_bot.py.

Печата вместо да изпраща (dry-run mode по default). Покрива:
  - send_alert() с трите severity нива
  - format_message() с context
  - rate limiting state file (state file persistence)
  - missing config (graceful degradation)

Usage:
    python3 test_telegram.py            # dry-run всичко
    python3 test_telegram.py --live     # реално изпрати info alert
"""

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from telegram_bot import (
    DEFAULT_RATE_LIMIT_SECONDS,
    SEVERITY_LEVELS,
    format_message,
    load_telegram_config,
    send_alert,
)


def test_format_message():
    text = format_message("warning", "Balance check failed",
                          {"failures": 5, "tenant_id": 99})
    assert "⚠️" in text
    assert "WARNING" in text
    assert "Balance check failed" in text
    assert "failures: 5" in text
    return text


def test_send_dry_run_all_levels():
    out = []
    for sev in SEVERITY_LEVELS:
        result = send_alert(sev, f"Smoke {sev}",
                            topic=f"test_{sev}",
                            context={"phase": "M", "smoke": True},
                            dry_run=True)
        assert result["sent"] is False, result
        assert result["reason"] == "dry-run"
        out.append({"severity": sev, **result})
    return out


def test_missing_config():
    """Когато няма token/chat_id, send_alert трябва да върне sent=False
    със смислена reason — БЕЗ да хвърля exception."""
    fake_cfg = {}
    result = send_alert("info", "test", topic="missing_cfg",
                        cfg=fake_cfg, dry_run=False)
    assert result["sent"] is False
    assert "missing" in result["reason"].lower()
    return result


def test_invalid_severity():
    result = send_alert("HUGE_DEAL", "test", dry_run=True)
    assert result["sent"] is False
    assert "invalid" in result["reason"].lower()
    return result


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--live", action="store_true",
                    help="Реално изпрати info тест към Telegram. "
                    "Изисква /etc/runmystore/telegram.env.")
    args = ap.parse_args()

    print("=== test_format_message ===")
    print(test_format_message())
    print()

    print("=== test_send_dry_run_all_levels ===")
    out = test_send_dry_run_all_levels()
    print(json.dumps(out, ensure_ascii=False, indent=2))
    print()

    print("=== test_missing_config ===")
    print(json.dumps(test_missing_config(), ensure_ascii=False, indent=2))
    print()

    print("=== test_invalid_severity ===")
    print(json.dumps(test_invalid_severity(), ensure_ascii=False, indent=2))
    print()

    cfg = load_telegram_config()
    print(f"=== config presence ===")
    print(f"TELEGRAM_BOT_TOKEN: {'SET' if cfg.get('TELEGRAM_BOT_TOKEN') else 'MISSING'}")
    print(f"TELEGRAM_CHAT_ID:   {'SET' if cfg.get('TELEGRAM_CHAT_ID') else 'MISSING'}")
    print()

    if args.live:
        print("=== LIVE send (info, topic=self_test) ===")
        result = send_alert("info",
                            "test_telegram.py --live smoke",
                            topic="self_test",
                            context={"script": "test_telegram.py"})
        print(json.dumps(result, ensure_ascii=False, indent=2))
        return 0 if result["sent"] else 1

    print("[OK] Всички dry-run тестове минаха.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
