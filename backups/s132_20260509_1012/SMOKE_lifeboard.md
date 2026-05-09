# SMOKE checklist — life-board.php (S132 P10 pilot)

**Тествай ръчно ВСЕКИ ред преди merge. Маркирай [x] като минава.**

## 1. Mode toggle
- [ ] **Бутон "Подробен →"** (line ~1005): Click → expect navigation към `/chat.php?from=lifeboard`

## 2. TOP ROW — Днес + Времето

### Днес (cell qd)
- [ ] **Cell-num се рендира** (line ~1019): Числото е round($rev_today) с currency символ
- [ ] **cell-pct цветен бадж** (line ~1021): `pos`/`neg`/`zero` клас отговаря на cmp_pct sign
- [ ] **Profit показва** (line ~1024): Само ако role=='owner' И profit_today>0
- [ ] **Store picker dropdown** (line ~1013): Показва се само ако count(all_stores)>1; onchange → `?store=N` query reload

### Времето (cell qd)
- [ ] **Weather icon** (line ~1031): `lbWmoSvg($weather_today.weather_code)` правилен SVG
- [ ] **Temp + condition** (line ~1032): round(temp_max)° + lbWmoText(weather_code)
- [ ] **Empty state** (line ~1042): Ако няма weather_today → показва "—" placeholder

## 3. OPS GRID (4 ops buttons)

- [ ] **Продай (q3)** (line ~1056): href=`/sale.php?from=lifeboard`; click → SPA nav
- [ ] **Стоката (qd)** (line ~1066): href=`/products.php?from=lifeboard`
- [ ] **Доставка (q5)** (line ~1076): href=`$op_deliveries_url?from=lifeboard` (orders.php fallback ако /deliveries.php няма)
- [ ] **Поръчка (q2)** (line ~1086): href=`$op_orders_url?from=lifeboard`

### Info popovers (NEW в P10)
- [ ] **Info btn 'sell'** (line ~1059): Click → opens overlay с Продай data; Voice text "Продай 2 черни тениски Nike размер L"; CTA "Отвори продажба" → `/sale.php?from=lifeboard`
- [ ] **Info btn 'inventory'** (line ~1069): Voice "Покажи Nike размер 42"; CTA → /products.php?from=lifeboard
- [ ] **Info btn 'delivery'** (line ~1079): Voice "Снимай фактура от Иватекс"; CTA → /deliveries.php?from=lifeboard (или fallback)
- [ ] **Info btn 'order'** (line ~1089): Voice "Какво да поръчам от Nike"; CTA → /orders.php?from=lifeboard
- [ ] **Info close X** (line ~1315): Click → overlay скрит
- [ ] **Click outside overlay** (line ~1310): event.target===this → closeInfo() извиква
- [ ] **Stop propagation works** (line 1059): Click info-btn НЕ trigger-ва parent <a> навигация

## 4. AI STUDIO ROW

- [ ] **AI Studio link** (line ~1101): href=`/ai-studio.php?from=lifeboard`
- [ ] **Pending count badge** (line ~1115): Когато ai_studio_count>0 → показва число (или "99+" ако >99); studio-sub текст "N ЧАКАТ"
- [ ] **No pending state** (line ~1112): studio-sub = "КАТАЛОГ & СНИМКИ"; badge скрит

## 5. WEATHER FORECAST CARD (NEW P10)

- [ ] **Card render conditional** (line ~1124): Показва се САМО ако `$weather_14` е not empty
- [ ] **3д tab default** (line ~1138): data-range=3 → display само първите 3 wfc-day
- [ ] **7д tab** (line ~1139): wfcSetRange('7') → 7 days visible
- [ ] **14д tab** (line ~1140): 14 days visible (или колкото има в `$weather_14`)
- [ ] **Today day pill** (line ~1149): Първият ден ако forecast_date===today → 'today' клас + "Днес" text
- [ ] **Weather class colors** (line ~1148): sunny/partly/cloudy/rain/storm CSS class по weather_code
- [ ] **Temp format** (line ~1153): `Хd°/Yd` formatting
- [ ] **Rain pct** (line ~1154): `dry` клас ако <30%; иначе син цвят
- [ ] **AI recs section** (line ~1162-1188): 3 recs (window/order/transfer) с placeholder Bulgarian text — **NOTE:** placeholder, чака backend wiring
- [ ] **Source pill timestamp** (line ~1192): "OPEN-METEO · Обновено HH:MM"

## 6. AI HELP CARD (NEW P10)

- [ ] **Help head + title** (line ~1205): "AI ти помага" gradient text
- [ ] **6 chips clickable** (lines ~1220-1225): Click → `lbOpenChat(event, "<question>")` → navigates to `/chat.php?from=lifeboard&q=<encoded>`
- [ ] **Video placeholder** (line ~1232): "Видео урок · Скоро" — visual only, no click action
- [ ] **'Виж всички възможности' link** (line ~1241): href=`/chat.php?from=lifeboard`

