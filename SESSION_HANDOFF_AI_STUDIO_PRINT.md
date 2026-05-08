# 🧭 SESSION_HANDOFF_AI_STUDIO_PRINT.md

**Сесия:** Шеф-чат · AI Studio + Печат mockup workflow
**Дата:** 08.05.2026
**Статус:** ✅ APPROVED · готово за Claude Code implementation
**Beta deadline:** 14-15.05.2026 (7 дни)

---

## 0. TL;DR за следващия шеф-чат

В тази сесия се финализираха **всички 9 mockup-а** за `products.php` wizard + AI Studio + печат, в новия BICHROMATIC дизайн (Bible v4.1). Файловете са качени в **`/var/www/runmystore/`** (директно, НЕ в `/mockups/`). Заедно с тях е качен **`AI_STUDIO_LOGIC_DELTA.md`** който описва промените в AI Studio логиката спрямо v1.0.

**Тук свършва шеф-чат фазата за тези модули.** Следващата стъпка е **Claude Code сесия** (CC), която прилага PHP + JS rewrite на `products.php` + `partials/ai-studio-modal.php` + `partials/ai-studio-queue-overlay.php` (нов).

---

## 1. ФАЙЛОВЕ КАЧЕНИ В `/var/www/runmystore/`

### 1.1 HTML mockup файлове (10 броя)

| Файл | Какво показва | Източник в `products.php` |
|---|---|---|
| `P2_home_v2.html` | Home (lesny mode) — 4 ops + AI brain pill | `home.php` (preview only) |
| `P3_list_v2.html` | Списък продукти + filter pills + add FAB | `products.php` ред ~240+ |
| `P4_wizard_step1.html` | Wizard Step 1 — Single/Variations toggle + основни | ред 7100+ |
| `P4b_photo_states.html` | Photo capture states (camera/upload/AI find) | ред 7200+ |
| `P5_step4_variations.html` | Wizard Step 4 — variations matrix | ред 7600+ |
| `P6_matrix_overlay.html` | Variations matrix fullscreen overlay | ред 7650+ |
| **`P7_recommended.html`** | **Wizard Step 5 — Препоръчителни (НОВ)** | ред 7820+ (renderWizStep2) |
| **`P8_studio_main.html`** | **AI Studio standalone (от лесен режим)** | `ai-studio.php` |
| **`P8b_studio_modal.html`** | **Per-product модал (default · Бельо · Лесен)** | `partials/ai-studio-modal.php` |
| **`P8b_advanced_clothes.html`** | Per-product · Дрехи · Разширен | (вариант) |
| **`P8b_advanced_lingerie.html`** | Per-product · Бельо · Разширен | (вариант) |
| **`P8b_advanced_jewelry.html`** | Per-product · Бижута · Разширен (8 повърхности) | (вариант) |
| **`P8b_advanced_acc.html`** | Per-product · Аксесоари · Разширен | (вариант) |
| **`P8b_advanced_other.html`** | Per-product · Друго · Разширен (free prompt) | (вариант) |
| **`P8c_studio_queue.html`** | **Queue overlay (нов екран!)** | `partials/ai-studio-queue-overlay.php` (TBD) |
| **`P9_print.html`** | **Wizard Step 6 — Печат на етикети** | ред 7417-7482 |

**Bold = нови или променени в тази сесия.**

### 1.2 Документация (1 нов документ)

| Файл | Описание |
|---|---|
| **`AI_STUDIO_LOGIC_DELTA.md`** | Описва всички промени в AI Studio логиката спрямо `docs/AI_STUDIO_LOGIC.md` v1.0 (26.04.2026) |

---

## 2. ДОКУМЕНТАЦИЯ КОЯТО ШЕФ-ЧАТ ТРЯБВА ДА ПРОЧЕТЕ ПРИ СТАРТ

### 2.1 ЗАДЪЛЖИТЕЛНИ (always read first)

