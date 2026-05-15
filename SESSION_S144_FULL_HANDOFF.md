# 🎯 SESSION S144 FULL HANDOFF — RunMyStore.AI

**Дата:** 15 май 2026 EOD
**За:** S145 (следващ шеф-чат)
**Време за прочит:** 5 минути
**Цел:** S145 да започне БЕЗ Тих да пуска ръчно документи

---

## 🚨 ЗА S145 — ПРОЧЕТИ ЦЕЛИЯ ТОЗИ ФАЙЛ ПРЕДИ ДА ОТГОВОРИШ НА ТИХ

Преди да коментираш, питаш или предприемеш ДЕЙСТВИЕ — отговори на BOOT TEST (в края на файла). Без правилни отговори → не започваш работа.

**НИКОГА НЕ ПИТАЙ ТИХ:**
- "Дай ми S142" → всичко важно е тук, по-долу
- "Пусни ми design system" → §3 от DESIGN_SYSTEM_v4.0_BICHROMATIC.md е цитирано тук
- "Какво направихте вчера" → виж секция COMMITS

---

# ЧАСТ 1 — КЪДЕ СПРЯХМЕ

## Какво работехме в S144

**Фокус:** Simple Mode на products-v2.php — почистване, реални insights, filter, навигация.

**Резултат:** Simple home работи с реални данни на ENI (tenant=7). Detailed Mode остава с mockup data (не сме го пипнали).

## 23 commits от днес (S144)

| Commit | Какво |
|---|---|
| `8edf1fd` | Confidence формула: правило #49 — преброяване ≠ информация. Снимка +10→+30 |
| `b357813` | products-v2.php?screen=list — нов списък с confidence filter |
| `5ac9872` | products.php → redirect към products-v2.php |
| `e951f9b` → `2107cc3` | Header унифициран (3 форми) + DESIGN_SYSTEM v4 §3 обновен |
| `1be8934` | SESSION-based active_mode (chat/life-board → simple) |
| `1b5275c` | Force mode= в URL за info-box линкове |
| `e48a24c` | COUNT(DISTINCT) бъг — артикул в N обекта се броеше N пъти |
| `f604cb1` | chat.php + life-board.php auto-избираха магазин — fix |
| `7ba3623` | Закон #51 — Native back-button guard правило |
| `1343302` | 6 help-chips → chat handoff (askAI + prompt + from params) |
| `20f6620` | Махнати stores-glance + 2 chips + 8 hardcode карти. Real AI insights loop |
| `6a4e678` | Insights module home (не products) |
| `3ee5904` | getInsightsForModule store_id=0 → не филтрира (Всички магазини) |
| `61e4e48` | applyFilters → products-v2.php (не стария products.php) + list SQL чете filter params |
| `c47d8b4` | Filter cleanup — махнати Преброяване + Информация дубликати |
| `ba7f441` | Сигнал бутоните: Покажи списък (overlay) / Защо (chat) / Действие (chat) |
| `c990e70` | 1004 fake sales seednati от Claude Code (tools/seed/sales_smart_seed.php) |

## Где сме в roadmap

| Модул | Status | Бележка |
|---|---|---|
| **chat.php** | ~95% работещ | Simple home сигнали стабилни |
| **products.php** | DEPRECATED | Редиректва към products-v2.php |
| **products-v2.php Simple** | ~80% | Реални insights, info-box, filter, list view |
| **products-v2.php Detailed** | ~30% | Hardcoded mockups, не пипнат в S144 |
| **products-v2.php Wizard "Добави артикул"** | ~85% | Voice STT locked. 4 нови AI полета (пол/сезон/марка/описание) **НЕ имплементирани** |
| **AI Studio** | ~70% | Bg removal + magic clothes/products. Отделна страница, не част от wizard |
| **sale.php** | ~90% | S87E patch с 8 bugs pending |
| **inventory.php v3** | ✅ DONE | Count sessions работят |
| **deliveries.php** | 0% | Очаква спецификация |
| **orders.php** | 0% | Чакаме |
| **transfers.php** | 0% | Чакаме |

---

# ЧАСТ 2 — РЕШЕНИЯ ВЗЕТИ В S144 (НЕ СЕ ПРЕРАЗГЛЕЖДАТ)

## Правило #49 — Преброяване ≠ Информация

**Преди:** Преброен физически даваше +20 точки в confidence_score
**След:** Преброеността НЕ влиза в confidence. Тя е отделен axis ("Не е броен · X дни").

**Confidence формула финална:**
- Име + цена = 20
- Бройки = +15
- Снимка (вкл. AI auto-fill 9 полета) = +30
- Доставчик + Категория = +10
- Доставна цена = +20
- Баркод/SKU = +5
- **Max = 100**

