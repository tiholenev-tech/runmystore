# 🧭 SESSION_HANDOFF_CONSOLIDATED.md

**Сесия:** Шеф-чат · AI Studio + Печат + Lesny + Detailed mode mockups
**Дата:** 08.05.2026
**Статус:** ✅ ALL APPROVED · готово за Claude Code implementation
**Beta deadline:** 14-15.05.2026 (7 дни)

> **Този handoff заменя `SESSION_HANDOFF_AI_STUDIO_PRINT.md`** (включва всичко от него + новите Lesny/Detailed файлове).

---

## 0. TL;DR за следващия шеф-чат

В тази сесия се финализираха **12 mockup-а** за `products.php` wizard + AI Studio + печат + lesny + detailed режими, в новия BICHROMATIC дизайн (Bible v4.1). Файловете са качени в **`/var/www/runmystore/`** (директно, НЕ в `/mockups/`).

3 нови документа:
- **`AI_STUDIO_LOGIC_DELTA.md`** — промени в AI Studio логиката
- **`DETAILED_MODE_DECISION.md`** — решение за detailed mode архитектура
- **`SESSION_HANDOFF_CONSOLIDATED.md`** (този файл)

**Тук свършва шеф-чат фазата.** Следващата стъпка е **Claude Code сесия** (CC), която прилага PHP + JS rewrite.

---

## 1. ФАЙЛОВЕ В `/var/www/runmystore/`

### 1.1 HTML mockup файлове (12 броя · всичките APPROVED)

| Файл | Какво показва | Production target |
|---|---|---|
| `P2_home_v2.html` | Home (lesny mode) — старата версия | `home.php` (replaced by P10) |
| `P3_list_v2.html` | Списък продукти + filter pills + add FAB | `products.php` ред ~240+ |
| `P4_wizard_step1.html` | Wizard Step 1 — Single/Variations toggle | ред 7100+ |
| `P4b_photo_states.html` | Photo capture states | ред 7200+ |
| `P5_step4_variations.html` | Wizard Step 4 — variations matrix | ред 7600+ |
| `P6_matrix_overlay.html` | Variations matrix fullscreen overlay | ред 7650+ |
| `P7_recommended.html` | Wizard Step 5 — Препоръчителни | ред 7820+ (renderWizStep2) |
| `P8_studio_main.html` | AI Studio standalone (от лесен режим) | `ai-studio.php` |
| `P8b_studio_modal.html` + 5 advanced × | Per-product модал + 5 категории | `partials/ai-studio-modal.php` |
| `P8c_studio_queue.html` | Queue overlay (НОВ екран) | `partials/ai-studio-queue-overlay.php` |
| `P9_print.html` | Wizard Step 6 — Печат на етикети | ред 7417-7482 |
| **`P10_lesny_mode.html`** | **Лесен режим v3 (с Weather Forecast)** | `life-board.php` |
| **`P11_detailed_mode.html`** | **Подробен режим (без ops, +bottom nav)** | `chat.php` |

**Bold = от тази сесия.**

### 1.2 Документация (3 нови документа)

| Файл | Описание |
|---|---|
| **`AI_STUDIO_LOGIC_DELTA.md`** | Промени в AI Studio логиката (3-екранен flow, bulk safe-only, 5 категории) |
| **`DETAILED_MODE_DECISION.md`** | Защо запазваме detailed mode + какво е delta-та (само bottom nav + filter pills + 12 сигнала) |
| **`SESSION_HANDOFF_CONSOLIDATED.md`** | Този файл — handoff за следващите шеф-чат + Claude Code сесии |

---

## 2. ДОКУМЕНТАЦИЯ КОЯТО ШЕФ-ЧАТ ТРЯБВА ДА ПРОЧЕТЕ ПРИ СТАРТ

### 2.1 ЗАДЪЛЖИТЕЛНИ (always read first)

| # | Файл | Защо |
|---|---|---|
| 1 | `SHEF_RESTORE_PROMPT.md` | 16-question IQ test + state restore |
| 2 | `MASTER_COMPASS.md` | Координация между сесии · file ownership |
| 3 | `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` (2748 реда) | **Bible v4.1** — sacred neon glass + ALL design tokens |
| 4 | `docs/AI_STUDIO_LOGIC.md` (876 реда) | Original AI Studio спецификация v1.0 |
| 5 | `AI_STUDIO_LOGIC_DELTA.md` (новo) | Промените в AI Studio за beta |
| 6 | `DETAILED_MODE_DECISION.md` (новo) | Lesny vs Detailed архитектурно решение |
| 7 | `SESSION_HANDOFF_CONSOLIDATED.md` | Този файл |