| # | Файл | Защо |
|---|---|---|
| 1 | `SHEF_RESTORE_PROMPT.md` | 16-question IQ test + държимост на context |
| 2 | `MASTER_COMPASS.md` | Координация между сесии · file ownership |
| 3 | `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` (2748 реда) | **Bible v4.1** — sacred neon glass + ALL design tokens |
| 4 | `docs/AI_STUDIO_LOGIC.md` (876 реда) | **Original AI Studio спецификация v1.0** |
| 5 | **`AI_STUDIO_LOGIC_DELTA.md`** (новo!) | **Промените в тази сесия** — read AFTER #4 |

### 2.2 ЗА ПРОЕКТА (продуктова стратегия)

| Файл | Какво съдържа |
|---|---|
| `DOCUMENT_1_LOGIC_PART_1_ONLY.md` (+ PART_2, PART_3) | 5-те закона · концепция · LIFE BOARD · 6 въпроса · planове |
| `docs/BIBLE_v3_0_CORE.md` | Tehnically tehnology stack |
| `docs/BIBLE_v3_0_TECH.md` | DB schema · Stripe · AI Safety · Testing |
| `PRODUCTS_DESIGN_LOGIC.md` | Wizard философия · 4 стъпки · AI parsing |
| `ROADMAP.md` | Phased rollout (Фаза 1-5) · feature priorities |

### 2.3 ЗА БЕТА LAUNCH (текущи приоритети)

| Файл | Защо |
|---|---|
| `PRIORITY_TODAY.md` | Какво се прави днес |
| `DAILY_RHYTHM.md` | Дневен ритъм за работа |
| `docs/DELIVERIES_BETA_READINESS.md` | Бета checklist |
| `docs/NEXT_SESSIONS_PLAN_27042026.md` | Sessions schedule |

### 2.4 ПРЕДИШНИ HANDOFF-И (за context)

| Файл | Какво описва |
|---|---|
| `HANDOFF_CONSOLIDATED.md` | Consolidated state на approved P2-P6 mockups |
| `HANDOFF_P4_P4b_FIND_COPY.md` | Find&copy logic + 3 AJAX endpoints + 16 i18n keys |
| `HANDOFF_S96_DESIGN_BICHROMATIC.md` | Bichromatic design transition |
| `HANDOFF_S95_STEP2_BUGFIX_SWEEP.md` | S95 wizard restructure (bugfix sweep) |
| `EOD_HANDOFF_S95.md` | End-of-day handoff S95 |

### 2.5 СПЕЦИФИЧНИ КЪМ AI STUDIO (исторически)

| Файл | Описание |
|---|---|
| `docs/SESSION_S82_STUDIO_MARATHON_HANDOFF.md` | Marathon на AI Studio логиката |
| `docs/SESSION_S82_STUDIO_BACKEND_HANDOFF.md` | Backend интеграция |
| `docs/SESSION_S82_VISUAL_HANDOFF.md` | Visual итерация |
| `SESSION_S82_SHELL_AI_STUDIO_HANDOFF.md` | Shell wrapper за AI Studio |

---

## 3. ЗАВЪРШЕНО В ТАЗИ СЕСИЯ

### 3.1 Mockup approval

✅ **P7 Препоръчителни** — APPROVED
- 4 секции: Цени · Детайли · AI Studio inline · finalPromptH
- Margin auto-calc 1 десетична · 3 цветни кредитни клетки · 3 ai-inline-rows
- Camera button само на Локация
- "Искаш ли AI обработка?" за и Single, и Variant

✅ **P8 AI Studio standalone** — APPROVED, нов BICHROMATIC
- Hero banner (q-magic) + 3-cell credits + "Купи още · -43% отстъпки"
- Bulk фон (47 продукта €2,35) + Bulk описание (89 продукта €1,78)
- AI магия по 5 категории с уникални hue (clothes/lingerie/jewelry/acc/other)
- История 8 thumbs + Стандартни настройки 3 редa

