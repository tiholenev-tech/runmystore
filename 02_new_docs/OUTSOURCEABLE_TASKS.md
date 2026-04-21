# 🔒 OUTSOURCEABLE_TASKS — Безопасни задачи за external dev / AI

## Какво може да се outsource-ва без риск за core IP

**Версия:** 1.0  
**Дата:** 21.04.2026  
**Аудитория:** Тихол (вътрешен документ) + templates за external brief  
**Принцип:** Всичко което е **external integration**, **boilerplate** или **hardware protocol** = безопасно. Всичко което е **logic**, **UX philosophy**, **AI behavior** или **data model** = остава вътрешно.

---

## 📑 СЪДЪРЖАНИЕ

1. Защо този документ
2. Какво Е core IP (НЕ се outsource-ва)
3. Какво НЕ Е core IP (МОЖЕ да се outsource-ва)
4. 20-те безопасни задачи (с приоритет)
5. Template за external task brief
6. Правила за external collaborator
7. Checklist преди изпращане на задача
8. Как да защитиш IP при комуникация

---

# 1. ЗАЩО ТОЗИ ДОКУМЕНТ

Като solo founder на комплексен SaaS, не можеш да правиш всичко сам. Някои части са пасивни — `третостранни API интеграции`, `hardware протоколи`, `boilerplate код`. Тези могат да се делегират на:

- External freelancer developer (Upwork, BGDev)
- Друг AI (ChatGPT, Gemini, Claude в отделен chat с limited контекст)
- Junior dev в България

**Рискът не е техническият, а IP-то:**
- Ако дадеш на external дев целия BIBLE → той разбира бизнес модела → може да копира
- Ако дадеш само "направи Stripe Connect интеграция по Stripe docs" → той получава стандартна задача без IP експозиция

**Целта:** Outsource 30-40% от обема работа при 0% IP експозиция.

---

# 2. 🚫 КАКВО Е CORE IP (НЕ СЕ OUTSOURCE-ВА)

**Никога не давай на external следните:**

| Област | Защо е IP |
|---|---|
| **5-те закона** | Cornerstone на product philosophy |
| **"Пешо" persona + voice-first UX** | Differentiator vs. конкуренти |
| **AI operational layer** (AI-Гид + AI-Мозък + $MODULE_ACTIONS) | Архитектурата която прави AI евтин и надежден |
| **6-те фундаментални въпроса** | Unique business intelligence framework |
| **Confidence model** (hidden inventory) | Anti-friction onboarding innovation |
| **Lost demand AI fuzzy matching** | Central differentiator |
| **Selection Engine** (MMR + diversity) | Life Board secret sauce |
| **Trust Decay + AI Safety 6 нива** | Anti-churn механизъм |
| **857 AI topics catalog** | 6 месеца работа, уникален asset |
| **Pills & Signals 3-слойна архитектура** | Performance + AI cost reduction |
| **Wizard 4-стъпково прогресивно разкриване** | Signature UX |
| **Simple Mode = life-board.php** (не 4 бутона) | Signature UX |
| **biz_learned_data** (cross-tenant AI learning) | Long-term moat |
| **Business modela** (planove, trial 4 месеца, партньори FLAT ISR) | Commercial strategy |
| **STRIPE_CONNECT ledger-first философия** | Partner model innovation |

**Правило:** Ако някой external разгледа тези и каже "мога да направя собствен такъв" — ти си изложен.

---

# 3. ✅ КАКВО НЕ Е CORE IP

**Тези са стандартни интеграции или technical boilerplate. Всеки dev може да ги направи. Не разкриват нищо за твоя бизнес.**

- Third-party API wrappers (Stripe, WooCommerce, Shopify, Econt, Speedy, Twilio, Firebase)
- Hardware protocols (Bluetooth TSPL, ESC/POS)
- Standard libraries usage (PDF generation, barcode scanning, CSV parsing)
- Infrastructure ops (backups, monitoring, logging)
- OAuth boilerplate (Google Login, Facebook Login)
- Deployment scripts
- Unit tests за isolated modules
- Database migrations (schema само, не семантика)
- Email templates (transactional)
- Static pages (landing, legal, about)

---

# 4. 📋 20-ТЕ БЕЗОПАСНИ ЗАДАЧИ (ПО ПРИОРИТЕТ)

## Phase B priority (S88-S92) — мащабни

