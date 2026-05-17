# PREBETA MASTER v2.1 — жив документ от 11.05.2026 (обновен 12.05.2026 от S141)

**Обновява:** ВСЕКИ шеф-чат в края на сесията
**Заменя:** PRIORITY_TODAY.md, STATE_OF_THE_PROJECT.md (остаряха)
**Бета няма дата.** Тихол е бетата. Когато реши — ще е.

---

# ЧАСТ 0 — СТАРТОВ ПРОМПТ

Копирай и paste-вай на ВСЕКИ нов шеф-чат:

```
Ти си шеф-чат за RunMyStore.AI пред-бета.

Прочети PREBETA_MASTER.md от project knowledge — там е ЦЕЛИЯТ план,
правила, макети, технически контекст и текущ статус.

Правила:
- Говори САМО на български. Технически термини само за файлове и команди.
- Максимална краткост. Без "може би", без дълги обяснения.
- НИКОГА не измисляй дизайн — копирай 1:1 от макетите в mockups/.
- Давай Python скриптове за paste на дроплет.
- 60% плюсове + 40% честна критика.
- Питай за UX/логика, решавай сам за технически неща.

Какво да направиш:
1. Прочети PREBETA_MASTER.md
2. Отговори на IQ ТЕСТА (ЧАСТ 1) — без него не започвай работа
3. Намери ТЕКУЩАТА ЗАДАЧА (първата ⏳ в ЧАСТ 5)
4. Прочети макета за нея от mockups/
5. Кажи какво ще правиш (2-3 изречения) и чакай одобрение
6. В края: изпълни ПРОТОКОЛ ЗА КРАЙ НА СЕСИЯ (ЧАСТ 8)

ЗАПОЧНИ С ТЕСТА.
```

---

# ЧАСТ 1 — IQ ТЕСТ (задължителен при старт)

Отговори ПРЕДИ да започнеш работа. Ако сгрешиш 2+ от 6 — Тихол затваря чата.

1. С какъв хедър трябва да е deliveries.php — Тип А или Тип Б? Защо?
2. Мога ли да ползвам Claude Code за редизайн на products.php? Защо?
3. Кога е бетата?
4. Seller (продавач) вижда ли бутон Лесен/Разширен? Защо?
5. Как се чете файл от GitHub в sandbox среда?
6. Каква е текущата задача (първата ⏳)?

**Верни отговори:**
1. Тип Б (опростен: ← Заглавие + тема + ПРОДАЖБА). Тип А е САМО за chat.php и life-board.php.
2. НЕ. CC е само за DB миграции, нови скелети 500+ реда, тежки рефакторинги. Дизайн = Опус + Python скриптове.
3. Няма дата. Тихол е бетата. Когато реши.
4. НЕ. Seller е винаги в лесен режим и НЕ може да превключи. Бутонът се скрива — не показвай бутон който не прави нищо.
5. github.com blob URL + парсване на rawLines JSON. raw.githubusercontent.com и api.github.com са БЛОКИРАНИ. Само bash_tool с curl/python3, НЕ web_fetch.
6. (Чете се от ЧАСТ 5 — първата ⏳ задача)

---

# ЧАСТ 2 — СВЕЩЕНИ ПРАВИЛА ЗА ДИЗАЙН

## Правило №1 — ДИЗАЙНЪТ Е В МАКЕТИТЕ

НИКОГА не измисляй дизайн. НИКОГА не „подобрявай" визията.
Преди да пипнеш CSS или HTML — ПРОЧЕТИ макета.
Ако макет не съществува — СПРИ и питай Тихол.
Копирай 1:1 от макета. Ако нещо изглежда грешно — питай, не поправяй.

## Правило №2 — ДВАТА ТИПА ХЕДЪР

**Тип А — Начална страница (САМО chat.php и life-board.php):**
```
[ RunMyStore.ai ] [ PRO ] [ spacer ] [ 🖨 ] [ ⚙ ] [ ⤴ ] [ ☀ ]
```
Еталон: mockups/P11_chat_v7_orbs2.html

**Тип Б — Вътрешна страница (ВСИЧКИ останали модули):**
```
[ ← назад ] [ Заглавие ] [ ☀ тема ] [ 🛒 ПРОДАЖБА ]
```
Еталон: mockups/P14b_deliveries_detailed_v5_BG.html

## Правило №3 — ПОДЛЕНТА (subbar) НАВСЯКЪДЕ

```
[ 🏠 ENI ˅ ]   [ КЪДЕ СМЕ ]   [ ← Лесен / Разширен → ]
```
- Ако хедърът ВЕЧЕ има бутон ПРОДАЖБА — подлентата НЕ добавя втори.
- При seller: toggle бутонът Лесен/Разширен се СКРИВА.

## Правило №4 — ДОЛНА НАВИГАЦИЯ

4 орб таба: AI / Склад / Справки / Продажба
Еталон за орбите: mockups/P11_chat_v7_orbs2.html
САМО в разширен режим. В лесен — НЯМА.

## Правило №5 — ДВАТА РЕЖИМА (seller vs owner)

