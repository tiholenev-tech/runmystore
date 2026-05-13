# SESSION_S142_HANDOFF — products-v2.php progress

**Дата:** 12.05.2026
**Статус:** Steps 0-2D готови · pushнати · ready for browser test
**Следва:** Step 3 (browser test) → Step 4 (wizard extract) → Step 5 (AJAX) → Step 6 (polish) → Step 7 (SWAP)

---

## ЗАВЪРШЕНО (5 commits, 1380 → 3074 реда в products-v2.php)

| Commit | Какво | Резултат |
|---|---|---|
| `pre-step2-S142` (tag) | Backup tag | Revert safety net |
| `0eac3fd` | Финални mockup-и в `mockups/` | P15_simple_FINAL + P2_v2_detailed_FINAL approved |
| `1b2360a` | Step 2A: P15+P2v2 mockup HTML inject | Визуално live (всички числа static) |
| `8b72260` | Step 2B+2C: PHP queries + echo replacements | KPI/multi-store/AI feed = реални числа |
| `7a0ab26` | Step 2D: JS handlers (searchInlineMic, lbToggle, tab switch, wrappers) | Voice + click handlers работят |
| `22cfc43` | Bible Закон №6 + DETAILED_SPEC §0 Philosophy | Универсален Simple=signals/Detailed=data pattern |

---

## КАКВО ВЕЧЕ РАБОТИ В products-v2.php

### Simple Mode (?mode=simple) — P15 home за Пешо
- ✅ Header: лого RunMyStore.ai (link → life-board.php) + тема toggle + Продажба бутон
- ✅ Subbar: store dropdown · СТОКАТА МИ · Разширен toggle
- ✅ Inv-nudge pill: `$uncounted_count` артикула не са броени · 12 дни
- ✅ Search bar + filter btn + микрофон (inline recording)
- ✅ "Виж всички N артикула" link (real `$total_products`)
- ✅ "Добави артикул" qa-btn с "Като предния" pill вътре
- ✅ AI Studio row (qm)
- ✅ Help card
- ✅ Multi-store glance (5 stores с PHP foreach · real revenue + trend %)
- ✅ AI feed: 10 различни type сигнали (alerts/weather/transfer/cash/size/wins) — placeholders засега
- ✅ Chat-input-bar floating pill с pulsing mic rings + send drift

### Detailed Mode (?mode=detailed) — P2_v2 home за Митко
- ✅ Header + subbar + tab bar (4 таба: Преглед/Графики/Управление/Артикули)
- ✅ Search bar + Q-chips row (q1-q6 сигнал филтри) + "Виж всички N"
- ✅ Period toggle (Днес/7д/30д/90д) + YoY ✨ бутон
- ✅ Quick actions (Добави + Като предния pill + AI поръчка)
- ✅ **5-KPI scroll row:** Приход / ATV / UPT / Sell-through % / Замразен € — реални PHP echo
- ✅ Tревоги: Свършили / Доставка закъсня — реални числа
- ✅ Cash reconciliation tile (POS/Реално/Разлика + 7-day avg)
- ✅ Weather Forecast Card (7/14 дни tabs + AI препоръка)
- ✅ Health card + Weeks of Supply
- ✅ Sparkline toggle Печеливши↔Застояли
- ✅ Топ 3 за поръчка card (AI quick action)
- ✅ Топ 3 доставчика + reliability score
- ✅ Магазини ranked table + Transfer Dependence (PHP foreach)
- ✅ Графики таб: Pareto + Календар с дати/числа + Margin trend + Sezonnost
- ✅ Bottom-nav 4 orbs (AI/Склад[active]/Справки/Продажба) — пълен chat.php pattern

### JavaScript
- ✅ Voice search (`searchInlineMic`) — 1:1 copy от products.php, sacred
- ✅ lb-card expand/collapse (`lbToggleCard`)
- ✅ Weather 7d/14d toggle (`wfcSetRange`)
- ✅ Tab switching (`rmsSwitchTab` + localStorage)
- ✅ Sparkline winners/losers toggle (`sparkToggle`)
- ✅ Period change (`rmsSetPeriod`)
- ✅ Action wrappers (openAddProduct, openLikePrevious, openAIOrder → проксират към products.php)

### PHP Queries (готови)
```php
$out_of_stock          // INT — свършили артикули в текущ store
$stale_60d             // INT — застояли 60+ дни
$total_products        // INT — общо активни
$uncounted_count       // INT — не броени 30+ дни
$kpi_revenue           // FLOAT — приход за $period_days
$kpi_profit            // FLOAT — печалба (revenue - cogs)
$kpi_atv               // FLOAT — среден чек
$kpi_upt               // FLOAT — артикули per чек
$kpi_margin_pct        // INT — марж %
$kpi_sellthrough       // INT — % продадено от полученото
$kpi_locked_cash       // FLOAT — замразен капитал €
$multistore            // ARRAY — топ 5 stores с trend
$ai_insights           // ARRAY — top 10 active signals
$weather_forecast      // ARRAY — 7-day forecast (от getWeatherForecast)
$top3_reorder          // ARRAY — top 3 за поръчка
$top3_suppliers        // ARRAY — top 3 с reliability score
$delayed_deliveries    // INT — late deliveries count
```

---

## КАКВО ОСТАВА (за следваща сесия)

### Step 3 — BROWSER TEST (Тих)
```bash
ssh root@164.90.217.120
cd /var/www/runmystore
git pull origin main
```