### 1. 🔴 Stripe Connect integration (4-5 days)
- **Scope:** Setup Stripe Connect (Separate Charges + Transfers, не Destination)
- **Input:** Stripe docs + STRIPE_CONNECT_AUTOMATION.md (само секции 3-7, БЕЗ секция 1-2 защото те разкриват партньор модела)
- **Output:** PHP класове за payment flow + webhooks + retry logic + test suite
- **Acceptance:** 10 test transactions end-to-end, 3 failure scenarios handled (card declined, refund, chargeback)
- **Защо безопасно:** Stripe Connect е публичен API pattern. Implementation-а не разкрива партньор бизнес модел.

### 2. 🔴 WooCommerce integration (3-4 days)
- **Scope:** Product sync, order webhook, stock sync — и двете посоки
- **Input:** WooCommerce REST API docs + кратък brief (виж template §5)
- **Output:** PHP client class `WooCommerceChannel` + webhook handler + cron worker
- **Acceptance:** End-to-end тест с WooCommerce docker image
- **Защо безопасно:** Standard e-commerce интеграция.

### 3. 🔴 Shopify integration (3-4 days)
- **Scope:** Custom App approach (не Public App) — OAuth с access token setup
- **Input:** Shopify Admin API docs
- **Output:** PHP client + webhook HMAC verification + sync queue
- **Acceptance:** End-to-end тест с Shopify development store
- **Защо безопасно:** Standard.

### 4. 🟡 Econt + Speedy API интеграция (2-3 days)
- **Scope:** Print shipping labels, track shipments, calculate cost
- **Input:** Econt API docs, Speedy API docs
- **Output:** `EcontClient` + `SpeedyClient` PHP класове
- **Acceptance:** Тест от автора с реални credentials (sandbox)
- **Защо безопасно:** Логистична интеграция, не разкрива продуктова логика.

### 5. 🟡 Bluetooth thermal printer (TSPL + ESC/POS) (2-3 days)
- **Scope:** Web Bluetooth API wrapper за DTM-5811 (TSPL) + fallback за ESC/POS
- **Input:** TSPL spec, DTM-5811 manual, ESC/POS docs
- **Output:** JS модул `bluetoothPrinter.js` с `connect()`, `printLabel()`, `printReceipt()`
- **Acceptance:** Отпечатана тестова етикетка 50×30mm с кирилица на реален принтер
- **Защо безопасно:** Hardware protocol, публичен.

### 6. 🟡 OCR за фактури (Gemini Vision wrapper) (2 days)
- **Scope:** Image → structured invoice data (supplier, items, amounts)
- **Input:** Gemini Vision API docs + 20 sample invoices
- **Output:** PHP `InvoiceOCR` class + confidence routing (AUTO_ACCEPT >92%, UI 75-92%, REJECT <75%)
- **Acceptance:** 92%+ accuracy на 20-те samples
- **Защо безопасно:** Third-party wrapper, не разкрива как се ползват резултатите.

## Phase C-D priority (S100+) — средно-мащабни

### 7. 🟢 PDF invoice generation (1-2 days)
- **Scope:** Generate PDF invoice с ДДС, Econt data, company info
- **Input:** Template requirements + mPDF или TCPDF library
- **Output:** `InvoiceGenerator` PHP class
- **Acceptance:** 3 PDF-а generated (retail, wholesale, online)
- **Защо безопасно:** Library usage.

### 8. 🟢 Push notifications (Firebase) (1-2 days)
- **Scope:** FCM setup + send notification + token management
- **Input:** Firebase docs + Capacitor integration guide
- **Output:** PHP `FirebasePush` class + iOS/Android tokens table
- **Acceptance:** Notification доставена на реален телефон (iOS + Android)
- **Защо безопасно:** Boilerplate.

### 9. 🟢 SMS integration (Twilio) (1 day)
- **Scope:** Send SMS + verify numbers
- **Input:** Twilio PHP SDK docs
- **Output:** `SMSSender` class
- **Acceptance:** SMS доставен на реален номер
- **Защо безопасно:** Boilerplate.

### 10. 🟢 Barcode scanning (ZXing или QuaggaJS) (1-2 days)
- **Scope:** Camera-based barcode scanner за web/mobile
- **Input:** Library docs + UI wireframes (простичко)
- **Output:** JS компонент `barcodeScanner.js` с callback `onScan(code)`
- **Acceptance:** 95%+ scan rate на 20 тестови barcode-а
- **Защо безопасно:** Library integration.