## 7. LIFE BOARD — Cards

- [ ] **lb-header count** (line ~1252): "N неща · HH:MM" където N = count($picked)
- [ ] **Empty state** (line ~1294): Ако picked empty → "🌿 Всичко е тихо днес"
- [ ] **lb-card per insight** (line ~1257): Render 0-4 cards based на $picked; class q1/q2/q3/q4/q5/q6 от $meta['q']
- [ ] **lb-collapsed onclick** (line ~1263): Click extends card; ignore if click on `.lb-fb-btn` or `.lb-action`
- [ ] **Expand chevron rotates** (line ~1268): `.lb-card.expanded .lb-expand-btn { transform: rotate(180deg) }`
- [ ] **lb-body shows detail_text** (line ~1271): Само ако ins.detail_text != ''
- [ ] **Защо? button** (line ~1276): onclick → lbOpenChat → /chat.php?from=lifeboard&q=<title>
- [ ] **Покажи action — deeplink** (line ~1278): За action.type=='deeplink' → <a href> към products.php?filter=...&from=lifeboard
- [ ] **Покажи action — chat** (line ~1281): За action.type=='chat' → button + lbOpenChat
- [ ] **Primary CTA (label →)** (line ~1279/1282): Or deeplink href, or chat button
- [ ] **Feedback 👍 selected** (line ~1287): Click → other 2 unselect, this gets `.selected` class
- [ ] **Feedback 👎/🤔** (line ~1288/1289): Same single-select behavior
- [ ] **Vibrate haptic** (line ~1397): navigator.vibrate(6) on toggle, vibrate(8) on feedback (Chrome Android)

## 8. SEE MORE link

- [ ] **'Виж всички N →'** (line ~1292): Показва се само ако remaining_after_picked>0; href=`/chat.php#all?from=lifeboard`

## 9. PARTIALS (поведение на партиалите — не пипано)

- [ ] **Header partial** (line ~999): /partials/header.php — render brand + plan badge + 4 icon buttons
- [ ] **Chat input bar partial** (line ~1342): /partials/chat-input-bar.php — sticky bottom; click → rmsOpenChat() → /chat.php?from=lifeboard
- [ ] **Shell scripts partial** (line ~1343): /partials/shell-scripts.php — theme toggle JS

## 10. NEW BEHAVIOR — session bootstrap

- [ ] **Първо посещение от seller**: $_SESSION['ui_mode'] === 'simple'
- [ ] **Първо посещение от owner/admin**: $_SESSION['ui_mode'] === 'detailed'
- [ ] **Subsequent посещения**: ui_mode НЕ се сменя (idempotent)
- [ ] **Logout/login cycle**: Bootstrap re-runs; ui_mode се сетва според role на новия user

## 11. Theme toggle

- [ ] **Light theme** (default per cookie/localStorage): Neumorphism convex shadows
- [ ] **Dark theme**: SACRED Neon Glass — shine/glow spans видими на лявата страна на cards
- [ ] **Theme toggle preserves UI mode**: switch theme → не сменя $_SESSION['ui_mode']

## 12. Animations + reduced motion

- [ ] **fadeInUp animations** на top-row (0.6s), ops (0.7s), studio (0.8s), wfc (0.85s), help (0.9s)
- [ ] **prefers-reduced-motion: reduce** → ВСИЧКИ анимации са disabled (line ~990)

## 13. Responsive

- [ ] **Max-width 480px** на .app — central column на tablet+ (line ~1042)
- [ ] **Mobile portrait**: Всички cards readable, нищо не се счупи
- [ ] **Mobile landscape**: Top-row остава 1.4fr 1fr grid

## 14. Critical regression checks

- [ ] **lbWmoSvg() работи**: Same output като оригинала за дадени codes (тествай 0,3,48,67,82,99)
- [ ] **lbWmoText() работи**: Same Bulgarian strings за same codes
- [ ] **lbInsightAction() работи**: Same return shape (label/type/url) за всички 6 fundamental_question values
- [ ] **autoGeolocateStore() извикан**: Per existing pattern (line ~58)
- [ ] **планирано plan check работи**: planAtLeast($plan, 'pro') → insights load
- [ ] **NO _wizPriceParse referenced** (zero touch verified)
- [ ] **NO voice STT logic referenced** (zero touch verified — те живеят в други модули)

## Известни placeholder areas (за future sessions)

1. **WFC AI recs** (window/order/transfer) — текстове са placeholder; чакат backend integration със seasonality + supplier data
2. **Help video placeholder** "Скоро" — onboarding video не е заснет
3. **Render guard в destination модули** (?from=lifeboard) — back arrow guard трябва да се добави в техните rewrite сесии (S133+)

---

**Smoke status overall: [ ] PASS / [ ] FAIL — кой items не минават**