### 2.2 BIBLE — ОТ КОЯ ЧАСТ ЗА КОЕ

| Темата | Кой документ + секция |
|---|---|
| **Simple mode UI / Lesny подредба** | `11_simple_mode_ui.md` (всичко) + `docs/BIBLE_v3_0_TECH.md` §1.2 |
| **Detailed mode** | `docs/BIBLE_v3_0_TECH.md` §1.3 + `DETAILED_MODE_DECISION.md` |
| **Multi-role visibility** | `09_multi_role_visibility.md` (целият) |
| **AI Chat philosophy** | `docs/BIBLE_v3_0_CORE.md` §19 + `AI_CONVERSATION_FLOW_TOPICS_v1.md` |
| **Onboarding** | `08_onboarding.md` + `docs/BIBLE_v3_0_CORE.md` §20-§23 |
| **Roles + permissions** | `docs/BIBLE_v3_0_CORE.md` §2 (475-499) |
| **i18n правила** | `docs/BIBLE_v3_0_CORE.md` §3 |
| **Anti-hallucination** | `06_anti_hallucination.md` |
| **AI Studio** | `docs/AI_STUDIO_LOGIC.md` + `AI_STUDIO_LOGIC_DELTA.md` |
| **Wizard logic** | `PRODUCTS_DESIGN_LOGIC.md` |
| **Print labels** | `products.php` ред 7417-7482 (single source) |

### 2.3 БЕТА ПРИОРИТЕТИ (4 файла)

- `PRIORITY_TODAY.md`
- `DAILY_RHYTHM.md`
- `docs/DELIVERIES_BETA_READINESS.md`
- `docs/NEXT_SESSIONS_PLAN_27042026.md`

### 2.4 АРХИВ (предишни handoff-и · референция)

- `HANDOFF_CONSOLIDATED.md` (P2-P6 approved)
- `HANDOFF_S96_DESIGN_BICHROMATIC.md` (BICHROMATIC transition)
- `HANDOFF_S95_STEP2_BUGFIX_SWEEP.md` (S95 bugfixes)
- `EOD_HANDOFF_S95.md` (end-of-day S95)
- `docs/SESSION_S82_STUDIO_MARATHON_HANDOFF.md` (AI Studio history)

---

## 3. ЗАВЪРШЕНО В ТАЗИ СЕСИЯ

### 3.1 AI Studio + Печат (commits S96.MOCKUPS + S96.DOCS — already pushed)

✅ **P7 Препоръчителни** — wizard step 5 · 4 секции · margin auto-calc
✅ **P8 AI Studio standalone** — bulk фон + bulk описание + 5 категории
✅ **P8b Per-product modal** + 5 advanced варианта (clothes/lingerie/jewelry/acc/other)
✅ **P8c Queue overlay** — НОВ екран · bulk safe-only template + individual list
✅ **P9 Печат** — success hero + 3 print modes + dual pricing warning + combos list

### 3.2 Lesny + Detailed mode (НОВО · pending commit)

✅ **P10 Lesny mode v3** — promotion на 4 ops горе + Weather Forecast Card + AI Help + collapsed Life Board с expand animation
✅ **P11 Detailed mode** — без ops grid + filter pills + 12 сигнала per module + bottom nav
✅ **AI Help card** — кратко обяснение + 6 примерни въпроса + видео placeholder
✅ **Weather Forecast Card** — 3/7/14 дни segmented + AI препоръки (витрина / поръчай / трансфер) + Open-Meteo source

### 3.3 Architectural decisions

✅ **Bulk магия = САМО safe automatic template** (P8c)
✅ **3-екранен flow за AI Studio** (Lesny → P8 → P8c → P8b)
✅ **Detailed mode = Lesny + bottom nav + повече сигнали − ops grid** (опростена архитектура)
✅ **AI Brain pill ПРЕМАХНАТА** в lesny (дублира chat input bar)
✅ **4 ops buttons → ГОРЕ в lesny** + info бутончета (Bible §19.1.9)

### 3.4 Visual standardization

✅ **0 emoji във всичките 12 mockup файла** (Bible §14)
✅ **Production-parity expand animation** (max-height + conic glow ring + chevron rotate)

---

## 4. NEXT — CLAUDE CODE СЕСИЯ

### 4.1 Файлове за update (по приоритет)