| Роля | Режим | Превключване | Toggle видим |
|---|---|---|---|
| Продавач (seller) | ЛЕСЕН винаги | НЕ може | СКРИТ |
| Собственик (owner) | Може и двата | Бутон Лесен ↔ Разширен | ВИДИМ |
| Мениджър (manager) | Може и двата | Бутон Лесен ↔ Разширен | ВИДИМ |

Контракт: $_SESSION['mode'] = 'simple' или unset (= разширен).
Seller → принудително 'simple', без toggle бутон.

## Правило №6 — НИКОГА

- Никога hardcoded BGN/лв/€ — винаги priceFormat($amount, $tenant)
- Никога hardcoded БГ текст — всичко през tenant.lang
- Никога "Gemini" в UI — само "AI"
- Никога emoji в UI — само SVG
- Никога sed за file edits — само Python скриптове
- Никога ADD COLUMN IF NOT EXISTS (MySQL не поддържа)
- Никога inline hue variables — само класове (q1-q6, qd, qm)
- Никога $pdo директно — DB::get(), DB::run(), DB::tx()
- Никога fmtMoney() — правилният helper е priceFormat($amount, $tenant)
- Montserrat единствен font
- max-width 480px в production

## Правило №7 — GLASS NEON BORDERS (СВЕЩЕНИ)

.shine + .glow + .glow-bright spans с conic-gradient + mask-composite.
overflow:hidden на glass карта = СМЪРТ (изрязва spans).
НИКОГА не се заменят с по-прост border.
oklch палитра за light тема (НЕ color inversion).

## Правило №8 — КАК СЕ РАБОТИ

**Опус (шеф-чат) = 90% от работата:**
- Малки и средни промени (до ~500 реда)
- Дизайн/визия (CSS, HTML)
- Логически решения
- Python скриптове за paste на дроплет

**Claude Code = САМО за:**
- DB миграции (SQL)
- Нови модули от нулата (скелет 500+ реда)
- Тежки рефакторинги на logic (15K+ реда)

**Claude Code НЕ се ползва за:**
- Дизайн/визия
- Малки поправки (< 200 реда)
- Хедър/навигация промени

## Правило №9 — БЕКЪП ВИНАГИ

```bash
cp -p file.php file.php.bak.$(date +%s)
```
Преди ВСЯКА промяна на production файл. Без изключения.

---



## Правило №10 — ГЛОБАЛЕН "ИНВЕНТАРИЗАЦИЯ NUDGE" (S141, 12.05.2026)

На ВСЕКИ модул (products, sale, deliveries, orders, transfers, inventory, chat, life-board) — persistent pill горе под хедъра:

```
⏳ N артикула не са броени · D дни →
```

- Tap → отваря inventory.php zone walk
- "По-късно" → скрива за 7 дни
- Simple Mode → миниатюрно "⚠ N неща за нагласяне →"
- Detailed Mode → с детайли (% точност + последна дата)

**Защо:** Inventory accuracy = foundation за всички AI сигнали. Без свежа броячка → AI казва "имам 5 бр Adidas 42" а реално 0 → грешни препоръки.

## Правило №11 — "ЗДРАВЕ" → "СЪСТОЯНИЕ НА СКЛАДА" (S141, 12.05.2026)

**Стар вид:** Плосък % "82%" — твърде абстрактен.

**Нов вид:** Breakdown по конкретни метрики:

```
СЪСТОЯНИЕ НА СКЛАДА
├─ Снимки           78%  (12 без)         →
├─ Цени едро        91%  (5 без)          →
├─ Броено < 30 дни  34%  (165 застояли)   →
├─ Доставчик        100% (всички)         ✓
└─ Категория        88%  (7 без)          →
```

- Всеки ред — tap → отива в filtered list
- Reds → жълти → зелени visual coding
- Общ % е сума, но показваме компонентите

**Защо:** Owner иска да знае КАКВО точно липсва, не общ score.

## Правило №12 — РЕДИЗАЙН СТРАТЕГИЯ: SWAP > INJECT (S141, 12.05.2026)

За модули **>5000 реда** (products.php = 14K, sale.php = 8K, и т.н.):

1. Създай нов файл `<module>-v2.php` (от 0, с inline CSS pattern от chat.php)
2. Тествай паралелно с production
3. SWAP в края: `git mv` rename

**НЕ INJECT-ONLY** (S140 plan казваше) — той се проваля защото CSS conflict с продуктувно CSS.

**Документация:** `docs/MODULE_REDESIGN_PLAYBOOK_v1.md` (461 реда — задължително четене за всеки чат прави модул редизайн).

## Правило №13 — design-kit/ Е "ИДЕАЛ", chat.php Е "РЕАЛНОСТ" (S141, 12.05.2026)