✅ **P8b Per-product модал** — APPROVED, нов BICHROMATIC + 5 advanced варианта
- Лесен режим: 1 click + auto-detect hint
- Разширен режим: 5 категории + sliding sub-panels
- Категория-специфично:
  - Дрехи/Бельо: подтипи + поза + кадрировка + фон + voice
  - Бижута: подтипи + 8 повърхности (без поза/кадрировка) + voice
  - Аксесоари: подтипи + изглед + фон + voice
  - Друго: textarea + voice + 3 примера + hint (без подтипи/поза/кадрировка)
- AI описание quick row под магията
- Quality Guarantee banner

✅ **P8c Queue overlay** — APPROVED, нов екран
- Bulk генерация (САМО safe automatic template) — €2,40 за 8 продукта
- Divider "ИЛИ"
- Individual list — tap на ред → отваря P8b модал
- Status pills: ЧАКА (amber) / ✓ ГОТОВ (green)

✅ **P9 Печат на етикети** — APPROVED, нов BICHROMATIC
- Success hero (q-gain) + animated checkmark + "Артикулът е записан!"
- 3 print mode tabs: € + лв (default за BG) / Само € / Без цена
- Toggle "Печат без баркод"
- Warning banner: dual pricing до 08.08.2026
- Combos list с qty steppers + ×2 / 1:1 buttons + per-row print btn
- Голям "ПЕЧАТАЙ ВСИЧКИ (15 ет.)" gradient бутон
- CSV export + footer (Добави нов / Затвори / Към инвентаризацията)

### 3.2 Visual standardization

✅ **0 emoji в всички 10 mockup файла** — всички са SVG icons (Bible §14 compliance)
- Категории (5): SVG + текст
- Подтипи (33): САМО текст (без icon — по-чисто)
- Поза/Кадрировка/Фон/Повърхност/Изглед: SVG + текст

### 3.3 Architectural decisions (в Delta документа)

✅ **Bulk магия = разрешена в P8c, но САМО със safe automatic template**
- Без избор на стойка/поза/кадрировка/фон в bulk
- Customization (chips) остава САМО per-product (P8b)
- Защо: магията е €0,30/бр; ако bulk настройките са грешни → много refunds на наша сметка

✅ **3-екранен flow за AI Studio (нов спрямо v1.0)**
```
Lesny mode → P8 standalone → tap категория → P8c queue → tap продукт → P8b модал
```

---

## 4. NEXT — CLAUDE CODE СЕСИЯ

### 4.1 Файлове за update (по приоритет)

```
1. partials/ai-studio-modal.php          ← STRUCTURAL + visual rewrite (P8b)
2. ai-studio.php                          ← visual emoji→SVG (P8 standalone)
3. partials/ai-studio-queue-overlay.php   ← НОВ ФАЙЛ (P8c — досега не съществува)
4. ai-image-processor.php                 ← добавя type=bulk_magic_safe endpoint
5. settings/ai-defaults.php               ← НОВ ФАЙЛ за per-tenant default настройки
6. products.php (renderWizStep2 + renderPrintStep) ← P7 + P9 visual rewrite
```

### 4.2 DB migration

```sql
-- 20260508_001_ai_studio_safe_template_seeds.sql
-- Seeds default templates за clothes/jewelry/acc/other (досега има само lingerie)
INSERT INTO ai_prompt_templates (category, template, success_rate, is_active) VALUES
  ('clothes', '...', NULL, 1),
  ('jewelry', '...', NULL, 1),
  ('acc',     '...', NULL, 1),
  ('other',   '{user_free_prompt} -- Object position must remain natural...', NULL, 1);
```

Виж `AI_STUDIO_LOGIC_DELTA.md` §4.3 за template принципи.

### 4.3 i18n keys (нови за добавяне)