Test URLs:
- `https://runmystore.ai/products-v2.php?mode=simple` — Simple home
- `https://runmystore.ai/products-v2.php?mode=detailed` — Detailed home
- `?mode=detailed&period=30` — 30-day period
- `?store=N` — switch store

**Какво да тестваш:**
- [ ] Layout не чупи на Z Flip6 (373px)
- [ ] Dark + Light режим (theme toggle)
- [ ] Multi-store dropdown работи
- [ ] Search mic започва recording (червен REC pulsing)
- [ ] Lb-cards expand при tap
- [ ] Period change прави reload с new query
- [ ] Bottom-nav orbs анимират в Detailed
- [ ] Числа са разумни за ENI tenant_id=7

**Известни ограничения (още не пипнати):**
- AI feed signals са static placeholders (не от `$ai_insights` array yet)
- Weather days са hardcoded (не от `$weather_forecast` array yet)
- Transfer Dependence % е random (TODO real calculation)
- Top 3 reorder/suppliers cards имат static names (още не от $top3_reorder array)
- Cash reconciliation tile = static (need cash_session DB table query)

### Step 3.5 — Замени останалите static placeholders с PHP echo
1. AI feed → `foreach ($ai_insights as $sig)` с lb-card template
2. Weather days → `foreach ($weather_forecast as $day)`
3. Top 3 reorder cards → `foreach ($top3_reorder as $item)`
4. Top 3 suppliers → `foreach ($top3_suppliers as $sup)`
5. Sparkline list → AJAX endpoint `/products-v2.php?ajax=top5&type=winners|losers`

### Step 4 — Wizard extract (HIGHEST RISK — sacred zone)
Wizard в products.php е редове ~7800-12900 (5000+ реда). НЕ ПРЕПИСВАМЕ — extract в `partials/products-wizard.php`:

```bash
# Python скрипт: extract sed-free, anchor-based
sed -n '7800,12900p' products.php > partials/products-wizard.php
# В products.php replace тези редове с: <?php include 'partials/products-wizard.php'; ?>
# В products-v2.php добави: <?php include 'partials/products-wizard.php'; ?>
```

**Sacred — НЕ ПИПАЙ:**
- `services/voice-tier2.php`
- `services/ai-color-detect.php`
- `js/capacitor-printer.js`
- 8-те mic input полета в wizard
- `_wizMicWhisper`, `_wizPriceParse`, `_bgPrice` функции

### Step 5 — AJAX endpoints
В products-v2.php добави:
```php
// На върха, преди HTML output:
if (!empty($_GET['ajax'])) {
    header('Content-Type: application/json');
    switch ($_GET['ajax']) {
        case 'search':       echo json_encode(searchProductsByName($q)); exit;
        case 'top5':         echo json_encode(getTop5($type)); exit;
        case 'insight_detail': echo json_encode(getInsightById($id)); exit;
        case 'create_order': echo json_encode(createOrderDraft($items)); exit;
        // ...
    }
}
```

Endpoints за live:
- `?ajax=search&q=N42` — autocomplete dropdown
- `?ajax=top5&type=winners` — sparkline switch
- `?ajax=insights&since=hour` — refresh feed
- `?ajax=multistore_refresh` — refresh glance

### Step 6 — Polish + edge cases
- Empty states (нов магазин без продажби)
- Loading spinners за AJAX
- Error handling (DB down, AI API timeout)
- Mobile touch targets ≥ 44px
- Print stylesheet (за Z-отчет)

### Step 7 — SWAP (production cutover)
Преди това:
- 100% parity с products.php
- Тих финално одобрение
- Backup tag `pre-swap-S142`

```bash
git mv products.php products-OLD-archive.php
git mv products-v2.php products.php
git commit -m "S142 SWAP: products-v2.php → products.php (production cutover)"
git push
```

---

## ВАЖНИ ФАЙЛОВЕ ЗА СЛЕДВАЩИЯ ЧАТ

**Прочети ТЕЗИ преди да започнеш:**
1. `docs/BIBLE_v3_0_CORE.md` (Закон №6 е нов)
2. `docs/DETAILED_MODE_SPEC.md` (§0 Philosophy е нов)
3. `mockups/P15_simple_FINAL.html` (canonical)
4. `mockups/P2_v2_detailed_FINAL.html` (canonical)
5. `products-v2.php` (3074 реда — текущ state)
6. `SESSION_S142_HANDOFF.md` (този файл)

**НЕ четеш цял products.php** (14k реда). Чети само:
- Ред 1-200 (auth + setup)
- Ред 4321-4635 (scrHome за reference)
- Ред 5310-5373 (searchInlineMic sacred function)
- Ред 7800-12900 (wizard — за Step 4 extract)

---

## РИСКОВЕ + REVERT

Ако нещо чупи production:
```bash
git reset --hard pre-step2-S142
git push --force origin main
```

Това returnt products-v2.php до shell (Step 0 state). products.php = непокътнат през цялата работа.

---

## STATUS SUMMARY (за бърз read на следващ чат)

✅ DONE: Mockup-и одобрени, products-v2.php има финалния UI live с реални KPI/multi-store queries, JS handlers готови
🟡 NEXT: Тих тества в browser → ако OK → продължи Step 3.5 (още PHP echo loops за AI feed/weather/top3) → Step 4 (wizard extract) → Step 5 (AJAX) → Step 6 (polish) → Step 7 (SWAP)
🔴 RISK: Step 4 (wizard sacred zone) изисква изключителна внимание + backup tag

**Bookmark: workspace clean, 5 commits behind pre-step2-S142 tag. Safe to continue.**