**Откритие:** chat.php (canonical SWAP файл от S140) **НЕ импортира НИЩО от design-kit/**. Има 60 KB inline CSS.

**Заключение:** При конфликт между design-kit/README.md и chat.php → **chat.php печели**.

**За нов модул:** Standalone файл с inline CSS от mockup. БЕЗ design-kit/ import-и. БЕЗ partials/. Чисто, изолирано.

**Документация:** `docs/MODULE_REDESIGN_PLAYBOOK_v1.md` §1 + §6.

---

# ЧАСТ 3 — ТЕХНИЧЕСКИ КОНТЕКСТ

## Сървър
- DigitalOcean Frankfurt (164.90.217.120)
- /var/www/runmystore/ (main worktree)
- PHP 8.3, MySQL 8, Apache
- DB credentials: /etc/runmystore/db.env (chmod 600)
- API ключове: Gemini в db.env (два ключа, ротация при 429), GROQ в /etc/runmystore/api.env (chmod 640)

## GitHub
- Repo: tiholenev-tech/runmystore (main клон)
- raw.githubusercontent.com и api.github.com → БЛОКИРАНИ в sandbox
- САМО github.com blob URLs работят:
  ```
  curl "https://github.com/tiholenev-tech/runmystore/blob/main/FILE?plain=1"
  ```
  Парсвай "rawLines":[...] от HTML. Helper: tools/gh_fetch.py
- НЕ ползвай web_fetch за GitHub — само bash_tool с curl/python3
- Тихол НЕ качва файлове — Claude чете сам от GitHub

## Worktrees
- /var/www/runmystore — main (production)
- /var/www/rms-design — дизайн работа
- /var/www/rms-stress — стрес тестове
- /home/tihol/rms-visual-gate — CC работна директория (s136 клон)

## Deploy pattern
- Multi-file: /tmp/ → tar+xz+base64 → single paste decode → staging → diff → ПОТВЪРДИ → cp
- Лимит: ≤11KB compressed per paste
- ЗАДЪЛЖИТЕЛНО след fix: `cd /var/www/runmystore && git add -A && git commit -m "описание" && git push origin main`
- НЕ destructive без потвърждение (rm/chmod/git reset/DROP/TRUNCATE/ALTER)

## Pre-commit hook
- Има path бъг: hardcoded /var/www/runmystore/$f
- В worktree проверява ГРЕШНИЯ файл
- Bypass: `git commit --no-verify -m "message"` когато грешките са в стар body
- Compliance check: `bash /var/www/runmystore/design-kit/check-compliance.sh file.php`

## CC (Claude Code) сесии
- tmux ЗАДЪЛЖИТЕЛЕН: `tmux new -s nameN` → `su - tihol` → `cd /home/tihol/rms-visual-gate` → `claude`
- Claude Code auto-deny за --no-verify → Тихол прави commit ръчно
- Cherry-pick от worktree към main: `git cherry-pick HASH -n && git commit --no-verify -m "msg" && git push origin main`

## ENI tenant
- tenant_id=7, tiholenev@gmail.com
- 5 магазина: Склад / Вас.Левски / Лукс / Сан Стефано / Ростов + онлайн
- РЕАЛЕН tenant — без seed data, ръчно реални артикули
- Samsung Z Flip6 (373px cover display) за тестване
- DTM-5811 Bluetooth принтер (TSPL, 50×30mm, MAC DC:0D:51:AC:51:D9)
- Capacitor APK — rebuild нужен за server changes

## Валута (S73+)
- БГ е в евро от 1.1.2026
- Двойно показване (€ + лв по курс 1.95583) ЗАДЪЛЖИТЕЛНО до 8.8.2026
- priceFormat($amount, $tenant) навсякъде

## SQL patterns
- DB::get() + DB::run() + DB::tx() — никога $pdo
- products.retail_price (НЕ sell_price)
- products.code (НЕ sku)
- inventory.quantity (НЕ qty)
- sales.status='canceled' (един L)

## AI архитектура
- Gemini 2.5 Flash primary (два API ключа, ротация при 429)
- GROQ Whisper за voice recognition
- AI = операционен слой над всички модули, не отделен модул
- $MODULE_ACTIONS per module, /ai-action.php е ядрото
- Signal tap → Signal Detail overlay (НЕ чат директно)
- 6-те фундаментални въпроса: 1)Какво губя(червено) 2)От какво(виолет) 3)Какво печеля(зелено) 4)От какво(тюркоаз) 5)Поръчай(амбър) 6)НЕ поръчай(сиво)
- Profit-first: всички числа = чиста печалба, никога оборот или марж
- "AI" в UI, НИКОГА "Gemini"

## Дизайн допълнения
- Design Kit: /var/www/runmystore/design-kit/ — 13 locked файла
- DESIGN_SYSTEM.md е истината за цветове, typography, components
- Hue класове: q1-q6, qd, qm. НИКОГА inline hue variables
- oklch палитра за light тема (НЕ color inversion)
- Header: 7 елемента фиксиран ред (Тип А)
- Bottom nav: 4 таба фиксирани (AI/Склад/Справки/Продажба)
- Wizard правила: няма "чернова" — минимален запис (име+цена+бройки) = истински продукт
- Печат = отделна страница/overlay от ВСЯКА стъпка
- Недовършени продукти = пил "⚠ недовършен" в списъка

## Блокери и забрани
- STRESS Lab — crons disabled (/etc/cron.d/stress-*.disabled). Активация САМО при "започваме стрес теста"
- s136-chat-rewrite-v3 клон = АРХИВ. Не продължавай, не merge-вай
- Катастрофата на 10.05: merge на s136 счупи production. Урок: layout shell и page content са РАЗЛИЧНИ слоеве
- staging.runmystore.ai — не е настроена още
- НЕ модифицирай partials/* без одобрение от Тихол
- НЕ предлагай merge на main без ръчна проверка

## Бъдещо (не сега, но да знаеш)
- Ecwid by Lightspeed = online store partner
- Marketing Bible в docs/marketing/ — schema migration (25 нови таблици) нужна
- Promotions = Phase D, след бета
- DUAL-AUDIENCE AI = Phase 5, декември 2026
- Pricing: FREE €0 / START €19 / PRO €49 / BUSINESS €109

---

# ЧАСТ 4 — ЕТАЛОННИ МАКЕТИ

| Макет | Страница | Режим | Статус |
|---|---|---|---|
| P10_lesny_mode.html | life-board.php | лесен | ✅ ЕТАЛОН |
| P11_chat_v7_orbs2.html | chat.php | разширен | ✅ ЕТАЛОН (орб нав + Тип А хедър) |
| P14_deliveries.html | deliveries.php | лесен | ⚠️ Хедър грешен (Тип А вместо Тип Б) |
| P14b_deliveries_detailed_v5_BG.html | deliveries.php | разширен | ✅ ЕТАЛОН (Тип Б хедър) |
| P15_products_simple.html | products.php | лесен | ✅ Canonical (Тих одобри 12.05) |
| P2_v2_detailed_home.html | products.php | разширен (4 таба) | ✅ Създаден 12.05 — заменя P2 |
| P2_home_v2.html | products.php | (legacy) | ❌ Отпада в полза на P2_v2 |
| P3_list_v2.html | products.php | разширен Tab "Артикули" | ⚠️ Trябва интеграция в P2_v2 |
| P12_matrix.html | products.php | матрица overlay | ✅ Не се пипа |
| P13_bulk_entry.html | products.php | wizard | ✅ Не се пипа |

**Нужни нови макети:**
- P16 — sale.php лесен (ако се наложи)
- P19 — inventory.php (двата режима)
- P17/P17b — orders.php лесен/разширен (Фаза 2)
- P18/P18b — transfers.php лесен/разширен (Фаза 2)

---

# ЧАСТ 5 — ПЛАН ДО КРАЯ НА ПРЕД-БЕТА

## ФАЗА 0 — ШАБЛОН (единна обвивка)

| # | Задача | Статус |
|---|---|---|
| 0.1 | LAYOUT_SHELL_LAW.md v1.1 — два типа хедър (А и Б) + seller toggle скрит | ⏳ |
| 0.2 | partials/shell.php — единен include за всички модули | ⏳ |
| 0.3 | Тест: shell.php работи на празна страница light + dark | ⏳ |
| 0.4 | Поправи макети P2/P3 (превод БГ + Тип Б хедър + орб нав) | ⏳ |
| 0.5 | Поправи макет P14 (Тип Б хедър вместо Тип А) | ⏳ |

## ФАЗА 1 — МОДУЛИТЕ ЗА РЕАЛНА РАБОТА

### 1.1 products.php — РЕДИЗАЙН (~3-4 сесии)

**⚠ S141 UPDATE 12.05.2026 — стратегия променена:** INJECT-ONLY → **SWAP** (както chat-v2 → chat в S140). Нов файл `products-v2.php` паралелен с production. SWAP в края.

**Защо SWAP:** Откритие в S141 — design-kit/ е "идеал", chat.php е "реалност" с inline CSS. CSS injections в 14K реда production файл = постоянен conflict. Standalone нов файл = чисто. Документирано в `docs/MODULE_REDESIGN_PLAYBOOK_v1.md`.

**Логиката работи.** Пренасяме я секция по секция в нов файл.
**Двата режима.** Печат на етикети остава sacred (capacitor-printer.js непокътнат).

#### Концептуални задачи (mockup-driven):

| # | Задача | Статус |
|---|---|---|
| 1.1.1 | Лесен режим: P15 → products-v2.php simple view | 🔄 IN PROGRESS (shell готов) |
| 1.1.2 | Разширен dashboard: P2 → P2_v2 (4 таба) → products-v2.php detailed | 🔄 P2_v2 mockup готов |
| 1.1.3 | Разширен списък: P3 → products-v2.php detailed Tab "Артикули" | ⏳ |
| 1.1.4 | Wizard (добави артикул): P13 → extract в partials/products-wizard.php (1:1 SACRED) | ⏳ |
| 1.1.5 | Matrix (вариации): P12 overlay | ⏳ |
| 1.1.6 | Етикети: печат работи от двата режима (sacred existing logic) | ⏳ |
| 1.1.7 | Тест: light + dark + Z Flip6 373px | ⏳ |

#### Реални 7 имплементационни стъпки (S141 шеф-чат):

| Step | Какво | Commit | Статус |
|---|---|---|---|
| 1 | Shell: PHP backend + head + Тип Б header + subbar + placeholder main + chat-input/bottom-nav | 7dded4e | ✅ Готов 12.05 |
| 2 | P15 simple content (тревоги + добави + AI поръчка + help + 6 AI сигнала) | — | ⏳ Next |
| 3 | P2v2 detailed content (4 таба: Преглед/Графики/Управление/Артикули) | — | ⏳ |
| 4 | Wizard extract в `partials/products-wizard.php` (sacred 1:1 от products.php ред ~7800-12900) | — | ⏳ |
| 5 | AJAX endpoints copy (search/save/insights/store-stats) | — | ⏳ |
| 6 | Visual polish + Тих feedback iterations | — | ⏳ |
| 7 | SWAP: `git mv products.php products.php.bak.S141 && git mv products-v2.php products.php` | — | ⏳ |

#### Решения от Тих през S141 (12.05.2026):

1. **P15 = canonical simple home** (не Bible §7.2.1 "Hybrid layout")
2. **P2 mockup отпада** — заменено с **P2_v2_detailed_home.html** (4 таба, 17 одобрени идеи)
3. **17 идеи за detailed mode** приети: sparklines, Парето, heatmap, donut, seasonality, ABC analysis, multi-store comparison, saved views, bulk actions
4. **AI прогноза с числа отхвърлена** — нарушава Закон №2 (PHP смята, AI говори qualitative)
5. **Глобален "Инвентаризация nudge"** = НОВ ЗАКОН за ВСЕКИ модул (виж Закон №10 по-долу)
6. **"Здраве склада" → "Състояние на склада"** с breakdown — не плосък %

#### Backup tags за S141:

```
pre-S141-p15-home               (преди първи INJECT опит — провален)
pre-S141-p15-simple-home        (използван за revert след INJECT провал)
pre-products-v2-S141            (преди products-v2.php shell creation)
```

Emergency revert:
```bash
cd /var/www/runmystore && git reset --hard pre-products-v2-S141 && git push origin main --force
```

### 1.2 deliveries.php — ДОПЪЛВАНЕ (~4-5 сесии)

Phase A скелет готов (11.05). Трябва DB корекции + Phase Б.

| # | Задача | Статус |
|---|---|---|
| 1.2.1 | DB: поправи колони (total→total_cost, number, delivered_at→committed_at) | ⏳ |
| 1.2.2 | DB: status ENUM (review не reviewing, махни pending) | ⏳ |
| 1.2.3 | DB: payment_status 'partial' не 'partially_paid' | ⏳ |
| 1.2.4 | Хедър → Тип Б (← Доставки + тема + ПРОДАЖБА) | ⏳ |
| 1.2.5 | Орб навигация в разширен режим | ⏳ |
| 1.2.6 | Phase Б: "Получи доставка" — OCR (снимай фактура) | ⏳ |
| 1.2.7 | Phase Б: "Получи доставка" — Voice (кажи) | ⏳ |
| 1.2.8 | Phase Б: "Получи доставка" — Ръчно | ⏳ |
| 1.2.9 | Phase Б: Reconciliation (сравни с поръчка) | ⏳ |
| 1.2.10 | Phase Б: Inventory update при commit + stock_movements | ⏳ |
| 1.2.11 | Тест: light + dark + Z Flip6 | ⏳ |

### 1.3 sale.php — ВИЗУАЛНА КОРЕКЦИЯ (~1-2 сесии)

Работи прекрасно. Само визия.

| # | Задача | Статус |
|---|---|---|
| 1.3.1 | Светъл режим (light theme) | ⏳ |
| 1.3.2 | Хедър → Тип Б | ⏳ |
| 1.3.3 | Двата режима (лесен/разширен) | ⏳ |
| 1.3.4 | Тест: light + dark + Z Flip6 | ⏳ |

### ► СЛЕД ФАЗА 1: ТИХОЛ ПОЧВА РАБОТА В ENI
- Печата етикети (products)
- Приема доставки (deliveries)
- Продава (sale)
- Записва какво липсва → задачи за Фаза 2

## ФАЗА 2 — ПАРАЛЕЛНО ДОКАТО РАБОТИ

### 2.1 inventory.php (~2 сесии)
80% готов. Редизайн + скрита инвентаризация.

| # | Задача | Статус |
|---|---|---|
| 2.1.1 | Макет P19 | ⏳ |
| 2.1.2 | Редизайн по макет | ⏳ |
| 2.1.3 | Zone Walk flow | ⏳ |

### 2.2 transfers.php — НОВ (~2 сесии)

| # | Задача | Статус |
|---|---|---|
| 2.2.1 | Макет P18/P18b | ⏳ |
| 2.2.2 | DB миграция | ⏳ |
| 2.2.3 | CC: скелет | ⏳ |
| 2.2.4 | Опус: редизайн | ⏳ |

### 2.3 orders.php — НОВ (~2-3 сесии)

| # | Задача | Статус |
|---|---|---|
| 2.3.1 | Макет P17/P17b | ⏳ |
| 2.3.2 | DB миграция | ⏳ |
| 2.3.3 | CC: скелет | ⏳ |
| 2.3.4 | Опус: редизайн | ⏳ |

### 2.4 chat.php — РЕДИЗАЙН (~2 сесии)

| # | Задача | Статус |
|---|---|---|
| 2.4.1 | Лесен: 4 бутона → правилни връзки (sale/products/deliveries/orders) | ⏳ |
| 2.4.2 | Лесен: без навигация извън 4-те бутона | ⏳ |
| 2.4.3 | Разширен: редизайн по P11 | ⏳ |
| 2.4.4 | Орб навигация | ⏳ |

## ФАЗА 3 — AI BRAIN + ВТОРОСТЕПЕННИ

Започва СЛЕД стабилна Фаза 1+2.

- compute-insights.php разширяване
- AI теми активиране (857 теми от ai_topics_catalog)
- Промоции модул (Phase D)
- Финанси модул — **разделено на Phase B + Phase 8** (виж по-долу)
- Лоялна програма (LOYALTY_BIBLE.md)

---

## ФАЗА 3.1 — ФИНАНСОВ МОДУЛ (S148, разделено)

**Source-of-truth:** `STATS_FINANCE_MODULE_BIBLE_v1.md` (12 500 реда, ETAP 1-7)

### Phase B — beta-critical (юни-юли 2026, влиза в beta)

✅ Включено в beta scope:
- **stats.php → 3 таба:** Преглед / Артикули / Финанси (owner-only)
- **Финанси sub-tab "Печалба" (12.1)** — пълно функционален:
  - P&L breakdown (Revenue − COGS = Gross Profit)
  - Margin trend 12 седмици
  - Top profit products
  - Category margin
  - Discount erosion alert
  - Confidence warning при cost_at_sale < 100%
- **Финанси sub-tabs 12.2-12.5** — placeholder с "Скоро" badge
- **s82-dash REVISED:** компактно финансово табло в life-board.php (виж по-долу)
- **DB migrations Phase B:** vat_rates, z_reports, store_balances + universal money_movements

### Phase 8 — post-beta (Q4 2026)

- 12.2 Cash flow + balance + burn rate
- 12.3 Разходи + budget vs actual
- 12.4 Дължими + B2B invoicing + tax tracking
- 12.5 Експорти (Microinvest/Sigma/Ajur)
- 12 нови DB таблици (M-004 → M-015)

### s82-dash REVISED (S148)

**ОТМЕНЕНО:** Старата концепция "top product + low stock + dead capital + Поръчай бутон" е DEPRECATED.

**НОВА концепция (Bible §24):** компактно финансово табло в life-board.php:
- 1 голямо число (Operating Profit / Касов баланс)
- AI ротиращ insight slot
- 3 quick action бутона: 🎤 Запиши · 📷 Снимка · 📊 Виж
- Tap → отваря stats.php?tab=finance

Top product / low stock / dead capital остават в life-board.php но в ОТДЕЛНИ карти.

### Two-Product Architecture (Закон §42)

**Един codebase, два продукта.** RunMyStore (start/pro/business) + Pocket CFO (€4.99/мес). Не блокира RMS beta — Pocket CFO върви паралелно.

---

# ЧАСТ 6 — СВЪРШЕНА РАБОТА (история)

| Дата | Задача | Запис |
|---|---|---|
| 11.05.2026 | LAYOUT_SHELL_LAW.md v1.0 | 6e1a98f |
| 11.05.2026 | DB миграция за доставки (5 таблици) | sandbox + production |
| 11.05.2026 | deliveries.php Phase A скелет | cbf338e |
| 11.05.2026 | P15_products_simple.html макет | 38fc191 |
| 11.05.2026 | P2/P3 превод на български | pending |
| 11.05.2026 | PREBETA_MASTER.md v2.0 | pending |
| 10.05.2026 | chat.php P11 rewrite + REVERT (катастрофа) | 8e440be |
| 10.05.2026 | Visual-gate v1.3 инфраструктура | s136 клон |

---

# ЧАСТ 7 — КЛЮЧОВИ ДОКУМЕНТИ

| Документ | Къде | За какво |
|---|---|---|
| PREBETA_MASTER.md | repo root | ТОЗИ ДОКУМЕНТ — планът |
| MASTER_COMPASS.md | repo root | Жив лог на проекта (LOGIC LOG) |
| LAYOUT_SHELL_LAW.md | docs/ | Закон за хедър и навигация |
| DESIGN_SYSTEM.md | repo root | Дизайн система v4.1 bichromatic |
| DELIVERY_ORDERS_DECISIONS_FINAL.md | repo root | 165 решения за доставки |
| DELIVERIES_FINAL_v3_COMPLETE.md | docs/ | 380K спецификация доставки |
| ORDERS_DESIGN_LOGIC.md | repo root | Спецификация поръчки |
| PRODUCTS_DESIGN_LOGIC.md | repo root | Спецификация продукти |
| INVENTORY_HIDDEN_v3.md | repo root | Скрита инвентаризация |
| SIMPLE_MODE_BIBLE.md | repo root | Лесен режим философия |
| AI_STUDIO_LOGIC.md | repo root | AI Studio спецификация |
| LOYALTY_BIBLE.md | repo root | Лоялна програма (Фаза 3) |

---

# ЧАСТ 8 — ПРОТОКОЛ ЗА КРАЙ НА СЕСИЯ

Изпълнява се САМО когато Тихол каже "изпълни протокол за приключване на сесията".

### Стъпка 1: Обнови PREBETA_MASTER.md
- Маркирай свършените задачи: ⏳ → ✅ ДД.ММ
- Добави нови задачи ако има (с ⏳)
- Обнови ЧАСТ 6 (свършена работа) с нови записи
- Обнови ЧАСТ 4 (макети) ако статус се е променил

### Стъпка 2: Обнови MASTER_COMPASS.md
Добави LOGIC LOG entry в края:
```
## [ДАТА] ШЕФ-ЧАТ #[N] — [заглавие]

**Свършено:**
- (2-3 реда какво е направено)

**Следващо:**
- (първата ⏳ задача от PREBETA_MASTER)

**Проблеми/блокери:**
- (ако има)

**Записи:** [commit hash-ове]
```

### Стъпка 3: Git commit + push
```bash
cd /var/www/runmystore
git add PREBETA_MASTER.md MASTER_COMPASS.md
git commit --no-verify -m "EOD [ДАТА]: [кратко описание]"
git push origin main
```

### Стъпка 4: Доклад на Тихол
Кратък доклад (5-6 реда): какво е свършено, какво е следващо, колко задачи от плана са завършени (X от Y).

---

**КРАЙ НА PREBETA_MASTER.md v2.0**
*Следващ update: от шеф-чат при завършване на задача.*

---

# S141 PROGRESS LOG (12.05.2026)

**Total commits S141:** 10

**Файлове създадени:**

| Файл | Размер | Статус |
|---|---|---|
| `docs/MODULE_REDESIGN_PLAYBOOK_v1.md` | 24 KB · 461 реда | ✅ Critical reference for future chats |
| `PRODUCTS_MASTER.md` | 96 KB · 2185 реда | ✅ 16 секции — products цялостна спецификация |
| `mockups/P2_v2_detailed_home.html` | 63 KB · 1853 реда | ✅ 4-tab detailed home mockup |
| `products-v2.php` | 75 KB · 1380 реда | 🔄 Step 1/7 shell готов |
| `daily_logs/DAILY_LOG_2026-05-12.md` | 5 KB | ✅ Daily log |
| `COMPASS_APPEND_S141.md` | 4 KB | ✅ EOD статус |
| `PREBETA_MASTER_v2.md` v2.1 | (този файл) | ✅ Обновен |

**Sacred zone — НЕ пипано:**

- `products.php` (14,074 реда) — production, непокътнат
- `services/voice-tier2.php` (333 реда) — Whisper Tier 2 БГ числа
- `ai-color-detect.php` (296 реда) — Gemini Vision color
- `js/capacitor-printer.js` (2097 реда) — DTM-5811 + D520BT BLE/SPP
- products.php wizard mic buttons (8 input полета)

**Следваща сесия започва с:** Step 2 на products-v2.php (P15 simple content). Pre-flight: чети MODULE_REDESIGN_PLAYBOOK + PRODUCTS_MASTER + COMPASS_APPEND_S141.

---

**End of v2.1.**
---

# S142 PROGRESS LOG (12-13.05.2026)

**Total commits S142:** 10

**Шеф-чат:** Opus 4.7, ~5 часа сесия

**Файлове създадени:**

| Файл | Размер | Статус |
|---|---|---|
| `mockups/P15_simple_FINAL.html` | 82 KB · 1653 реда | ✅ Approved canonical |
| `mockups/P2_v2_detailed_FINAL.html` | 147 KB · 2703 реда | ✅ Approved canonical |
| `SESSION_S142_FULL_HANDOFF.md` | 67 KB · 1746 реда | ✅ Пълен контекст за S143 |
| `S142_BUG_REPORT.md` | 9 KB · 224 реда | ✅ 6 bugs за S143 |
| `COMPASS_APPEND_S142.md` | ~5 KB | ✅ EOD статус |

**Файлове обновени:**

| Файл | Промяна |
|---|---|
| `docs/BIBLE_v3_0_CORE.md` | +126 реда — Закон 6 + "ПЕТТЕ" → "ШЕСТТЕ" |
| `docs/DETAILED_MODE_SPEC.md` | +71 реда — §0 Philosophy |
| `products-v2.php` | 1380 → 3251 реда (+1694 / -647) — Step 2A+B+C+D + 3 hotfix-а |

**Sacred zone — НЕ пипано:**

- `products.php` (14,074 реда) — production, непокътнат
- `services/voice-tier2.php` — sacred
- `services/ai-color-detect.php` — sacred
- `js/capacitor-printer.js` — sacred
- 8 mic input полета във wizard — sacred

**Главно постижение:** Закон №6 в Bible — universal pattern "SIMPLE = СИГНАЛИ · DETAILED = ДАННИ" за ВСИЧКИ модули (Sale, Доставки, Трансфери, Промоции, Marketing, Reports, Settings).

**Следваща сесия започва с:** Step 3 = fix 6 documented bugs в `S142_BUG_REPORT.md`. Pre-flight: чети `SESSION_S142_FULL_HANDOFF.md` (1746 реда — пълен контекст).

**Backup safety net:** `pre-step2-S142` tag → 30-секунден revert.

---

**End of S142 progress log. v2.2.**

## S143 ENDS — продуктов модул редизайн (14.05.2026)

### СТАТУС: ⚠ Незавършено

### Свършено:
- products-v2.php v1-v4 (SWAP файл, не е production)
- Богата търсачка с 16+ филтри
- Sticky search + cascading filters
- Обща картина (store_id=0)
- Информативен бокс v1 (2 нива)
- Deep Research за AI auto-fill ИКОНОМИКА — €0.15/мес/магазин, 94% марж

### НЕЗАВЪРШЕНО:
- Info-box v2 (3 нива: пълна/частична/минимална)
- Решение за confidence_score изчисление
- Премахване на inv-nudge button
- Тестване на products-v2.php в production (още не активиран)

### S143 файлове:
- products-v2.php (4499 редa, SWAP, на main)
- docs/AI_AUTOFILL_RESEARCH_2026.md (Deep Research 413 редa)
- migrations/20260513_001_products_filters.up.sql (приложена)
- CORE_BUSINESS_RULES.md (нов файл с бизнес правила)
- TOMORROW_WIZARD_REDESIGN.md (план за S144)

### S144 ПРИОРИТЕТИ:
1. Info-box v2 (3 нива + AI обяснение)
2. confidence_score логика (възможност 1/2/3)
3. Wizard редизайн "Добави артикул"
4. Снимка → стъпка 2 в wizard
5. AI all-in-one prompt (категория + цвят + описание)
6. Ниво 1 baрcode lookup + Ниво 2 perceptual hash

### Beta blocker:
- products-v2.php SWAP не е активиран още
- Чака потвърждение от Тих след тестване
- Кога активира: rename products.php → products-v1-old.php; mv products-v2.php → products.php


---

# S144 BETA READINESS UPDATE (15.05.2026)

## Status: Beta launch DAY = TODAY (15 май)

**Реалистично:** не е готов 100%. Имаме функциониращ Simple Mode на products-v2.php, но wizard + AI Studio не са финализирани.

## Какво е готово за beta

### ✅ Stable (можем да startirame с тези)

- **Login + auth** ✓
- **chat.php Simple home** (life-board)
- **products-v2.php Simple Mode** — реални insights, info-box, list view, filter
- **products-v2.php Detailed Mode** — UI е там, mockup data в някои секции
- **sale.php** — основни функции работят (numpad, voice, scanner)
- **inventory.php v3** — count sessions
- **products.php → products-v2.php redirect** (clean migration)
- **Header унифициран** (3 форми)
- **Bottom-nav session-based**

### ⚠️ Functional но incomplete

- **Wizard "Добави артикул"** — работи без 4 нови AI полета. Voice STT locked.
- **AI Studio** — работи, но drawer-овете не са дизайн-rafinirani
- **AI insights** — 13 от 25 имплементирани, активни на ENI

### ❌ Не готови за beta

- **deliveries.php** — 0% (без него: 5 типа AI сигнали не светят)
- **orders.php** — 0%
- **transfers.php** — 0%
- **promotions** — 0%
- **loyalty** — 0%
- **finance.php** — 0%
- **10-те S142 типа сигнали** — само generic 25 (НЕ specific Alert/Weather/Transfer/...)

## Recommended beta strategy

**За ENI клиент (tenant_id=7 пробен):**
- НЕ launch-вай на реален клиент още — wait for wizard + AI Studio done
- Използвай tenant=7 като продължение на тестване
- Финализирай products module (wizard + AI Studio + Detailed Mode) → след това beta на реален клиент

**Realistic beta date:** **края на май / началото на юни 2026** (не 14-15 май).

## Critical path до реален beta

1. **S145** — wizard 4 нови AI полета + AI Studio polish
2. **S146** — Detailed Mode finalization (11 секции реални данни)
3. **S147** — deliveries.php (минимум — за supplier reliability)
4. **S148** — orders.php
5. **S149** — promotions basic
6. **S150** — beta launch на реален клиент

## Какво S144 направи за beta готовност

- Стабилизира Simple Mode (повече не се чупи)
- Унифицира header/bottom-nav (по-малко UX гафове)
- Премахна Nike/Adidas mockup data (вече реални insights)
- Премахна дублиращи UI елементи (по-чист интерфейс)
- Документира всички правила в design system v4

**Net effect: По-стабилна основа, но НЕ ускорихме beta date.**