### 11. 🟢 Google OAuth / Apple Sign-In (1 day)
- **Scope:** Sign-In with Google + Apple
- **Input:** Google/Apple docs
- **Output:** Auth flow endpoints + token exchange
- **Acceptance:** Successful login от 3 тест акаунта
- **Защо безопасно:** OAuth boilerplate.

### 12. 🟢 Capacitor mobile build pipeline (2-3 days)
- **Scope:** iOS + Android build за App Store / Play Store
- **Input:** Capacitor docs + existing web app URL
- **Output:** .ipa + .apk files + CI/CD скрипт
- **Acceptance:** App installable на тест устройство
- **Защо безопасно:** Packaging, не разкрива app логика.

### 13. 🟢 Weather API wrapper (Open-Meteo) (0.5 day)
- **Scope:** Wrapper за Open-Meteo с local caching
- **Input:** API docs + DB schema за `weather_forecast` таблица
- **Output:** `WeatherService` PHP class
- **Acceptance:** Cached forecast за 5 стора за 7 дни
- **Защо безопасно:** Third-party API.

### 14. 🟢 fal.ai image processing wrappers (0.5 day)
- **Scope:** birefnet (background removal) + nano-banana-pro (try-on)
- **Input:** fal.ai docs
- **Output:** `FalClient` PHP class
- **Acceptance:** 5 тестови изображения processed
- **Защо безопасно:** Third-party wrapper.

### 15. 🟢 Image upload + compression (0.5 day)
- **Scope:** Upload image → resize (max 1200px) → compress (JPEG 85%) → save
- **Input:** GD или Imagick docs
- **Output:** `ImageUploader` PHP class
- **Acceptance:** 10 image-а processed, all < 300KB
- **Защо безопасно:** Boilerplate.

### 16. 🟢 CSV export/import library (1 day)
- **Scope:** Generic CSV reader/writer с UTF-8 BOM handling, delimiter detection, validation
- **Input:** League\Csv library docs
- **Output:** `CSVService` PHP class
- **Acceptance:** 3 тестови CSV (Excel export, comma-separated, tab-separated)
- **Защо безопасно:** Library usage, generic.

### 17. 🟢 VAT calculation library (0.5 day)
- **Scope:** Calculate VAT за BG (20%), EU countries (различни ставки)
- **Input:** EU VAT rates + BG specifics
- **Output:** `VATCalculator` PHP class
- **Acceptance:** Test suite с 10 countries
- **Защо безопасно:** Pure math, publicly documented.

### 18. 🟢 Backup scripts (0.5 day)
- **Scope:** Automated mysqldump + upload to S3/B2 + retention policy
- **Input:** Cron + mysqldump + AWS CLI
- **Output:** Bash scripts + cron config
- **Acceptance:** 7 days of backups in S3, verified restore test
- **Защо безопасно:** Ops boilerplate.

### 19. 🟢 Health check + monitoring (1 day)
- **Scope:** Endpoints `/health` + Uptime monitor setup (UptimeRobot/Healthchecks.io)
- **Input:** Standard patterns
- **Output:** Health endpoint + monitor config
- **Acceptance:** Alert triggered при downtime
- **Защо безопасно:** Ops.

### 20. 🟢 Email templates (transactional) (1 day)
- **Scope:** 10 transactional email-а (welcome, password reset, invoice, etc.)
- **Input:** Copy text + Tailwind-based HTML email templates
- **Output:** HTML + txt versions
- **Acceptance:** Всички renders на Gmail, Outlook, Apple Mail
- **Защо безопасно:** Content + design, не логика.

---

# 5. TEMPLATE ЗА EXTERNAL TASK BRIEF

**Използвай този template когато пращаш задача. Никога не изпращай BIBLE-тата или цели DOC-ове.**

```markdown
# Task: [NAME]

## Context (2-3 sentences, no IP)
Работим върху SaaS платформа за малки магазини. Нуждаем се от [specific third-party integration / boilerplate feature].
Технологичен стек: PHP 8+, MySQL 8+, vanilla JS frontend.

## Deliverable
[Pure technical specification без бизнес контекст]

Пример за Stripe Connect:
- PHP class `StripeConnectClient` with methods: createAccount(), createPayment(), transferToPartner(), getBalance()
- Webhook handler за events: account.updated, payment_intent.succeeded, transfer.created
- PHPUnit tests (минимум 20 test cases)
- README с setup instructions

## Constraints
- PHP 8.0+ compatible
- No framework (vanilla PHP with minimal dependencies)
- PSR-12 code style
- All strings in UTF-8
- Error handling: exceptions, not silent failures
- Logging: PSR-3 compatible

## Testing
- Stripe test mode credentials provided separately
- Expected: pass all tests on CI

## Timeline
[X days]

## Deliverables
- GitHub repo / ZIP с source code
- README.md
- Tests
- Short demo video (optional, 2-3 min)
```