**3 нива (заменя 4):**
- 🔴 Минимална 0-39
- 🟡 Частична 40-79
- 🟢 Пълна 80-100

**Етикетите за Пешо:** "Готови / Недовършени / Празни"

## Правило #50 — Header има 3 форми (НЕ СЕ ОБЯСНЯВА ПОВТОРНО)

> **Във всеки модул е форма Б, освен chat.php = форма А, sale.php = форма В.**

**Форма А — chat.php (FULL):**
brand → plan-badge → spacer → Print → Settings → Logout → Theme

**Форма Б — ВСИЧКИ ОСТАНАЛИ страници (products-v2, warehouse, stats, и т.н.):**
brand → spacer → Theme → Продажба pill (амбър gradient)

**Форма В — sale.php:**
БЕЗ header изобщо. Камерата заема горната част.

**Документиран в:** `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` §3.1

## Правило #51 — Bottom-nav е SESSION-BASED

> **Влязъл от Лесен → никъде нямаш 4 таба. Влязъл от Разширен → навсякъде имаш 4 таба.**

`$_SESSION['active_mode']` се сетва:
- chat.php / life-board.php → `'simple'` ВИНАГИ
- products-v2.php?mode=detailed → `'detailed'`
- Default: owner → detailed, seller → simple

**Bottom-nav (4 tabs AI/Склад/Справки/Продажба):**
- В Simple → НЕ се рендерира (replaced с chat-input-bar)
- В Detailed → ВИНАГИ се рендерира

**Документиран в:** `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` §3.3

## Правило #52 — Back-button guard (отложено)

Глобална имплементация **след всички модули** (post-beta, Q3 2026).
Изключение: **products-v2.php** — back-guard щом се финализира (защото там работим много).

## tenant_id=7 = ПРОБЕН профил на Тихол

НЕ е реален beta клиент. Защитите за writing срещу tenant_id=7 могат да се махат. Фиктивни данни разрешени.

## Tenant_id=7 текущ state (15.05.2026 EOD)

- 387 active parent products
- 251 с реален stock (qty > 0)
- 767 включая variants
- 30 stores configured
- 1004 fake sales добавени (smart adversarial seed)
- 13 ai_insights активни (от 25 имплементирани в compute-insights.php)

---

# ЧАСТ 3 — КОНЦЕПЦИЯТА "ВСЯКО DETAILED → ПРАВИ СИГНАЛ В SIMPLE"

## Идея (от S142 + потвърдено в S144)

**Detailed Mode = ръчно разглеждане:**
- Митко вижда KPIs, sparklines, графики, таблици
- Сам стига до изводи
- Безкрайно много данни — без limits

**Simple Mode = AI обработва същите данни и казва:**
- Пешо НЕ преглежда нищо ръчно
- AI превръща ВСИЧКО в action-oriented сигнал
- Top 6-8 сигнала (по 1 per fundamental_question)

**Правилото:** Всяка функция в Detailed → трябва да има еквивалентен сигнал в Simple. Иначе Пешо няма достъп до тази информация.

## 10-те типа сигнали в Simple (от S142, НЕ имплементирани още)

| # | Тип | Цвят | Примерен текст |
|---|---|---|---|
| 1 | 🔴 Alert | q1 red | "Свърши Nike N42 · 7 продажби тази седмица" |
| 2 | 🌤 Weather | q5 amber | "Топло идва 25-26°C · летни рокли · имаш 8 бр" |
| 3 | 🔄 Transfer | q4 cyan | "5 бр N42 · Бургас → Скайтия" |
| 4 | 💰 Cash trapped | q2 purple | "1 180€ спят в стока 60+ дни" |
| 5 | 📏 Size run | q5 amber | "Тениска М свърши, остават S+L" |
| 6 | 📦 Supplier | q1 red | "Verona закъсня · 11 пропуснати" |
| 7 | 💸 Cash variance | q1 red | "Z вчера +24 лв · провери" |
| 8 | 📈 Sell-through | q5 amber | "Новите · 12% продадени, цел 25%" |
| 9 | 🟡 Trend | q3 green | "Печалба +12% · виж защо" |
| 10 | 🟢 Win | q3 green | "Рекорден ден · 47 продажби" |

**Detailed Mode имплементация:** 11 секции в Tab Преглед (виж S142 ред 383-394 ако трябва пълна спецификация — но НЕ е приоритет за S145).

---

# ЧАСТ 4 — КАКВО МАХНАХМЕ ОТ SIMPLE HOME (И ЗАЩО)

