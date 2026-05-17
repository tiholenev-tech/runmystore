# 🧭 COMPASS_RUNMYWALLET v1.0 — ЖИВИЯТ ORCHESTRATOR
## Router + Tracker + Dependency Tree за RunMyWallet (sub-brand на RunMyStore.AI)

**Последна актуализация:** 17.05.2026 (S150 closing)
**Последна завършена сесия:** S150 — Phase 1 DB Schema (CC-A) + Landing Page (CC-B)
**Текуща Phase:** 1 DB ✅ → 2 Backend API (следва)
**Beta target:** Юли-Август 2026

---

## ⚠️ ВЗАИМОДЕЙСТВИЕ С RunMyStore (Закон §42)

```
RunMyWallet НЕ Е изолиран продукт.
Той е sub-brand на RunMyStore.AI и СПОДЕЛЯ:

Shared codebase:
  ✓ /var/www/runmystore/ (един server, една DB)
  ✓ Един user system (extended users table)
  ✓ Apache vhosts: runmywallet.ai + runmystore.ai
  ✓ Един Gemini/Whisper API client
  ✓ Един Stripe Connect setup

Shared tables (НЕ префиксирани):
  ✓ tenants               (с product ENUM)
  ✓ users                 (extended с wallet полета)
  ✓ profession_templates  (с translations)
  ✓ country_config        (BG active, others prepared)
  ✓ ui_translations
  
Wallet-only tables (wallet_* prefix):
  ✓ wallet_money_movements
  ✓ wallet_categories
  ✓ wallet_goals
  ✓ wallet_goal_contributions
  ✓ wallet_notifications
  ✓ wallet_ai_topics
  ✓ wallet_recurring_rules
  ✓ wallet_ai_audit_log

RMS-only tables (без prefix):
  ✓ categories (RMS product cats)
  ✓ notifications (RMS)
  ✓ products / orders / inventory / sales / deliveries
  ✓ ai_insights / ai_topics (RMS context)
```

**ПРАВИЛО:** При всяка промяна влияеща на Wallet:
1. Обнови **този** файл (COMPASS_RUNMYWALLET.md)
2. Ако промяната засяга shared tables → обнови **също MASTER_COMPASS.md**
3. Бъдни последователен — едни и същи commits в двата документа

---

## 🚀 СТАРТОВ ПРОТОКОЛ за нов Wallet chat

```
1. Прочети COMPASS_RUNMYWALLET.md (този файл)
2. Прочети DESIGN_HANDOFF.md (834 реда design system)
3. Прочети последен SESSION_S15X_WALLET_HANDOFF.md (ако има)
4. Прочети STATS_FINANCE_MODULE_BIBLE_v1.md §49 (i18n architecture)

Chat НЕ задава въпроси. Чете, казва състояние, започва работа.
```

---

## 📊 СЪСТОЯНИЕ ПО ФАЗИ

### ✅ ФАЗА 0 — Foundation (CLOSED, S148-S149)

```
Bible v1.5:
  /STATS_FINANCE_MODULE_BIBLE_v1.md (12 627 реда, v1.5)
  - §44 Voice-first audit trail
  - §49 International-Ready Architecture (i18n + tax routing)

Mockups (10 файла, 6 468 реда):
  P20_runmywallet_home.html
  P22_runmywallet_onboarding.html (7 screens)
  P23_runmywallet_records.html
  P24_runmywallet_analysis.html (4 sub-tabs)
  P25_runmywallet_goals.html
  P26_runmywallet_settings.html
  P27_runmywallet_voice_overlay.html (5 states)
  P28_runmywallet_photo_receipt.html (4 states)
  P29_runmywallet_add_goal.html
  P30_runmywallet_notifications.html
  P21_dash82_v2.html (RMS life-board integration)

i18n Foundation (1 787 реда):
  /i18n-foundation/migrations/001_i18n_schema.sql
  /i18n-foundation/lib/i18n.php
  /i18n-foundation/lib/locale.php
  /i18n-foundation/lib/bootstrap.php
  /i18n-foundation/lang/bg.json (~200 keys)
  /i18n-foundation/lang/en.json (draft)
  /i18n-foundation/tax/TaxEngine.php (BG impl + Generic fallback)
```

### ✅ ФАЗА 1 — DB Schema (CLOSED, S150)