**Никога не казвай:**
- "Приложение за малки магазини в България с voice-first UX"
- "Имаме 'Пешо' персона"
- "AI управител мониторираща бизнеса"
- "Фундаментални 6 въпроса"
- "Life Board алгоритъм"

**Казвай:**
- "B2B SaaS"
- "Mobile-first web app"
- "Transaction processing system"

---

# 6. ПРАВИЛА ЗА EXTERNAL COLLABORATOR

Преди да дадеш задача, изисквай:

1. **NDA подписан** — стандартен template от адвокат, 15 мин работа
2. **Ограничен GitHub достъп** — нов private repo само за тази задача, не целия runmystore
3. **Sandbox credentials** — Stripe test, WooCommerce staging, Shopify dev store
4. **Code review задължителен** — ти четеш всеки PR преди merge
5. **Ownership clause** — в договора: IP ownership = ти от момента на плащане
6. **No reuse clause** — не може да ползва кода за друг проект

**За AI collaborator (ChatGPT/Gemini/Claude в отделен chat):**
- Няма NDA (няма лице)
- Използвай new chat (без история)
- Не давай production API keys
- Не давай DB passwords
- Не давай real tenant data
- При съмнение → test с fake данни

---

# 7. CHECKLIST ПРЕДИ ИЗПРАЩАНЕ НА ЗАДАЧА

Преди да натиснеш "Send" или да дадеш brief:

- [ ] Прочетох задачата — разкрива ли Пешо persona? **НЕ трябва.**
- [ ] Разкрива ли AI philosophy (voice-first, 5 закона)? **НЕ трябва.**
- [ ] Разкрива ли бизнес модела (trial, партньори)? **НЕ трябва.**
- [ ] Разкрива ли database tables специфични за нашия продукт (life_board, ai_insights)? **НЕ трябва.**
- [ ] Задачата е self-contained? Може ли да се направи без цел допълнителен контекст? **ДА трябва.**
- [ ] Deliverable е технически специфичен, не бизнес? **ДА трябва.**
- [ ] Test criteria са обективни (passes/fails, не "прилича на Пешо")? **ДА трябва.**

Ако някое НЕ трябва → преработи brief-а преди изпращане.

---

# 8. КАК ДА ЗАЩИТИШ IP ПРИ КОМУНИКАЦИЯ

## Имена на продукта
- **Вътрешно:** RunMyStore.ai
- **Външно (за dev):** "Internal SaaS project" или "POS/Inventory system"

## Имена на персони
- **Вътрешно:** Пешо, Ени
- **Външно:** "merchant", "user", "store owner"

## Feature наименования
- **Вътрешно:** Life Board, Pills, Signals, 6-те въпроса, AI-Гид, AI-Мозък
- **Външно:** "dashboard", "notifications", "metrics", "integration layer"

## DB имена
- **Безопасно да разкриеш:** sales, products, inventory, users, tenants, stores, suppliers, categories
- **Опасно да разкриеш:** ai_insights, ai_shown, ai_audit_log, tenant_ai_memory, life_board_entries, selection_engine_state, biz_learned_data

Когато пращаш DB schema за outsourceable задача → export само таблиците които му трябват, не целия schema.

## Github повторно
- **Main repo:** `tiholenev-tech/runmystore` — **private** или **public** според current state, но external dev НЕ получава достъп
- **Outsource repo:** `tiholenev-tech/[integration-name]` — private, минимален README, само необходими файлове

Когато задачата е готова → ти сам мерджваш в main repo (и refactor-ваш ако трябва да intergrates с core logic).

---

# 9. ПРИМЕРЕН SCENARIO: WooCommerce integration

**Подходящ външен колаборатор:** mid-level PHP dev (~$30-50/час) или AI (ChatGPT-5 pro в нов chat)

**Time estimate:** 20-25 hours = 3-4 работни дни

**Brief който му пращаш:**

