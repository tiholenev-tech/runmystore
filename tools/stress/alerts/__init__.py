"""tools/stress/alerts — Phase M (S130 extension).

Централизиран Telegram alerter за всички STRESS crons.

Resolves OQ-01 (Telegram bot integration).

Модули:
  - telegram_bot.py     — send_alert() + CLI + rate limiting
  - test_telegram.py    — dry-run + integration smoke

Конфигурация: /etc/runmystore/telegram.env
  TELEGRAM_BOT_TOKEN=...
  TELEGRAM_CHAT_ID=...
"""