```
PHASE A — AI Studio (commits S96.MOCKUPS done; pending implementation):
1. partials/ai-studio-modal.php          ← STRUCTURAL + visual rewrite (P8b)
2. partials/ai-studio-queue-overlay.php  ← НОВ ФАЙЛ (P8c)
3. ai-studio.php                          ← visual emoji→SVG (P8 standalone)
4. ai-image-processor.php                 ← добавя type=bulk_magic_safe
5. settings/ai-defaults.php               ← НОВ ФАЙЛ
6. products.php (renderPrintStep + renderWizStep2) ← P9 + P7

PHASE B — Lesny + Detailed (NEW — този handoff):
7. life-board.php                         ← P10 структура + Weather Forecast Card + Help card
8. chat.php                               ← P11 структура (rewrite от 1642 → ~600 реда)
9. partials/header.php                    ← без промяна (toggle вече работи)
10. cron/weather-recs-generator.php       ← НОВ ФАЙЛ (daily 06:00)
11. db migrations:
    - 20260509_001_weather_recs.sql       ← weather_recs таблица
    - 20260509_002_ai_studio_safe_template_seeds.sql  ← prompt templates per category
    - 20260509_003_ai_insights_module_column.sql ← добавя module ENUM

PHASE C — bonus:
12. design-kit/check-compliance.sh        ← опционален bypass за mockup folders
```

### 4.2 i18n keys (общо ~70 нови)

Виж `AI_STUDIO_LOGIC_DELTA.md` § 7.3 + `DETAILED_MODE_DECISION.md` § 5.2.

Главни групи:
- AI Studio (~35 keys)
- Print step (~10 keys)
- Weather card (~15 keys)
- Filter pills (~8 keys)
- Help card (~5 keys)

### 4.3 Order на работа за Claude Code

```
Step 1: P8b модал (най-голяма промяна) — partials/ai-studio-modal.php
Step 2: P8c queue overlay (нов файл) — partials/ai-studio-queue-overlay.php
Step 3: P10 lesny — life-board.php promotion на ops + Weather Card
Step 4: P11 detailed — chat.php rewrite (drop ops + add filter pills + 12 insights)
Step 5: Backend — ai-image-processor.php добавя bulk_magic_safe
Step 6: Backend — cron/weather-recs-generator.php
Step 7: DB migrations (3 SQL файла)
Step 8: i18n — 70 нови keys
Step 9: P7 + P9 visual rewrite в products.php
Step 10: Test на tenant_id=7
Step 11: Deploy + commit + Capacitor APK rebuild
```

---

## 5. OPEN QUESTIONS (за Тихол при Claude Code сесия)

### От AI Studio:
1. **Безопасен template per категория** — Lingerie има 90% success. За clothes/jewelry/acc/other още няма definitive template. Дали (a) bulk магия само за категории с template, или (b) generic fallback?
2. **Bulk job state** — paused vs continued при затваряне на overlay-я?
3. **Resume midway** — webhook continuation или skip-completed?
4. **AI описание quick row в P8b** — bulk-style или per-product винаги?
5. **`_fromInventory` flag** — кога точно "Към инвентаризацията" се показва?

### От Detailed mode:
6. **Filter pills priority** — кой module е default selected: "Всички" или последния избран от user?
7. **Insight LIMIT** — 12 default, 20 max? Pagination или infinite scroll?
8. **Module assignment legacy data** — ALTER TABLE с DEFAULT='sales', но как обновяваме старите records?

### От Weather:
9. **AI recs hallucination risk** — какъв model генерира? GPT-4o-mini fallback?
10. **Multi-store transfers in solo tenant** — какво да показва `wfc-rec.transfer` при 1 магазин? (skip / show different rec)

---

## 6. КРИТИЧНИ РИСКОВЕ ЗА БЕТА LAUNCH (14-15.05)

### Висок
- **AI Studio bulk templates** — само lingerie има тестван template. Опция: за beta launch disable bulk магия за clothes/jewelry/acc/other (само индивидуално).
- **Capacitor APK rebuild** — последния APK от края на април не съдържа Sprint A/B fixes. След Claude Code → задължителен rebuild.

### Среден
- **i18n RO ключове** — 70 нови ключа. ENI е BG-only за beta → нискорисково. Но за RO expansion → блокер.
- **Weather AI recs стабилност** — нови LLM prompts може да хлуцират. Mitigation: hardcoded preview prompts ревюирани от Тихол + cron-генерирани с retry.
- **DB migration sequencing** — 3 SQL файла. Ако едната падне → split deploy.

### Нисък
- **Visual** — mockup-и одобрени от Тихол. Claude Code просто пренася в PHP/JS.
- **Open-Meteo rate limit** — 10K calls/day free, нужни 1 call/tenant/day. ENI = 1 call.

---

## 7. РАБОТНА ИНФРАСТРУКТУРА