Виж `AI_STUDIO_LOGIC_DELTA.md` + последните mockup-и за конкретните `{T_*}` placeholder-и. Около 35-40 нови ключа за:
- T_AI_BULK_TITLE / T_AI_BULK_SAFE_ONLY / T_AI_BULK_INFO_LINE1+2 / T_AI_BULK_TESTED_TEMPLATE
- T_AI_GENERATE_ALL_8 (с pluralization)
- T_AI_INDIVIDUAL_TITLE
- T_AI_WAITING / T_AI_DONE
- T_AI_TOTAL_BULK / T_AI_ESTIMATE
- T_BACK_TO_STUDIO / T_AI_OR
- T_PRINT_LABELS / T_PRINT_NO_BARCODE / T_VARIATIONS_FOR_PRINT
- T_PRINT_ALL / T_DOWNLOAD_CSV_ONLINE_STORE
- T_DUAL_PRICING_WARN / T_PRODUCT_SAVED
- T_AI_MODE_EASY / T_AI_MODE_ADVANCED
- T_AI_AUTO_DETECT / T_AI_SETTINGS_FROM_DEFAULTS
- T_AI_CFG_SURFACE / T_AI_CFG_VIEW / T_AI_CFG_VOICE_ADD
- T_AI_DESCRIBE_FREE / T_AI_FREE_PLACEHOLDER / T_AI_FREE_HINT / T_AI_EXAMPLES
- T_AI_VOICE_EXTRA_TITLE / T_AI_VOICE_EXTRA_EXAMPLES
- T_AI_QG_TITLE / T_AI_QG_SUB
- T_AI_DESC_FOR_SEO

### 4.4 Order на работа (препоръка)

```
Step 1: P8b модал (най-голяма промяна) — partials/ai-studio-modal.php
Step 2: P8c queue overlay (нов файл) — partials/ai-studio-queue-overlay.php
Step 3: P8 standalone (само visual) — ai-studio.php
Step 4: P9 печат (само visual) — products.php renderPrintStep
Step 5: P7 препоръчителни (само visual) — products.php renderWizStep2
Step 6: Backend — ai-image-processor.php добавя bulk_magic_safe
Step 7: DB migration + ai-defaults.php
Step 8: i18n — 35-40 нови keys
Step 9: Test на tenant_id=7
Step 10: Deploy + commit + Capacitor APK rebuild
```

---

## 5. OPEN QUESTIONS (за Тихол при Claude Code сесия)

1. **Безопасен template per категория** — кой template се използва за bulk_magic_safe? Lingerie има 90% success. За clothes/jewelry/acc/other още няма definitive template. Дали:
   - (a) Bulk магия се пуска само за категории с template (≥80% success), останалите → "Няма bulk за тази категория, отвори индивидуално"
   - (b) Fallback на generic template (по-нисък success) с warning banner

2. **Bulk job state** — ако Пешо затвори overlay-я по средата на bulk генерация, какво става?
   - (a) Jobs продължават backend-side, Пешо вижда notification когато готово
   - (b) Pause на place, при следващ open continue

3. **Resume midway** — ако bulk-ът се рестартира след product 4/8, webhook-ове ще възобновяват ли?
   - Препоръка: idempotent processor с checkpoint. Stop при Пешо ✓ Запази, ↻ Retry или ✕ Refund.

4. **AI описание quick row в P8b** — при bulk сценарий, нужно ли е да го заместим с bulk-style row "Описания за всичките 8"? Или остава винаги per-product?
   - Препоръка: остава per-product. AI описание има отделен bulk бутон в P8 standalone (P8 → "89 продукта без описание").

5. **P9 Step 6 — `_fromInventory` flag** — кога точно се показва "Към инвентаризацията" бутона? При бутон от inventory.php → wizard, или само от specific endpoint?

---

## 6. РИСКОВЕ ЗА БЕТА LAUNCH (14-15.05)

### 6.1 Висок риск
- **Bulk магия default templates** — само lingerie има тестван template (90% success). За другите 4 категории трябва или да се тестват, или да се disable bulk магията за тях за бета.
- **Capacitor APK rebuild** — последния APK от края на април не съдържа Sprint A fixes. Трябва нов rebuild след Claude Code sesията.

