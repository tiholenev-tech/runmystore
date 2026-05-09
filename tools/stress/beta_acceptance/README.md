# tools/stress/beta_acceptance/

**Phase P (S130 extension) — Beta Acceptance Checklist.**

**Resolves OQ-02** (STRESS_BOARD.md): "Beta Acceptance Checklist — Шеф пише draft, Тихол полира?". Тук имаме **автоматичния draft**.

---

## 📂 Файлове

| Файл | Роля |
|---|---|
| `checklist.py` | 30 автоматични проверки. Output: `BETA_ACCEPTANCE_REPORT.md`. |
| `__init__.py` | Маркер. |
| `README.md` | Този файл. |

---

## 🚦 Поведение по подразбиране

- **Read-only** — никакви DB / file mutations извън output отчета.
- **File-existence heuristics** — повечето checks гледат за наличие на ключови файлове / patches / migrations / locales.
- **3 възможни статуса:** `pass` / `fail` / `skip`.
  - `skip` = check изисква live data, DB достъп, или ресурс който не е наличен.
- **Output fallback** — ако `BETA_ACCEPTANCE_REPORT.md` в repo root е root-owned (write permission denied), отчетът отива в `tools/stress/data/BETA_ACCEPTANCE_REPORT.md`.

---

## 🛠 Usage

```bash
# Default — пише в repo root
python3 tools/stress/beta_acceptance/checklist.py

# Custom output path
python3 tools/stress/beta_acceptance/checklist.py --output /tmp/beta_report.md

# Допълнителен JSON output (за CI / dashboards)
python3 tools/stress/beta_acceptance/checklist.py \
    --json /tmp/beta_report.json

# Strict CI mode — exit 1 ако има поне 1 fail
python3 tools/stress/beta_acceptance/checklist.py --strict
```

---

## 📋 30-те проверки

| # | Категория | Какво |
|---|---|---|
| 1-3 | schema | db/schema.sql, migrations, stress migrations |
| 4-9 | known_bugs | 6-те бъга от STRESS_BUILD_PLAN (patch файлове) |
| 10-13 | security | HTTPS, secure cookies, dev-exec quarantine, CSRF audit |
| 14-15 | voice | Primary STT + fallback tier |
| 16-20 | visuals | products.php, sale.php, life-board.php, ai-studio.php, deliveries.php |
| 21 | design | design-kit/check-compliance.sh pass на 5-те визуални |
| 22-24 | audit | CSRF / PERF / AIBRAIN audit batches |
| 25 | build | APK > 0.9.5 |
| 26 | i18n | BG↔EN покриваемост >= 95% |
| 27 | performance | 5 визуални load < 3s (skip — изисква live test) |
| 28 | tracking | P0 RWQ items resolved или post-beta tagged |
| 29-30 | documentation | STRESS handoffs + STRESS система active |

---

## 🎯 Beta готовност

Скриптът категоризира резултата:

- 🟢 `fail == 0` → Готово.
- 🟡 `1 <= fail <= 3` → Близо.
- 🔴 `fail > 3` → НЕ Е ГОТОВО.

`skip` НЕ блокира готовност. Това са checks които искат live test или DB достъп — тествай ги ръчно при beta cutover.

---

## 🧪 Limitations & Honest Caveats

1. **File-existence ≠ correctness.** Например check #14 ("Voice STT primary") минава ако `voice.php` съществува, но не тества че реално работи на български. За deep behavior — нужни са integration tests + manual QA.

2. **Heuristic file paths** — checks търсят файлове по conventional имена. Ако в твоя repo нещата са на различни места, някои checks ще се skip-нат с false negative. Adjust path-овете в `checklist.py` ако нужно.

3. **Не тества production state** — checklist гледа repo state, не live. Например check #25 за APK гледа за файл в repo, не за актуалния build на Play Store.

4. **i18n 95% threshold** — е heuristic. Малки JSON-и с малко ключове може да минат с по-малко coverage от очакваното. За beta — увери се че всички user-visible strings са преведени.

5. **OQ-02 resolved means: draft is automated.** Final polish + interpretation остава за Тихол. Това е стартова точка, не финална verdict.

---

## 🔌 Integration с другите Phase O+ инструменти

```bash
# Пълен beta validation pipeline
python3 tools/stress/perf/load_test.py --apply --concurrent 10 --requests 100
python3 tools/stress/perf/db_query_profiler.py /var/log/mysql/slow.log \
    --output /tmp/slow.json
python3 tools/stress/perf/index_advisor.py --report /tmp/slow.json
python3 tools/stress/sync_registries.py --check
python3 tools/stress/sync_board_progress.py --check
python3 tools/stress/beta_acceptance/checklist.py --strict
```

Ако всички minat → beta-ready.

---

## 🛡 Iron Law

- Никога не пише в production DB.
- Не raises на missing files — върна `skip` вместо това.
- Output paths се дефолтват към `tools/stress/data/` ако root-owned.