- **Droplet:** DigitalOcean Frankfurt `164.90.217.120` · `/var/www/runmystore/`
- **GitHub:** `tiholenev-tech/runmystore` (public)
- **DB:** `runmystore` (MySQL 8) · creds `/etc/runmystore/db.env`
- **Test tenant:** `tenant_id=7`
- **Beta tenant:** ENI (5 магазина, BG, RO планиран Phase B)
- **API keys:** `/etc/runmystore/api.env` (Gemini × 2, fal.ai, Groq Whisper, Open-Meteo, Stripe)

### Claude Code launch sequence

```bash
# SSH to droplet
ssh root@164.90.217.120

# tmux session
tmux new -s code1
cd /var/www/runmystore
git pull origin main

# Verify mockups present
ls P7_*.html P8_*.html P8b_*.html P8c_*.html P9_*.html P10_*.html P11_*.html

# Verify docs present
ls AI_STUDIO_LOGIC_DELTA.md DETAILED_MODE_DECISION.md SESSION_HANDOFF_CONSOLIDATED.md

# Launch Claude Code
claude
```

### Първо съобщение към Claude Code

```
Прочети следните файлове в този ред:
1. /var/www/runmystore/SHEF_RESTORE_PROMPT.md
2. /var/www/runmystore/MASTER_COMPASS.md
3. /var/www/runmystore/DESIGN_SYSTEM_v4.0_BICHROMATIC.md
4. /var/www/runmystore/docs/AI_STUDIO_LOGIC.md
5. /var/www/runmystore/AI_STUDIO_LOGIC_DELTA.md
6. /var/www/runmystore/DETAILED_MODE_DECISION.md
7. /var/www/runmystore/SESSION_HANDOFF_CONSOLIDATED.md (този файл)

Mockup файлове в /var/www/runmystore/:
- P7_recommended.html
- P8_studio_main.html, P8b_*.html × 6, P8c_studio_queue.html
- P9_print.html
- P10_lesny_mode.html (Lesny)
- P11_detailed_mode.html (Detailed)

Започни от Phase A Step 1: partials/ai-studio-modal.php (P8b).
След като приключи → Step 2.
В края на всяка стъпка commit + push.
```

---

## 8. КАКВО ШЕФ-ЧАТ ПРАВИ (boundary)

❌ **Не пиша production PHP/JS код** — само mockups.
❌ **Не commit-вам в git** — само Claude Code прави това.
❌ **Не модифицирам DB директно** — само мигриране в Claude Code.
❌ **Не правя deploy** — само Claude Code.

✅ **Какво правя:** mockups, design decisions, logic specifications, handoff документи, конфликт-разрешение с Bible.

---

## 9. SESSION SUMMARY

| Метрика | Стойност |
|---|---|
| Mockup файлове създадени/обновени | 12 |
| Нови документи | 3 (Delta + Detailed Decision + този handoff) |
| Emoji премахнати → SVG | 51+ |
| Нови екрани (без оригинален mockup) | 2 (P8c queue overlay + P11 detailed) |
| Architectural decisions финализирани | 5 (bulk safe-only · 3-screen flow · category-specific · detailed simplification · weather integration) |
| Conflicts разрешени с Bible | 4 (Life Board ordering · ai-brain-pill removal · info buttons · detailed mode scope) |
| Open questions за Claude Code | 10 |
| Критични beta launch рискове | 2 (bulk templates · Capacitor rebuild) |
| Days to beta launch | 7 |

---

## 10. ЗАЩО НЯМА ОТДЕЛЕН "ЛОГИКА" ДОКУМЕНТ

Тихол попита: *"ако документа за логика има ли нужда от"*

**Отговор:** НЕ. Всичко необходимо е разпределено в съществуващите файлове:

| Тема | Намира се в |
|---|---|
| AI Studio логика | `docs/AI_STUDIO_LOGIC.md` v1.0 + `AI_STUDIO_LOGIC_DELTA.md` (промените) |
| Detailed mode логика | `DETAILED_MODE_DECISION.md` (всичко) |
| Weather integration логика | `DETAILED_MODE_DECISION.md` § 5 + § 7 (DB schema + cron) |
| Lesny mode логика | Bible `docs/BIBLE_v3_0_TECH.md` §1.2 + `11_simple_mode_ui.md` (вече съществуват) |
| Multi-role логика | `09_multi_role_visibility.md` (вече съществува) |
| Visual + design tokens | `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` (Bible v4.1) |

Допълнителен "логика" документ би бил **дублиране на съдържание** → maintenance burden.

**Решение:** При следващи промени → update към съответния съществуващ документ + line в SESSION_HANDOFF_CONSOLIDATED.md.

---

**КРАЙ НА HANDOFF-А.**

*Approval: Тихол · 08.05.2026*
*Next: Claude Code сесия (CC) с първо съобщение от §7.*
*Replaces: `SESSION_HANDOFF_AI_STUDIO_PRINT.md` (запазва се като архив).*