### 6.2 Среден риск
- **i18n** — 35-40 нови keys. Ако beta стартира със Bulgarian-only, OK. Но ако ENI има ENI-RO магазин, RO ключовете не са попълнени.
- **DB migration** — `ai_prompt_templates` template seeds. Ако миграцията не пусне → AI Studio bulk магия пада за clothes/jewelry/acc/other.

### 6.3 Нисък риск
- **Visual** — mockup-и са одобрени от Тихол. Claude Code просто пренася в PHP/JS.
- **i18n keys в Print стъпката** — ENI продава само в BG, dual pricing работи правилно до 08.08.2026.

---

## 7. РАБОТНА ИНФРАСТРУКТУРА

- **Droplet:** DigitalOcean Frankfurt `164.90.217.120` · `/var/www/runmystore/`
- **GitHub:** `tiholenev-tech/runmystore` (public)
- **DB:** `runmystore` (MySQL 8) · creds в `/etc/runmystore/db.env`
- **Test tenant:** `tenant_id=7`
- **Beta tenant:** ENI (5 магазина, BG)
- **API keys:** `/etc/runmystore/api.env` (Gemini × 2, fal.ai, Groq Whisper, Stripe)

### Claude Code launch (за следващата сесия)

```bash
# SSH to droplet
ssh root@164.90.217.120

# tmux session за persistent работа
tmux new -s code1
cd /var/www/runmystore
git pull origin main

# Old mockups files са в /var/www/runmystore/ root (НЕ в /mockups/)
ls P7_recommended.html P8_*.html P9_print.html AI_STUDIO_LOGIC_DELTA.md

# Launch Claude Code
claude
```

Първо съобщение към Claude Code:
```
Прочети следните файлове в този ред:
1. /var/www/runmystore/SHEF_RESTORE_PROMPT.md
2. /var/www/runmystore/MASTER_COMPASS.md
3. /var/www/runmystore/DESIGN_SYSTEM_v4.0_BICHROMATIC.md
4. /var/www/runmystore/docs/AI_STUDIO_LOGIC.md
5. /var/www/runmystore/AI_STUDIO_LOGIC_DELTA.md
6. /var/www/runmystore/SESSION_HANDOFF_AI_STUDIO_PRINT.md (този файл)

Mockup файлове в /var/www/runmystore/:
- P7_recommended.html (wizard step 5)
- P8_studio_main.html (ai-studio.php)
- P8b_studio_modal.html + 5 варианта (per-product modal)
- P8c_studio_queue.html (нов екран — partials/ai-studio-queue-overlay.php)
- P9_print.html (wizard step 6)

Започни от order step 1: partials/ai-studio-modal.php (P8b → PHP rewrite).
```

---

## 8. КОНТРОЛ — ШТО ШЕФ-ЧАТ НЕ ПРАВИ

❌ **Не пиша production PHP/JS код** — само mockups.
❌ **Не commit-вам в git** — само Claude Code прави това.
❌ **Не модифицирам DB директно** — само мигриране в Claude Code сесия.
❌ **Не правя deploy** — само Claude Code.

✅ **Какво правя:** mockups, design decisions, logic specifications, handoff документи.

---

## 9. SESSION SUMMARY

| Метрика | Стойност |
|---|---|
| Mockup файлове създадени/обновени | 10 |
| Документи нови | 2 (Delta + този handoff) |
| Emoji премахнати → SVG | 51 |
| Нови екрани (без оригинален mockup) | 1 (P8c queue overlay) |
| Architectural decisions финализирани | 3 (bulk магия safe-only · 3-screen flow · category-specific options) |
| Open questions за Claude Code | 5 |
| Критични беta launch рискове | 2 (bulk templates · Capacitor rebuild) |

---

**КРАЙ НА HANDOFF-А**

*За въпроси: чети `AI_STUDIO_LOGIC_DELTA.md` секции 1-9 + конкретен mockup HTML файл.*
*Approval: Тихол · 08.05.2026*
*Next: Claude Code сесия (CC) с първо съобщение от §7 по-горе.*
