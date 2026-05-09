# tools/stress/alerts/

**Phase M (S130 extension) — Централизиран Telegram alerter.**

Resolves **OQ-01** (open question от STRESS_HANDOFF_20260508.md): "Кой е каналът за нощни алерти и как се rate-limit-ват?"

---

## 📂 Файлове

| Файл | Роля |
|---|---|
| `telegram_bot.py` | `send_alert(severity, message, topic, ...)` + CLI. Rate limiter с state file. |
| `test_telegram.py` | Dry-run + integration smoke. Покрива всички severity нива и edge cases. |
| `__init__.py` | Маркер. |
| `README.md` | Този файл. |

---

## 🔧 Setup

### 1. Създай Telegram bot

1. Отвори [@BotFather](https://t.me/BotFather) в Telegram.
2. Изпрати `/newbot`. Дай му име, например `RunMyStore STRESS Bot`.
3. Запази **HTTP API token** (формат `123456789:ABCdefGHIJklm...`).

### 2. Намери chat_id

1. Изпрати първото съобщение към новия бот ('hi').
2. Отвори https://api.telegram.org/bot<TOKEN>/getUpdates в браузър.
3. Намери `"chat":{"id":NNNNNNNN}` — това е твоят chat_id.

### 3. Запази в `/etc/runmystore/telegram.env`

```bash
sudo install -d -m 0755 /etc/runmystore
sudo tee /etc/runmystore/telegram.env >/dev/null <<'EOF'
TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIJklm...
TELEGRAM_CHAT_ID=987654321
# или: TIHOL_CHAT_ID=987654321  (alias)
EOF
sudo chown root:www-data /etc/runmystore/telegram.env
sudo chmod 0640 /etc/runmystore/telegram.env
```

### 4. Тествай

```bash
# Dry-run (без реално изпращане)
python3 tools/stress/alerts/test_telegram.py

# Live (реално изпрати info)
python3 tools/stress/alerts/test_telegram.py --live
```

---

## 🚦 Severity levels

| Severity | Префикс | Rate limit | Описание |
|---|---|---|---|
| `info` | (без) | 60s/topic | Тих, информативен |
| `warning` | `⚠️` | 60s/topic | Грешка но не критична |
| `critical` | `🚨` | **Никакъв** (винаги излиза) | P0 escalation |

`critical` алертите **НИКОГА не са rate-limited**. Това е по дизайн: ако нощният робот падне 3 пъти, искаме всички 3 алерта.

---

## 📡 Интеграция в crons (Phase M2)

Wrapper-ите живеят в `cron_hooks.py` (отделен файл, за да не създава merge конфликти с cron модификации в други branches). Cron файловете НЕ са пипнати на това branch — интеграцията се прилага като 1-2 редов patch при следващия sync.

| Hook | Severity logic | Topic |
|---|---|---|
| `alert_balance(n, period_h, tenant_id, sample)` | 0 → quiet, 1-50 → warning, >50 → critical | `balance` |
| `alert_nightly_outcome(run_id, pass, fail, skip, ms)` | 0 fail → quiet, 1-5 → warning, >5 → critical | `nightly_robot` |
| `alert_nightly_crash(exc)` | always critical | `nightly_robot` |
| `alert_p0_escalation(headline)` | always critical | `morning_report_p0` |
| `alert_cron_skipped(name, reason)` | warning | `skip_<name>` |

Всички hook-ове са best-effort — ако `telegram_bot` модулът липсва или Telegram API върне грешка, cron-ът продължава нормално (failure не се пропагира).

### 🔌 Integration patches (apply при sync)

#### `tools/stress/cron/sanity_checker.py`

```python
# след reda 39 (под import-ите):
from alerts.cron_hooks import alert_balance

# в края на main(), след heartbeat-а в apply branch:
alert_balance(len(failures), args.hours, tenant_id, failures)
```

#### `tools/stress/cron/nightly_robot.py`

```python
# след reda 58 (под seed_rng import):
from alerts.cron_hooks import alert_nightly_outcome, alert_nightly_crash

# в края на main(), след heartbeat-а:
alert_nightly_outcome(run_id, pass_n, fail_n, skip_n, duration_ms)

# в __main__ except branch:
except Exception as e:
    heartbeat("nightly_robot", "FAIL", f"unhandled: {type(e).__name__}: {str(e)[:200]}")
    alert_nightly_crash(e)
    raise
```

#### `tools/stress/cron/code_analyzer.sh`

Замени inline curl блока (lines ~72-81) с:

```bash
if grep -qE "P0 ESCALATION|3-та нощ подред|🚨" "$OUTPUT_MD" 2>/dev/null; then
    head_line=$(grep -m1 "🚨" "$OUTPUT_MD" || echo "P0 escalation")
    "$PYTHON" "${TOOLS_STRESS}/alerts/telegram_bot.py" \
        --severity critical \
        --topic morning_report_p0 \
        --message "P0 escalation: ${head_line}" \
        >>"$LOG_FILE" 2>&1 || true
fi
```

Старият inline curl остава като fallback — telegram_bot.py CLI има същия ефект, но добавя rate limiting + state file.

---

## 🧪 Python API

```python
from alerts.telegram_bot import send_alert

result = send_alert(
    "warning",                            # severity
    "5 balance failures last 24h",        # message
    topic="balance",                      # за rate limiting
    context={"tenant_id": 99, "delta_total": -32},  # доп. полета
    rate_limit_seconds=60,                # default
    dry_run=False,                        # ако True → принтира
)

# result = {"sent": True/False, "reason": "...", "severity": ..., "topic": ...}
```

`send_alert()` НИКОГА не хвърля изключения — всички failures се връщат в `result["reason"]`.

---

## 🛠 CLI

```bash
# Изпрати warning alert
python3 telegram_bot.py --severity warning --topic balance \
    --message "Balance check failed" \
    --context-json '{"failures": 5, "tenant_id": 99}'

# Test без изпращане
python3 telegram_bot.py --dry-run --severity info --message "hello"

# Quick test
python3 telegram_bot.py --send-test
```

---

## 📊 Rate limiter state

State се пази във `tools/stress/data/telegram_state.json`:

```json
{
  "balance":   { "last_sent": 1731234567, "last_severity": "warning", "last_message": "..." },
  "nightly_robot": { "last_sent": 1731234500, "last_severity": "warning", "last_message": "..." },
  "_history":  [ {"ts": "2026-05-09T05:21", "topic": "balance", ...}, ... ]
}
```

`_history` пази последните 200 алерта. Useful за post-mortem.

---

## ⚠️ Известни ограничения

1. **Без callback handlers** — този модул само изпраща, не слуша. Бъдеща версия може да добави `/status` команди от Telegram → cron status.
2. **Няма threaded pool** — синхронен HTTP запис. При много алерти (>100/min), cron-ът ще se забави. Realistic load: 1-5 alerts/денонощие.
3. **Single chat_id** — поддържа само един получател. За екип, използвай Telegram group и сложи group chat_id.
4. **State file race condition** — write-then-replace е atomic, но при едновременен запис от 2 cron-а може да загуби 1 history запис. Не е критично.

---

## 🛡 Iron Law

- `send_alert()` НИКОГА не raises.
- `/etc/runmystore/telegram.env` не се чете в dry-run mode.
- Token-ът никога не се принтира в логовете (dry-run output не го включва).