Тих ясно каза: "така ми харесват Simple Mode. Не искам повече да усложняваме."

**Махнато:**
1. ❌ Stores-glance "Магазините днес" — продажби-specific, не артикулна
2. ❌ Двата chips "Свършили / Застояли 60+" — дублираха AI feed
3. ❌ 8 hardcoded mockup сигнала (Nike/Adidas) — заменени с реални
4. ❌ Видео chip "Добави първия артикул" — placeholder, видеа не съществуват
5. ❌ Линк "Всички помощни теми" — каталог 600+ topics не готов
6. ❌ "AI Съвет" бутон в product detail drawer — дублираше "Защо"

**Какво ОСТАНА в Simple home:**
- ✅ Header (form Б: brand + theme + Продажба)
- ✅ Search bar + микрофон
- ✅ Multi-store toggle (Всички магазини / конкретен)
- ✅ Simple/Detailed mode switcher
- ✅ Info-box (3 нива confidence — Готови/Недовършени/Празни)
- ✅ Help card "Как работи Стоката ми?" + 6 chips → чат
- ✅ AI feed (top 1 per fundamental_question — collapse cards с реални insights)
- ✅ Chat-input-bar floating отдолу

**Какво НЕ е имплементирано (S142 plan):**
- 10-те типа сигнали (Alert/Weather/Transfer/...)
- Weather Card integration в Detailed
- Multi-store transfer detection
- Cash reconciliation logic
- 11-те секции в Detailed Tab Преглед (има само 6 hardcoded mockup сигнала)

---

# ЧАСТ 5 — РАБОТА ЗА S145

## Приоритет 1 — Wizard "Добави артикул" (HIGH)

Тих изрично каза: "продължаваме с products-v2.php докато не го завършим. Само там лесен и Detailed режим вече, после в добави артикул и AI Studio."

**Wizard статус:** ~85% работещ. Voice STT locked. **НЕ са имплементирани 4-те нови AI полета:**
- gender (Пол) — мъжко/женско/детско/унисек
- season (Сезон) — лято/зима/преходен/целогодишно
- brand (Марка) — от лого
- description_short (Кратко описание) — 20-50 думи

**ВАЖНО:** Wizard НЕ се пипа на своя глава. Преди ВСЯКА промяна — Claude чете 200+ реда wizard код (`renderWizPage`, `renderWizPagePart2`, `_wizAIInlineRows`, `openImageStudio`) и **разбира текущия flow**. Тих няколко пъти беше ядосан че Claude е скimнал wizard.

**Прочети ЗАДЪЛЖИТЕЛНО:**
- `docs/PRODUCTS_DESIGN_LOGIC.md` §3 (4 wizard стъпки)
- `PRODUCTS_WIZARD_v4_SPEC.md`
- `products-v2.php` редове 7645-9300 (manualWizard, renderWizPage, _wizAIInlineRows)

## Приоритет 2 — AI Studio (MEDIUM)

**AI Studio = ОТДЕЛНА страница, НЕ част от wizard.** Активна връзка между двата.

**Има 3 типа обработка:**
- Bg removal (€0.05)
- AI Магия — дрехи (try-on 6 модела, €0.50)
- AI Магия — предмети (8 presets, €0.50)

**Прочети:**
- `docs/AI_STUDIO_LOGIC.md`
- `products.php` ред 7002+ (openImageStudio)

## Приоритет 3 — Detailed Mode сигнали (LOW)

11-те секции в Detailed Tab Преглед остават mockup. Реализирай **в Q3 2026** (post-beta) заедно с 10-те типа сигнали.

## Приоритет ОТЛОЖЕНО — Back-button guard

Изпълнява се **САМО за products-v2.php** при финализация. Глобален guard = post-beta.

---

# ЧАСТ 6 — БЪГОВЕ ОТКРИТИ В S144 (НЕ ФИКСИРАНИ)

## Бъг 1 — "Цветове без цвят" (шампан без visual chip)
- В filter drawer "Цветове" има chips за цветове без visual swatch
- SQL filter `color IS NOT NULL AND color != ''` изглежда правилен
- Може би е data issue (color='шампан' без hex)
- Не critical — отложено

## Бъг 2 — "Полно/Балфон" текст (вероятно speech-to-text)
- Тих каза "е супер но не е балфон"
- Вероятно референция към `composition` поле
- Малък OCR-like fix или data cleanup

## Бъг 3 — Filter "Цена" + "Бройка" interaction
- Може да има race condition между slider и number input
- Тествай: slide quantity → промяна на number → промяна се запазва ли?

