# SESSION S82.STUDIO.APPLY — Migration applied, cron installed, lingerie template live

**Дата:** 2026-04-26
**Модел:** Claude Opus 4.7 (1M context)
**Статус:** ✅ DONE — schema applied на live, cron installed, lingerie prompt updated.
**Предходна сесия:** `docs/SESSION_S82_STUDIO_BACKEND_HANDOFF.md` (commit `fcf0ec1`)
**Паралелна сесия:** Chat 1 пише `chat.php` / `life-board.php` / `partials/*` (вид. `3e23896 S82.STUDIO.NAV: AI Studio entry button in chat.php`) — НЕ съм пипал тези файлове.

---

## 🎯 SCOPE — изпълнено

P1–P5 от задачата.

| Phase | Действие | Резултат |
|---|---|---|
| P1 — Pre-apply diagnostic | `run_diag.py --module=insights --tenant=99 --pristine` | ⚠️ Pipeline functional, pre-existing failures (не блокират — независими от schema) |
| P2.1 — Backup | `mysqldump tenants products ai_image_usage` | ✅ `/root/pre_studio_backend_20260426_1613.sql` (262 KB) |
| P2.2 — Clone test | `rms_studio_check` disposable DB up + down + drop | ✅ Up + down clean roundtrip; 5 prompt seeds, plan defaults seeded |
| P2.3 — Apply on live | `mysql runmystore < up.sql` | ✅ 3 нови таблици + 6 tenant cols + 4 product cols + idx_ai_category |
| P2.4 — Smoke test | `get_credit_balance` / `count_products_needing_ai` / `get_prompt_template` | ✅ Всички 4 проверки PASS |
| P3 — Install cron | `crontab -u www-data` | ✅ Installed; dry-run reports "reset 0 tenants" |
| P4 — Lingerie prompt | `UPDATE ai_prompt_templates ... WHERE category='lingerie'` | ✅ id=1, 566 chars, real wording от AI_CREDITS_PRICING_v2.md |
| P5 — Post-apply diagnostic | Same command | ✅ Identical results; no regression |

---

## 📦 Backup paths

- **DB backup (pre-apply):** `/root/pre_studio_backend_20260426_1613.sql` (262 611 bytes)
- **Cron backup (pre-install):** `/tmp/cron_backup_20260426_1615.txt` (празно — www-data нямаше cron преди)

Restore инструкции (ако трябва revert):
```bash
mysql runmystore < /var/www/runmystore/migrations/20260427_001_ai_studio_schema.down.sql
mysql runmystore < /root/pre_studio_backend_20260426_1613.sql   # only if base tables corrupted
crontab -u www-data /tmp/cron_backup_20260426_1615.txt          # restore empty cron
```

---

## 🧪 Diagnostic results — pre vs post apply

`diagnostic_log` таблица — runs 14 (pre) и 15 (post) идентични:

| Run | Timestamp | Total | Pass | Fail | Cat A | Cat D |
|---|---|---|---|---|---|---|
| 13 (older S81 baseline) | 2026-04-26 11:14:41 | 52 | 22 | 30 | 47.83% | 21.43% |
| 14 (PRE apply) | 2026-04-26 16:12:25 | 52 | 22 | 30 | 47.83% | 21.43% |
| 15 (POST apply) | 2026-04-26 16:17:13 | 52 | 22 | 30 | 47.83% | 21.43% |

**Tълкувание:** Pipeline функционира end-to-end (no crashes). Failures са pre-existing в insights logic (compute-insights.php / oracle expectations / seed scenarios) — независими от моите schema промени. Cat A/D < 100% е **pre-existing state** и блокира bug-fix work, не блокира schema apply.

**Pre-existing failures видими в seed phase:**
- `lost_demand_pos: OperationalError: (1054, "Unknown column 'query' in 'field list'")` — seed_oracle scenario с грешна колона
- `basket_pair_b_pos: OperationalError: (1364, "Field 'total' doesn't have a default value")` — sales table NOT NULL constraint без default

И двете са seed-data bugs, не логически — извън моя scope.

---

## 📊 Live DB state след apply

```
SHOW TABLES LIKE 'ai_%' :
  ai_advice_log         (pre-existing)
  ai_credits_balance    ← NEW (0 rows)
  ai_image_jobs         (pre-existing)
  ai_image_usage        (pre-existing)
  ai_insights           (pre-existing)
  ai_prompt_templates   ← NEW (5 rows: 1 active lingerie + 4 inactive)
  ai_shown              (pre-existing)
  ai_spend_log          ← NEW (0 rows)
  ai_topic_rotation     (pre-existing)
  ai_topics_catalog     (pre-existing)
```

```
tenants — нови колони:
  included_bg_per_month     INT NOT NULL DEFAULT 0
  included_desc_per_month   INT NOT NULL DEFAULT 0
  included_magic_per_month  INT NOT NULL DEFAULT 0
  bg_used_this_month        INT NOT NULL DEFAULT 0
  desc_used_this_month      INT NOT NULL DEFAULT 0
  magic_used_this_month     INT NOT NULL DEFAULT 0

Plan defaults seeded:
  PRO    × 2  → 300 / 500 / 20
  START  × 45 → 50  / 100 / 5

NOTE: 0 редa с plan='free' се появиха в GROUP BY — има 0 free tenants (или
всички са plan_effective='pro' от trial). Defaults остават NOT NULL DEFAULT 0
така че бъдещи free signups автоматично получават правилно нулеви включени.
```

```
products — нови колони:
  ai_category    VARCHAR(20)  NULL  + INDEX idx_ai_category
  ai_subtype     VARCHAR(30)  NULL
  ai_description TEXT         NULL
  ai_magic_image VARCHAR(500) NULL
```

---