```
CC-A (DB Schema):
  /var/www/runmystore/wallet/migrations/
    010_wallet_users_extend.sql      (apps_enabled, vat_*, plan fields)
    011_wallet_money_movements.sql   (главна таблица + is_demo + audit)
    012_wallet_categories.sql        (20 system seeds + translations)
    013_wallet_goals.sql             (savings/limit/recurring + history)
    014_wallet_notifications.sql     (6 type variants)
    015_wallet_ai_topics.sql         (10 prompts catalog)
    016_wallet_recurring_rules.sql   (auto income/expense/contributions)
    017_wallet_ai_audit_log.sql      (per-call tracking + cost_usd)
    018_seed_test_data.sql           (tenant_id=99908, user_id=100)
  /var/www/runmystore/wallet/docs/ERD.md
  /var/www/runmystore/wallet/README.md
  /var/www/runmystore/wallet/run_migrations.sh
  /var/www/runmystore/wallet/scripts/cron_cleanup_demo.sh

Deviations от brief (документирани в README):
  ✓ tenant_id=8 → 99908 (избегнат collision с Donela.bg RMS tenant)
  ✓ users.password_hash → users.password (existing column)
  ✓ Trigger → sp_cleanup_demo_movements + cron (SUPER privilege липсва)
  ✓ FK към profession_templates → guarded (still not deployed)
  ✓ tenants columns aligned с existing schema (locale → language, etc)

CC-B (Landing Page):
  /var/www/runmystore/wallet/landing/index.html (~1500 реда)
  10 sections, Sacred Glass canon, Aurora 4 blobs
  Light + Dark theme + prefers-reduced-motion
  85 KB / 8ms render
  Acceptance: DM Mono 0 matches, q1/q3/q5 dictionary aligned

Helper docs:
  WALLET_PHASE_1_DB_BRIEF_CC.md (999 реда — за CC-A)
  WALLET_LANDING_PAGE_BRIEF_CC.md (504 реда — за CC-B)
  DESIGN_HANDOFF.md (834 реда — за нов design chat)
```

### 🔜 ФАЗА 2 — Backend API (СЛЕДВА, S151-S152)

```
~25 PHP endpoints планирани:

/api/auth/signup           email + password + locale + persona seed
/api/auth/login
/api/auth/logout
/api/auth/reset
/api/auth/verify-email

/api/movements             GET list (period, category, type filters)
/api/movements             POST create (manual)
/api/movements/:id         PATCH/DELETE

/api/voice/transcribe      audio → Whisper → text + language
/api/voice/parse           text + context → {category, amount, vendor, date}

/api/photo/upload          → DigitalOcean Spaces
/api/photo/parse           image → Gemini Vision → receipt structure

/api/analysis              period aggregations (today/week/month/quarter/year)
/api/analysis/categories   donut breakdown
/api/analysis/trends       6mo sparklines
/api/analysis/tax          BGTaxEngine output

/api/goals                 GET list with type filter
/api/goals                 POST create
/api/goals/:id/contribute  add money
/api/goals/:id             PATCH/DELETE

/api/notifications         GET unread/archive
/api/notifications/:id     PATCH (mark read)

/api/waitlist              POST email + profession (за landing)

Pattern:
  - PDO + parse_ini_file (/etc/runmystore/db.env)
  - Bootstrap helper: bootstrapTenant($pdo, $tenant_id)
  - i18n: t() + Locale::priceFormat() в response payloads
  - Audit trail: retrieved_facts JSON в money_movements
```

### ФАЗА 3 — AI Integration (S152-S153)
### ФАЗА 4 — Frontend Wiring (S153-S155)
### ФАЗА 5 — Mobile Capacitor (S155-S156)
### ФАЗА 6 — Payments / Stripe (S156-S157)
### ФАЗА 7 — Beta + Launch (S157-S158)

---

## 🗂️ DB SCHEMA OVERVIEW

```
SHARED (между RMS и Wallet):
  tenants                      → products: 'store' | 'wallet'
  users                        → apps_enabled JSON: ["rms"]/["wallet"]/["rms","wallet"]
  profession_templates         → translations JSON
  country_config               → BG active, 8 prepared
  ui_translations              → override per tenant/locale

WALLET-ONLY (wallet_* prefix):
  wallet_money_movements       ← главна (audit trail + is_demo)
  wallet_categories            ← 20 seeded + translations
  wallet_goals                 ← + wallet_goal_contributions
  wallet_notifications         ← 6 type variants
  wallet_ai_topics             ← prompts catalog
  wallet_recurring_rules
  wallet_ai_audit_log          ← AI cost tracking
```

---

## 👤 USER PERSONAS

```
Пешо (RMS staff seller):
  apps_enabled = ["rms"]
  rms_plan = "free" (tenant плаща)
  wallet_plan = NULL (няма достъп)

Митко (RMS owner + Wallet user):
  apps_enabled = ["rms", "wallet"]
  rms_plan = "pro_49" (€49/мес)
  wallet_plan = "free" (Бонус за PRO RMS users)

ENI (Wallet only — самонает):
  apps_enabled = ["wallet"]
  rms_plan = NULL
  wallet_plan = "start" (€4.99/мес)

Стефан (test):
  user_id = 100
  tenant_id = 99908
  apps_enabled = ["wallet"]
  password = "test123" (bcrypt: $2b$10$duOCSfYfXw7KG7Lh9LS/N.eqqmecEahVXy1PavlGBynd/0U3EYtka)
```

---

## 💰 PRICING

```
FREE (€0/мес):
  ✓ До 20 записа/месец
  ✓ Само ручен вход
  ✓ 1 цел
  ✗ Без AI

START (€4.99/мес, 14 дни trial):
  ✓ Неограничени записи
  ✓ Voice + Photo + AI
  ✓ Прогнозен данък + ДДС праг
  ✓ Неограничени цели

PRO (€9.99/мес, скоро):
  ✓ Multi-business splits
  ✓ Auto-bank sync
  ✓ Експорт за счетоводител

Per-country pricing (виж Bible §49.11):
  US/UK = +50% premium
  EM markets = PPP-adjusted
```