## Бъг 4 — Чат отговори при tap на сигнал
Тих каза: "тапнах Защо · отговори ми списък но аз не искам той да ми показва списъка."
- В chat.php openChatQ → AI връща списък от артикули
- Тих иска **кратко обяснение**, не списък
- Може би трябва системни prompt-ове за всеки тип сигнал

---

# ЧАСТ 7 — ПРОМПТ ЗА CLAUDE CODE (STRESS LAB)

Виж отделен файл: **`CLAUDE_CODE_STRESS_LAB_PROMPT.md`**

**Резюме на голямата работа за Claude Code (утре сутрин):**
- 8 stores seed (Склад + 7 магазина + Online)
- 11 доставчика с lead times
- 5 продавача
- 90 дни история (продажби/доставки/инвентаризации)
- **+ 10-те S142 типа сигнали имплементирани в compute-insights.php**
- Weather signal integration активен
- Multi-store transfer detection
- Activate 4-те cron-а

Очаквано време: 4-6 часа в tmux session.

---

# ЧАСТ 8 — ФАЙЛОВЕ КОИТО ТРЯБВА ДА ПРОЧЕТЕШ

**ЗАДЪЛЖИТЕЛНО:**
1. `SESSION_S144_FULL_HANDOFF.md` — **този файл (вече четеш)**
2. `MASTER_COMPASS.md` § Closing State + §S144 Logic Change
3. `DESIGN_SYSTEM_v4.0_BICHROMATIC.md` § 3.1 (header) + § 3.3 (bottom-nav)
4. `docs/BIBLE_v3_0_CORE.md` § Закон #6 (Simple=signals/Detailed=data)
5. `docs/PRODUCTS_DESIGN_LOGIC.md` (целия — за wizard работа)
6. `mockups/P15_simple_FINAL.html` (canonical visual reference)
7. `mockups/P2_v2_detailed_FINAL.html` (canonical detailed visual)

**ПРИ НУЖДА (само ако задачата го изисква):**
- `SIGNALS_CATALOG_v1.md` — 25 имплементирани сигнала + 1000 каталог
- `SIMPLE_MODE_BIBLE.md` — детайли за Simple UX
- `DETAILED_MODE_SPEC.md` §0 — Detailed философия
- `PRODUCTS_WIZARD_v4_SPEC.md` — wizard спецификация
- `AI_STUDIO_LOGIC.md` — AI Studio спецификация

---

# ЧАСТ 9 — BOOT TEST (отговори преди работа)

**S145 → отговори на тези 7 въпроса в първото си съобщение към Тих:**

1. **Кои са 3-те форми на header?** (Очакван отговор: А=chat.php FULL, Б=всички други brand+theme+Продажба, В=sale.php БЕЗ)

2. **Как се решава дали bottom-nav се показва?** (Очакван: чете `$_SESSION['active_mode']`. Simple → не. Detailed → да.)

3. **Кой е tenant_id на Тихол и какъв е?** (Очакван: 7, пробен профил, защитите могат да се махат)

4. **Какво е приоритет 1 за S145?** (Очакван: wizard "Добави артикул" — 4 нови AI полета)

5. **Какво НЕ се пипа без 200+ реда прочитане?** (Очакван: wizard кода в products-v2.php)

6. **Какво направихме днес в Simple home?** (Очакван: реални ai_insights, header унифициран, filter работи, info-box 3 нива, list view, helper chips → chat handoff)

7. **Какъв е статусът на AI Studio?** (Очакван: ~70%, ОТДЕЛНА страница, 3 типа обработка: bg removal + magic clothes + magic objects)

**Ако сбъркаш 2+ → прочети файла отново. Без правилни отговори не започваш работа.**

---

# ЧАСТ 10 — РАЗГОВОРЕН ПРОТОКОЛ

Тих е founder, **НЕ developer**. Говори с него:
- На български, кратко, директно
- Никога "Може би" / "Сигурен ли си" / "Готов ли си"
- Технически решения (скриптове, git, backup) → действай сам
- Логически/продуктови решения (UX, текстове, задължителни полета) → ПИТАЙ
- Caps = urgency/frustration. Не реагирай защитно.
- Когато Тих каже "ти луд ли си" → значи си забравил важен контекст. Спри и преразгледай.

**60% плюсове + 40% критика. Никога 100% ентусиазъм.**

---

## END HANDOFF

Това е всичко. Всичко важно за S145 е тук. БЕЗ нужда от Тих да пуска S142 / Compass / design system.

**Последен push:** commit `c990e70` (15.05.2026 EOD).

**Ако S145 не разбере нещо → провере първо този файл, после repo grep, после питай Тих.**

— Шеф-чат S144 → S145