```markdown
# Task: WooCommerce Integration Client

## Context
B2B SaaS project needs a WooCommerce integration client for product/order/inventory sync.
Stack: PHP 8.1+, MySQL 8+, vanilla (no framework).

## Deliverable
PHP package containing:

1. `WooCommerceClient` class with methods:
   - `syncProduct(array $product): int` — creates or updates, returns WC product ID
   - `updateStock(int $wcProductId, int $quantity): bool`
   - `fetchOrders(DateTime $since): array`
   - `deleteProduct(int $wcProductId): bool`

2. Webhook handler at `/webhook/woocommerce.php`:
   - Validates HMAC signature (X-WC-Webhook-Signature)
   - Parses `order.created` events
   - Calls provided callback function

3. Sync queue worker (`worker.php`):
   - Reads from table `ecommerce_sync_queue` (schema provided)
   - Processes pending sync tasks with exponential backoff
   - Updates status: pending → succeeded / failed

4. PHPUnit tests (20+ test cases)

5. README with setup instructions

## Constraints
- PHP 8.1+
- No Laravel/Symfony — vanilla PHP
- PSR-12 code style
- PSR-3 logging (Monolog for testing)
- All strings UTF-8

## Testing
- Test credentials for staging WooCommerce: provided separately
- CI: pass all tests on GitHub Actions

## DB Schema (provided, subset)
[Only ecommerce_channels + ecommerce_sync_queue + relevant columns from products + sales]

## Timeline
3-4 working days

## Deliverable format
Private GitHub repo + README
```

**Какво НЕ е в този brief:**
- Никакво упоменаване на RunMyStore
- Никакво упоменаване на Пешо
- Никакво AI behavior
- Никакви 5 закона
- Никаква life-board, pills, signals

**Devа получава:** generic интеграционна задача, 99% идентична на такава за всеки e-commerce SaaS.

---

# 10. РЕЗЮМЕ

**Безопасно за outsource: ~30% от total работа**

| Задача | Време | Спестено време за теб |
|---|---|---|
| Stripe Connect | 4-5 дни | 4-5 дни |
| WooCommerce | 3-4 дни | 3-4 дни |
| Shopify | 3-4 дни | 3-4 дни |
| Econt + Speedy | 2-3 дни | 2-3 дни |
| Bluetooth printer | 2-3 дни | 2-3 дни |
| OCR wrapper | 2 дни | 2 дни |
| PDF invoice | 1-2 дни | 1-2 дни |
| Push (Firebase) | 1-2 дни | 1-2 дни |
| SMS (Twilio) | 1 ден | 1 ден |
| Barcode scanning | 1-2 дни | 1-2 дни |
| Google/Apple OAuth | 1 ден | 1 ден |
| Capacitor build | 2-3 дни | 2-3 дни |
| Weather wrapper | 0.5 ден | 0.5 ден |
| fal.ai wrappers | 0.5 ден | 0.5 ден |
| Image compression | 0.5 ден | 0.5 ден |
| CSV service | 1 ден | 1 ден |
| VAT calculator | 0.5 ден | 0.5 ден |
| Backup scripts | 0.5 ден | 0.5 ден |
| Health monitoring | 1 ден | 1 ден |
| Email templates | 1 ден | 1 ден |
| **ОБЩО** | **~30-35 дни** | **~30-35 дни работа спестена** |

**При $30-50/час = $7,000-14,000 external cost.**

---

# 11. КОГАТО НЯМАШ CASH — AI КАТО COLLABORATOR

Ако нямаш бюджет за external dev → AI (ChatGPT Pro, Gemini, Claude) може да направи горните задачи за 10-20% от времето.

**Workflow:**
1. Отваряш нов chat (празен контекст)
2. Paste-ваш brief-а от §5 template
3. Iterate 2-3 пъти за refinement
4. Copy paste-ваш резултата в собствен частен repo
5. Ти ревю-ваш и integrates в main runmystore repo

**AI е даже по-безопасен от human** — не помни разговора ако не му подскажеш, не може да "открадне" идеята защото не е организация.

---

# 12. КАКВО НЕ ДАВАШ НА AI (ДАЖЕ В НОВ CHAT)

- API keys (никога)
- Database credentials (никога)
- Production customer data (никога)
- Пълен BIBLE (никога)
- Списък с tenants (никога)

Ако AI попита "какво е контекста" → отговаряш "B2B SaaS, стандартна интеграционна задача".

---

**КРАЙ НА OUTSOURCEABLE_TASKS.md**