---

## 🤖 AI ARCHITECTURE

```
Voice STT:
  OpenAI Whisper API ($0.006/min)
  Auto-detect language hint = tenant.locale
  Returns: text + detected_language

Parse / classify:
  Gemini 2.5 Flash ($0.0015/img / $0.000125-0.000375 per 1k tokens)
  Locale-aware prompts (ai_topics table)
  PromptBuilder::build($topic_code, $context, $locale)

Photo OCR:
  Gemini 2.5 Flash Vision (auto-detects 100+ languages)
  Extracts: vendor, amount, items, currency, date

Confidence routing (Bible §44 ЗАКОН №8):
  >0.85 → auto-accept (saved directly)
  0.50-0.85 → confirm card shown
  <0.50 → block + ask manual entry

Audit trail (Bible §44 ЗАКОН №7):
  Всеки movement → retrieved_facts JSON
  Всеки AI call → wallet_ai_audit_log row с cost_usd
```

---

## 🌍 i18n & TAX

```
Day 1: BG active
Day 1: Locale = bg-BG, Currency = EUR, Timezone = Europe/Sofia
Day 1: BGTaxEngine (НПР 25/40/60%, ДДС €51 130, осигуровки €551-€1918 / 27.8%)

Phase 2 (Oct 2026): RO, GR active
Phase 3 (Jan 2027): US, UK active (GenericTaxEngine)
Phase 4 (Apr 2027): DE, ES, IT, FR

VAT registration:
  Onboarding question: "По ДДС регистриран ли си?"
  Ако НЕ → cron daily checks 70% threshold → notification.type='alert'
  Ако ДА → 20% VAT auto-tracking + декларация reminders
  Cross-over flow: auto upgrade на settings UI
```

---

## 📂 FILE STRUCTURE

```
/var/www/runmystore/                  ← shared codebase
  wallet/
    migrations/*.sql                  ← 8+ idempotent migrations
    docs/ERD.md
    scripts/cron_cleanup_demo.sh
    landing/index.html                ← public marketing site
    [pages/*.php]                     ← TODO Phase 4
    [api/*.php]                       ← TODO Phase 2
    [lib/*.php]                       ← TODO Phase 2-3
    README.md
    run_migrations.sh
  
  i18n-foundation/                    ← shared library
    lib/i18n.php
    lib/locale.php
    lib/bootstrap.php
    lang/*.json
    tax/TaxEngine.php
  
  mockups/                            ← shared design references
    P15_simple_FINAL.html             ← supreme reference
    P20-P30_runmywallet_*.html        ← Wallet mockups (S149)
    
  STATS_FINANCE_MODULE_BIBLE_v1.md    ← v1.5
  COMPASS_RUNMYWALLET.md              ← този файл
  MASTER_COMPASS.md                   ← RMS compass (shared updates)
  DESIGN_HANDOFF.md                   ← design system guide
  DESIGN_SYSTEM_v4.0_BICHROMATIC.md   ← canonical (RMS + Wallet)
```

---

## 🔗 GITHUB REFERENCES

```
Repo:    tiholenev-tech/runmystore
Branch:  main

Mockups:        /mockups/P*_runmywallet_*.html
i18n:           /i18n-foundation/
Bible:          /STATS_FINANCE_MODULE_BIBLE_v1.md
Landing:        /wallet/landing/ (after CC-B commit)
Migrations:     /wallet/migrations/ (after CC-A commit)

Last commits S148-S150:
  c322ab4 S149: P26 Settings + P28 Photo Receipt
  0504cff S149: P27 RunMyWallet Voice Overlay
  830b769 S149 ENGINE: Shared scan component
  9bb4d96 S149: P29 fix — labels under icon cells
  b726302 S149: P29 Add Goal + P30 Notifications
  2de8425 S149: P25 RunMyWallet Goals page
  8dfe446 S149: RunMyWallet mockups final batch
  e8bc109 S149: i18n foundation + Bible v1.5 §49
  1af130d S148: Bible v1.4 — REBRAND Pocket CFO → RunMyWallet
```

---

## 🎯 SUCCESS CRITERIA per phase

```
Phase 1 (S150):  ✅ DB готов, идемпотентен test pass x2
Phase 2 (S151-2): 25 API endpoints, integration tests
Phase 3 (S152-3): AI < 2s response, confidence > 85% за 80% от calls
Phase 4 (S153-5): 10 pages functional, mobile responsive
Phase 5 (S155-6): APK signed, тестван на Z Flip6 + 1 iPhone
Phase 6 (S156-7): Stripe live mode, 1 paying test customer
Phase 7 (S157-8): 10 beta users, NPS > 50, 0 trust-killing bugs
```

---

**END OF COMPASS v1.0** — обновявай при всяка сесия която засяга Wallet.