## 🪡 Lingerie prompt — финална версия (id=1, 566 chars)

```
STUDIO PHOTO. White background.
Model torso only. Model slightly turned to the right.
DO NOT CHANGE THE GARMENT.
LOCK THE SIDE WIDTH EXACTLY as in the reference product photo.
KEEP THE ORIGINAL PATTERN SIZE, ORIGINAL CUT, ORIGINAL HIP WIDTH.
DO NOT widen hips. Do NOT stretch sides. Do NOT adapt product to the body.
The product controls the shape. The body must adapt to the product.
Match the exact proportions from the flat product photo.
STRICT PROPORTION LOCK. 1:1 garment width replication.
Maintain EXACT horizontal tightness — NO lateral expansion allowed.
```

`notes`: "S82.STUDIO.APPLY: real wording from AI_CREDITS_PRICING_v2.md (Тихол approved)"

Останалите 4 категории (clothes/jewelry/acc/other) остават `is_active=0` placeholders. Тихол одобрява per template after.

---

## ⏱ Cron entry installed

```bash
$ crontab -u www-data -l
0 0 1 * * cd /var/www/runmystore && /usr/bin/php cron-monthly.php >> /var/log/runmystore/cron-monthly.log 2>&1
```

`/var/log/runmystore/` създадена + chowned www-data:www-data.

Dry-run потвърждава: `[2026-04-26 16:16:45] cron-monthly OK: reset 0 tenants in 4ms`. На 1-во число се reset-ват `bg_used_this_month` / `desc_used_this_month` / `magic_used_this_month`.

NOTE: Първоначално сложих cron entry-то без `cd /var/www/runmystore &&` (грешка при моя shell escape). Поправено веднага в втори опит — текущата версия е финалната с правилния cd prefix.

---

## ⚠️ Unexpected findings

1. **Pre-existing diagnostic failures (52 scenarios — 22 pass / 30 fail).** Не блокираха apply per task spec. Категория A 47.83% и D 21.43% са state от S81, не въведени от мен. Тихол / шеф-чат да решат кога да се attack-нат тези.

2. **Seed phase errors** при diagnostic run-а:
   - `lost_demand_pos` → seed scenario references колона `query` която вече не съществува (seed_oracle bug)
   - `basket_pair_b_pos` → `sales.total` няма default value, seed-ът не го supply-ва

3. **0 free tenants** на DB (по plan group-ване): всички 47 active tenants са start (45) или pro (2). Нямам очаквана free baseline, но миграцията щe seed-ва правилно бъдещи free регистрации (NOT NULL DEFAULT 0).

4. **Crontab initial typo (self-fixed):** Първият `crontab -u www-data -` пипe missнах `cd /var/www/runmystore &&` part — bash heredoc-ът ми загуби фразата при escape. Загубата би довела до cron failure (relative path). Поправих с `crontab -u www-data /tmp/wwwdata_cron.txt` подход. Текущата записана е коректна.

5. **`AI_CREDITS_PRICING_v2.md` НЕ е на диска** — wording-ът на lingerie template дойде от user message (Тихол paste-на го директно). Попълних `notes` колоната с "Тихол approved" за audit trail.

---

## ❓ OPEN QUESTIONS — отложени за бъдеща Тихол review

(Същите като в backend handoff — НЕ съм решавал нищо сам.)

1. **nano-banana-2 vs pro switch** — config flag готов, чака Тихол
2. **Stripe Connect нови packs** — backend SKU map не е стартиран
3. **Default prompts за clothes / jewelry / acc / other** — 4 placeholder template-а с `is_active=0`
4. **DROP стара `credits` колона** — план: 30 дни grace, drop ~2026-05-27 в нова migration
5. **UX rewire `ai-studio.php`** — trябва да чете от `get_credit_balance()` вместо `tenants.ai_credits_bg` директно
6. **BIZ tier ENUM extension** — `tenants.plan` ENUM не съдържа 'biz'

---

## 🚦 Next session — препоръки

- [ ] Шеф-чат + Chat 1 — rewire `ai-studio.php` за 3-те типа credits (свързано с UX risk)
- [ ] STUDIO.16 (bulk ops) — backend готов, чакат UI hook-овете в `count_products_needing_ai()`
- [ ] Тихол одобрява prompts на 4 placeholder templates → `UPDATE is_active=1`
- [ ] Тихол избира nano-banana-2 vs pro → flip `AI_MAGIC_MODEL` define
- [ ] Тихол / друг chat — investigation на pre-existing 30 diagnostic failures (отделна сесия)

---

## 🛡 IRON PROTOCOL compliance

- ✅ Никакъв base64 в paste-нати скриптове
- ✅ Rule #19: `git status` + `git log -5` + `git pull` ПРЕДИ apply; commits специфични paths (НЕ `-A`)
- ✅ DB::run() / DB::tx() — никога $pdo директно
- ✅ Backup ПРЕДИ apply (mysqldump + cron txt)
- ✅ Test на /tmp/ disposable DB clone ПРЕДИ live apply
- ✅ Diagnostic run преди + след — confirm no regression
- ✅ Никакъв "Gemini" / "fal.ai" / "nano-banana" в потребителски strings (само logs / notes column)

---

## ✅ DOD checklist

- [x] Migration applied на live (3 нови таблици + 6 tenant cols + 4 product cols + index)
- [x] Smoke test backend PASS (3 helpers verified live)
- [x] Cron installed в `crontab -u www-data` с правилен `cd` prefix + log dir
- [x] Lingerie template updated (566 chars, реален wording, notes annotated)
- [x] Diagnostic pipeline functional преди + след (no regression)
- [x] SESSION_S82_STUDIO_APPLY_HANDOFF.md (този файл)
- [x] Git commits separate, specific paths (НЕ `-A`)
