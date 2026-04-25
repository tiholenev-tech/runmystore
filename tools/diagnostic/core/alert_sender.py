"""
alert_sender.py — изпраща CRITICAL alerts чрез Telegram + email.

Триггери:
  - След всеки diagnostic_log INSERT → проверява a/d pass rates
  - Ако a < 100 или d < 100 → send_telegram_critical()
  - Daily 08:30 → send_email_summary() (winaги, не само при критични)

Никаква конфигурация в код — всички credentials идват от /etc/runmystore/db.env:
  NOTIFY_EMAIL          = адрес за дневен summary
  TELEGRAM_BOT_TOKEN    = от @BotFather
  TELEGRAM_CHAT_ID      = от https://api.telegram.org/bot<TOKEN>/getUpdates

Ако някой ENV е празен → log в stderr и продължава (НЕ блокира diagnostic run).
"""

import sys
import json
import subprocess
import urllib.request
import urllib.parse
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))
from core.db_helpers import get_notify_config  # noqa: E402
from core.report_writer import (  # noqa: E402
    telegram_critical, bg_email_body,
)


def send_telegram_critical(log_row: dict) -> tuple:
    """
    Изпраща Telegram съобщение веднага при Cat A/D fail.
    Връща (success: bool, message: str).
    """
    cfg = get_notify_config()
    token = cfg.get('telegram_token', '')
    chat_id = cfg.get('telegram_chat_id', '')

    if not token or not chat_id:
        msg = "Telegram NOT configured (TELEGRAM_BOT_TOKEN/CHAT_ID празни в /etc/runmystore/db.env)"
        print(f"alert_sender: {msg}", file=sys.stderr)
        return False, msg

    text = telegram_critical(log_row)
    url = f"https://api.telegram.org/bot{token}/sendMessage"
    data = urllib.parse.urlencode({
        'chat_id': chat_id,
        'text': text,
        'parse_mode': 'HTML',
        'disable_web_page_preview': '1',
    }).encode('utf-8')

    try:
        req = urllib.request.Request(url, data=data, method='POST')
        with urllib.request.urlopen(req, timeout=10) as r:
            body = r.read().decode('utf-8', errors='ignore')
        try:
            j = json.loads(body)
            if j.get('ok'):
                return True, "sent"
            return False, j.get('description', body[:200])
        except Exception:
            return False, body[:200]
    except Exception as e:
        return False, f"{type(e).__name__}: {e}"


def send_email_summary(log_row: dict, previous: dict | None = None, sales_today: int = 0) -> tuple:
    """
    Изпраща дневен email през системния `mail` command.
    Връща (success, message).
    """
    cfg = get_notify_config()
    to_addr = cfg.get('email', '')
    if not to_addr:
        msg = "NOTIFY_EMAIL не е настроен в /etc/runmystore/db.env"
        print(f"alert_sender: {msg}", file=sys.stderr)
        return False, msg

    body = bg_email_body(log_row, previous=previous, sales_today=sales_today)
    subject = "RunMyStore Diag — дневен отчет"
    if log_row:
        a = log_row.get('category_a_pass_rate')
        d = log_row.get('category_d_pass_rate')
        if a is not None and float(a) < 100 or d is not None and float(d) < 100:
            subject = "🚨 RunMyStore Diag — CRITICAL"

    try:
        proc = subprocess.run(
            ['mail', '-s', subject, to_addr],
            input=body, text=True, capture_output=True, timeout=20
        )
        if proc.returncode == 0:
            return True, "sent"
        return False, f"mail returncode={proc.returncode}: {proc.stderr[:200]}"
    except FileNotFoundError:
        return False, "`mail` command not installed (apt-get install mailutils)"
    except Exception as e:
        return False, f"{type(e).__name__}: {e}"


def maybe_alert_critical(log_row: dict) -> dict:
    """
    Wrapper: ако Cat A или D < 100% → веднага Telegram.
    Никога не блокира diagnostic run при failure на alert.
    """
    a = log_row.get('category_a_pass_rate')
    d = log_row.get('category_d_pass_rate')
    is_critical = (a is not None and float(a) < 100) or (d is not None and float(d) < 100)
    if not is_critical:
        return {'is_critical': False, 'sent': False}

    ok, msg = send_telegram_critical(log_row)
    return {'is_critical': True, 'sent': ok, 'detail': msg}


if __name__ == '__main__':
    # CLI self-test (mock log row)
    mock = {
        'id': 999, 'run_timestamp': '2026-04-25 12:00:00',
        'trigger_type': 'manual', 'module_name': 'insights',
        'category_a_pass_rate': 92.0, 'category_d_pass_rate': 88.0,
        'category_b_pass_rate': 75.0, 'category_c_pass_rate': 65.0,
        'passed': 60, 'failed': 12, 'total_scenarios': 72,
        'failures_json': [
            {'scenario_code': 'zombie_pos_0', 'reason': 'product missing'},
            {'scenario_code': 'high_return_d_boundary', 'reason': 'rate=100% (Cartesian regression?)'},
        ],
    }
    print("Testing alert_sender (mock log row)...")
    print()
    result = maybe_alert_critical(mock)
    print(f"is_critical={result['is_critical']}, sent={result['sent']}")
    if 'detail' in result:
        print(f"detail: {result['detail']}")
    print()
    print("Testing email summary...")
    ok, msg = send_email_summary(mock)
    print(f"email_sent={ok}, msg={msg}")
