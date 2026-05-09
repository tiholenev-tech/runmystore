1,4c1,3
< # INVENTORY (pre-rewrite) — chat.php
< ## Дата: Sat May  9 10:49:36 UTC 2026
< ## Commit: fe9163ac8e1040f80b8ee7227c496dcc7597c51e
< ## File size: 1642 chat.php
---
> # INVENTORY (post-rewrite) — chat.php
> ## Дата: Sat May  9 11:16:18 UTC 2026
> ## File size: 2012 chat.php
7,51c6,59
< 112:function wmoSvg($code) {
< 118:function wmoText($code) {
< 148:function periodData($tid, $sid, $r, $from, $to = null) {
< 164:function cmpPct($a, $b) { return $b > 0 ? round(($a - $b) / $b * 100) : ($a > 0 ? 100 : 0); }
< 165:function mgn($p) { return $p['rev'] > 0 ? round($p['profit'] / $p['rev'] * 100) : 0; }
< 278:function insightAction(array $ins): array {
< 329:function insightUICategory(array $ins): string {
< 384:function urgencyClass(string $u): string {
< 836:function toggleTheme(){
< 867:function esc(s) {
< 873:function fmt(n) { return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
< 874:function showToast(m) {
< 878:function vib(n) { if (navigator.vibrate) navigator.vibrate(n || 6); }
< 887:function updateRevenue() {
< 920:function setPeriod(period, el) {
< 933:function lbSelectFeedback(e, btn){
< 940:function lbDismissCard(e, btn){
< 950:function setMode(mode) {
< 962:function toggleLogout(e) {
< 975:function _openBody() { document.body.classList.add('overlay-open'); }
< 976:function _closeBody() { if (!OV.chat && !OV.sig && !OV.br) document.body.classList.remove('overlay-open'); }
< 979:function openChat() {
< 992:function closeChat(skipHistory) {
< 1003:function openChatQ(question) {
< 1012:function scrollChatBottom() {
< 1018:function openSignalDetail(idx) {
< 1109:function closeSignalDetail(skipHistory) {
< 1118:function addToOrderDraft(idx) {
< 1127:function openSignalBrowser() {
< 1178:function closeSignalBrowser(skipHistory) {
< 1257:function addUserBubble(txt) {
< 1267:function addAIBubble(txt, actions) {
< 1292:function toggleVoice() {
< 1333:function stopVoice() {
< 1345:function markInsightShown(topicId, action, category, pid) {
< 1358:function proactivePillTap(el, title) {
< 1393:function animateCountUp(el, finalValue, duration) {
< 1399:    function tick(now) {
< 1466:function addMessageWithAnimation(role, txt){
< 1489:function animateNumberChange(el, newValue, duration){
< 1495:    function tick(now){
< 1505:function bounceBadge(el){
< 1516:    function wrapClose(name, panelId){
< 1568:function changeContext(targetUrl){
< 1599:function spawnTopPill(html){
---
> 120:function wmoSvg($code) {
> 126:function wmoText($code) {
> 137:function wfcDayClass(int $code): string {
> 145:function wfcDayIcon(int $code): string {
> 160:function wfcDayName(string $date_str, bool $is_today): string {
> 166:function chatLink(?string $url): string {
> 175:function fqModuleLabel(?string $cat, ?string $module): string {
> 212:function periodData($tid, $sid, $r, $from, $to = null) {
> 228:function cmpPct($a, $b) { return $b > 0 ? round(($a - $b) / $b * 100) : ($a > 0 ? 100 : 0); }
> 229:function mgn($p) { return $p['rev'] > 0 ? round($p['profit'] / $p['rev'] * 100) : 0; }
> 342:function insightAction(array $ins): array {
> 390:function insightUICategory(array $ins): string {
> 444:function urgencyClass(string $u): string {
> 1183:function toggleTheme(){
> 1214:function esc(s) {
> 1220:function fmt(n) { return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
> 1221:function showToast(m) {
> 1225:function vib(n) { if (navigator.vibrate) navigator.vibrate(n || 6); }
> 1234:function updateRevenue() {
> 1266:function setPeriod(period, el) {
> 1277:function lbSelectFeedback(e, btn){
> 1284:function lbDismissCard(e, btn){
> 1295:function lbToggleCard(e, row) {
> 1306:function setMode(mode) {
> 1318:function toggleLogout(e) {
> 1335:function _openBody() { document.body.classList.add('overlay-open'); }
> 1336:function _closeBody() { if (!OV.chat && !OV.sig && !OV.br) document.body.classList.remove('overlay-open'); }
> 1339:function openChat() {
> 1352:function closeChat(skipHistory) {
> 1363:function openChatQ(question) {
> 1372:function scrollChatBottom() {
> 1378:function openSignalDetail(idx) {
> 1462:function closeSignalDetail(skipHistory) {
> 1471:function addToOrderDraft(idx) {
> 1479:function openSignalBrowser() {
> 1530:function closeSignalBrowser(skipHistory) {
> 1608:function addUserBubble(txt) {
> 1618:function addAIBubble(txt, actions) {
> 1643:function toggleVoice() {
> 1684:function stopVoice() {
> 1696:function markInsightShown(topicId, action, category, pid) {
> 1709:function proactivePillTap(el, title) {
> 1726:function openInfo(key) {
> 1736:function closeInfo() {
> 1741:function wfcSetRange(r) {
> 1780:function animateCountUp(el, finalValue, duration) {
> 1786:    function tick(now) {
> 1846:function addMessageWithAnimation(role, txt){
> 1867:function animateNumberChange(el, newValue, duration){
> 1873:    function tick(now){
> 1883:function bounceBadge(el){
> 1892:    function wrapClose(name, panelId){
> 1941:function changeContext(targetUrl){
> 1969:function spawnTopPill(html){
53c61
< ## AJAX endpoints (action|do params)
---
> ## AJAX endpoints
56,70c64,78
< 28:    $chk = DB::run('SELECT id FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
< 36:    $first = DB::run('SELECT id FROM stores WHERE tenant_id=? ORDER BY id LIMIT 1', [$tenant_id])->fetch();
< 43:$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
< 47:$store = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1', [$store_id, $tenant_id])->fetch();
< 49:$all_stores = DB::run('SELECT id, name FROM stores WHERE tenant_id=? ORDER BY name', [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
< 62:        'SELECT temp_max, temp_min, precipitation_prob, weather_code FROM weather_forecast WHERE store_id=? AND forecast_date=CURDATE() LIMIT 1',
< 65:        'SELECT forecast_date, temp_max, temp_min, precipitation_prob, weather_code FROM weather_forecast WHERE store_id=? AND forecast_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 13 DAY) ORDER BY forecast_date',
< 137:        "SELECT COUNT(*) FROM products
< 151:        'SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=? AND status!="canceled"',
< 154:        'SELECT COUNT(*) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=? AND status!="canceled"',
< 159:            'SELECT COALESCE(SUM(si.quantity*(si.unit_price - COALESCE(si.cost_price,0))),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at)>=? AND DATE(s.created_at)<=? AND s.status!="canceled"',
< 211:$total_products = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1', [$tenant_id])->fetchColumn();
< 212:$with_cost = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND cost_price>0', [$tenant_id])->fetchColumn();
< 256:        $sql = "SELECT i.id, i.topic_id, i.fundamental_question, i.title,
< 404:    'SELECT role, content, created_at FROM chat_messages WHERE tenant_id=? AND store_id=? ORDER BY created_at ASC LIMIT 50',
---
> 36:    $chk = DB::run('SELECT id FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
> 44:    $first = DB::run('SELECT id FROM stores WHERE tenant_id=? ORDER BY id LIMIT 1', [$tenant_id])->fetch();
> 51:$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
> 55:$store = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1', [$store_id, $tenant_id])->fetch();
> 57:$all_stores = DB::run('SELECT id, name FROM stores WHERE tenant_id=? ORDER BY name', [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
> 70:        'SELECT temp_max, temp_min, precipitation_prob, weather_code FROM weather_forecast WHERE store_id=? AND forecast_date=CURDATE() LIMIT 1',
> 73:        'SELECT forecast_date, temp_max, temp_min, precipitation_prob, weather_code FROM weather_forecast WHERE store_id=? AND forecast_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 13 DAY) ORDER BY forecast_date',
> 201:        "SELECT COUNT(*) FROM products
> 215:        'SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=? AND status!="canceled"',
> 218:        'SELECT COUNT(*) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=? AND status!="canceled"',
> 223:            'SELECT COALESCE(SUM(si.quantity*(si.unit_price - COALESCE(si.cost_price,0))),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at)>=? AND DATE(s.created_at)<=? AND s.status!="canceled"',
> 275:$total_products = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1', [$tenant_id])->fetchColumn();
> 276:$with_cost = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND cost_price>0', [$tenant_id])->fetchColumn();
> 320:        $sql = "SELECT i.id, i.topic_id, i.fundamental_question, i.title,
> 464:    'SELECT role, content, created_at FROM chat_messages WHERE tenant_id=? AND store_id=? ORDER BY created_at ASC LIMIT 50',
72c80
< ## Form field names
---
> ## Form names
77,121c85,143
< 459:                <select onchange="location.href='?store='+this.value" aria-label="Магазин">
< 484:            <button type="button" class="s82-dash-pill rev-pill active" onclick="setPeriod('today',this)">Днес</button>
< 485:            <button type="button" class="s82-dash-pill rev-pill" onclick="setPeriod('7d',this)">7 дни</button>
< 486:            <button type="button" class="s82-dash-pill rev-pill" onclick="setPeriod('30d',this)">30 дни</button>
< 487:            <button type="button" class="s82-dash-pill rev-pill" onclick="setPeriod('365d',this)">365 дни</button>
< 490:            <button type="button" class="s82-dash-pill rev-pill active" id="modeRev" onclick="setMode('rev')">Оборот</button>
< 491:            <button type="button" class="s82-dash-pill rev-pill" id="modeProfit" onclick="setMode('profit')">Печалба</button>
< 509:        <span class="health-link" onclick="openChatQ('Как да подобря AI точността?')">Преброй &rarr;</span>
< 510:        <span class="health-info" onclick="document.querySelector('.health-tooltip').classList.toggle('open')" aria-label="Какво е AI точност?">
< 619:            <button type="button" class="lb-dismiss" aria-label="Скрий" onclick="lbDismissCard(event,this)">×</button>
< 626:            <button type="button" class="lb-action" onclick="openChatQ('<?= $title_js ?>')">Защо?</button>
< 627:            <button type="button" class="lb-action" onclick="openSignalDetail(<?= $idx_in_all ?>)">Покажи</button>
< 631:            <button type="button" class="lb-action primary" onclick="addToOrderDraft(<?= $idx_in_all ?>)"><?= htmlspecialchars($action['label']) ?> →</button>
< 633:            <button type="button" class="lb-action primary" onclick="openChatQ('<?= $title_js ?>')"><?= htmlspecialchars($action['label']) ?> →</button>
< 638:            <button type="button" class="lb-fb-btn" data-fb="up" onclick="lbSelectFeedback(event,this)" aria-label="Полезно">👍</button>
< 639:            <button type="button" class="lb-fb-btn" data-fb="down" onclick="lbSelectFeedback(event,this)" aria-label="Безполезно">👎</button>
< 640:            <button type="button" class="lb-fb-btn" data-fb="hmm" onclick="lbSelectFeedback(event,this)" aria-label="Неясно">🤔</button>
< 645:    <div class="lb-see-more"><button type="button" onclick="openSignalBrowser()">Виж още <?= $remaining ?> теми →</button></div>
< 655:        <div style="margin-top:10px"><button type="button" class="lb-action primary" onclick="showToast('Включи PRO за AI съвети')" style="padding:8px 18px">Включи PRO</button></div>
< 684:<div class="ov-bg" id="chatBg" onclick="closeChat()"></div>
< 688:        <button class="ov-back" onclick="closeChat()" title="Назад">
< 700:        <button class="ov-close" onclick="closeChat()" title="Затвори">
< 749:                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}"></textarea>
< 750:            <div class="chat-mic" id="micBtn" onclick="toggleVoice()">
< 756:            <button class="chat-send" id="chatSend" onclick="sendMsg()" disabled>
< 766:<div class="ov-bg" id="sigBg" onclick="closeSignalDetail()"></div>
< 770:        <button class="ov-back" onclick="closeSignalDetail()" title="Назад">
< 780:        <button class="ov-close" onclick="closeSignalDetail()" title="Затвори">
< 790:<div class="ov-bg" id="brBg" onclick="closeSignalBrowser()"></div>
< 794:        <button class="ov-back" onclick="closeSignalBrowser()" title="Назад">
< 806:        <button class="ov-close" onclick="closeSignalBrowser()" title="Затвори">
< 1066:            h += '<button class="sig-btn-primary" onclick="location.href=\'' + esc(s.action.url) + '\'">' + esc(s.action.label) + '</button>';
< 1068:            h += '<button class="sig-btn-primary" onclick="addToOrderDraft(' + idx + ')">' + esc(s.action.label) + '</button>';
< 1070:            h += '<button class="sig-btn-primary" onclick="closeSignalDetail();setTimeout(function(){openChatQ(\'' + esc(s.title) + '\')},400)">' + esc(s.action.label) + '</button>';
< 1073:    h += '<button class="sig-btn-secondary" onclick="closeSignalDetail();setTimeout(function(){openChatQ(\'' + esc(s.title) + '\')},400)">Попитай AI</button>';
< 1160:            h += '<div class="sig-card ' + u + '" style="margin:4px 0" onclick="closeSignalBrowser();setTimeout(function(){openSignalDetail(' + idx + ')},400)">'
< 1278:            h += '<button class="action-button" onclick="window.open(\'' + esc(a.url || '#') + '\',\'_blank\')">' + esc(a.label) + ' \u2192</button>';
< 
< ## Включени файлове (require/include)
< 15:require_once __DIR__ . '/config/database.php';
< 16:require_once __DIR__ . '/config/helpers.php';
< 433:    <?php include __DIR__ . '/design-kit/partial-header.html'; ?>
< 674:<?php include __DIR__ . '/partials/chat-input-bar.php'; ?>
< 679:<?php include __DIR__ . '/design-kit/partial-bottom-nav.html'; ?>
< 1635:<?php include __DIR__ . '/partials/shell-scripts.php'; ?>
---
> 676:                <select onchange="location.href='?store='+this.value" aria-label="Магазин">
> 701:            <button type="button" class="s82-dash-pill rev-pill active" onclick="setPeriod('today',this)">Днес</button>
> 702:            <button type="button" class="s82-dash-pill rev-pill" onclick="setPeriod('7d',this)">7 дни</button>
> 703:            <button type="button" class="s82-dash-pill rev-pill" onclick="setPeriod('30d',this)">30 дни</button>
> 704:            <button type="button" class="s82-dash-pill rev-pill" onclick="setPeriod('365d',this)">365 дни</button>
> 707:            <button type="button" class="s82-dash-pill rev-pill active" id="modeRev" onclick="setMode('rev')">Оборот</button>
> 708:            <button type="button" class="s82-dash-pill rev-pill" id="modeProfit" onclick="setMode('profit')">Печалба</button>
> 725:        <span class="health-link" onclick="openChatQ('Как да подобря AI точността?')">Преброй &rarr;</span>
> 726:        <span class="health-info" onclick="document.querySelector('.health-tooltip').classList.toggle('open')" aria-label="Какво е AI точност?">
> 780:            <button type="button" class="wfc-tab" data-tab="3" onclick="wfcSetRange('3')" style="flex:1;height:28px;border-radius:999px;font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:800;letter-spacing:0.04em;color:var(--text-muted);border:none;background:transparent">3д</button>
> 781:            <button type="button" class="wfc-tab" data-tab="7" onclick="wfcSetRange('7')" style="flex:1;height:28px;border-radius:999px;font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:800;letter-spacing:0.04em;color:var(--text-muted);border:none;background:transparent">7д</button>
> 782:            <button type="button" class="wfc-tab" data-tab="14" onclick="wfcSetRange('14')" style="flex:1;height:28px;border-radius:999px;font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:800;letter-spacing:0.04em;color:var(--text-muted);border:none;background:transparent">14д</button>
> 840:            <button type="button" class="help-chip" onclick="openChatQ('Какво ми тежи на склада')" style="padding:7px 12px;border-radius:999px;font-size:11.5px;font-weight:700;color:var(--text);border:none;display:inline-flex;align-items:center;gap:5px;background:var(--surface);box-shadow:var(--shadow-card-sm)"><span style="font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:900;color:var(--magic,oklch(0.65 0.25 310))">?</span><span>Какво ми тежи на склада</span></button>
> 841:            <button type="button" class="help-chip" onclick="openChatQ('Кои са топ продавачи')" style="padding:7px 12px;border-radius:999px;font-size:11.5px;font-weight:700;color:var(--text);border:none;display:inline-flex;align-items:center;gap:5px;background:var(--surface);box-shadow:var(--shadow-card-sm)"><span style="font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:900;color:var(--magic,oklch(0.65 0.25 310))">?</span><span>Кои са топ продавачи</span></button>
> 842:            <button type="button" class="help-chip" onclick="openChatQ('Колко да поръчам')" style="padding:7px 12px;border-radius:999px;font-size:11.5px;font-weight:700;color:var(--text);border:none;display:inline-flex;align-items:center;gap:5px;background:var(--surface);box-shadow:var(--shadow-card-sm)"><span style="font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:900;color:var(--magic,oklch(0.65 0.25 310))">?</span><span>Колко да поръчам</span></button>
> 843:            <button type="button" class="help-chip" onclick="openChatQ('Защо приходите паднаха')" style="padding:7px 12px;border-radius:999px;font-size:11.5px;font-weight:700;color:var(--text);border:none;display:inline-flex;align-items:center;gap:5px;background:var(--surface);box-shadow:var(--shadow-card-sm)"><span style="font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:900;color:var(--magic,oklch(0.65 0.25 310))">?</span><span>Защо приходите паднаха</span></button>
> 844:            <button type="button" class="help-chip" onclick="openChatQ('Покажи Adidas 42')" style="padding:7px 12px;border-radius:999px;font-size:11.5px;font-weight:700;color:var(--text);border:none;display:inline-flex;align-items:center;gap:5px;background:var(--surface);box-shadow:var(--shadow-card-sm)"><span style="font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:900;color:var(--magic,oklch(0.65 0.25 310))">?</span><span>Покажи Adidas 42</span></button>
> 845:            <button type="button" class="help-chip" onclick="openChatQ('Какво продаваме днес')" style="padding:7px 12px;border-radius:999px;font-size:11.5px;font-weight:700;color:var(--text);border:none;display:inline-flex;align-items:center;gap:5px;background:var(--surface);box-shadow:var(--shadow-card-sm)"><span style="font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:900;color:var(--magic,oklch(0.65 0.25 310))">?</span><span>Какво продаваме днес</span></button>
> 872:        <button type="button" class="fp-pill active" onclick="openSignalBrowser()" style="flex:0 0 auto;height:32px;padding:0 14px;border-radius:999px;display:inline-flex;align-items:center;gap:5px;font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:800;letter-spacing:0.04em;color:white;border:none;background:linear-gradient(135deg,var(--accent),var(--accent-2));box-shadow:0 4px 12px hsl(255 80% 50% / 0.4);white-space:nowrap">
> 887:        <button type="button" class="fp-pill" onclick="openSignalBrowser()" style="flex:0 0 auto;height:32px;padding:0 14px;border-radius:999px;display:inline-flex;align-items:center;gap:5px;font-family:'DM Mono',ui-monospace,monospace;font-size:10px;font-weight:800;letter-spacing:0.04em;color:var(--text-muted);border:none;background:var(--surface);box-shadow:var(--shadow-card-sm);white-space:nowrap">
> 925:        <div class="lb-collapsed" onclick="lbToggleCard(event,this)" style="display:flex;align-items:center;gap:10px;position:relative;z-index:5">
> 934:            <button type="button" class="lb-dismiss" aria-label="Скрий" onclick="lbDismissCard(event,this)" style="width:22px;height:22px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;background:transparent;border:none;color:var(--text-faint);font-size:14px;line-height:1">×</button>
> 941:                <button type="button" class="lb-action" onclick="openChatQ('<?= $title_js ?>')">Защо?</button>
> 942:                <button type="button" class="lb-action" onclick="openSignalDetail(<?= $idx_in_all ?>)">Покажи</button>
> 946:                <button type="button" class="lb-action primary" onclick="addToOrderDraft(<?= $idx_in_all ?>)"><?= htmlspecialchars($action['label']) ?> →</button>
> 948:                <button type="button" class="lb-action primary" onclick="openChatQ('<?= $title_js ?>')"><?= htmlspecialchars($action['label']) ?> →</button>
> 953:                <button type="button" class="lb-fb-btn" data-fb="up" onclick="lbSelectFeedback(event,this)" aria-label="Полезно">👍</button>
> 954:                <button type="button" class="lb-fb-btn" data-fb="down" onclick="lbSelectFeedback(event,this)" aria-label="Безполезно">👎</button>
> 955:                <button type="button" class="lb-fb-btn" data-fb="hmm" onclick="lbSelectFeedback(event,this)" aria-label="Неясно">🤔</button>
> 965:    <div class="lb-see-more"><button type="button" onclick="openSignalBrowser()">Виж още <?= $remaining ?> теми →</button></div>
> 975:        <div style="margin-top:10px"><button type="button" class="lb-action primary" onclick="showToast('Включи PRO за AI съвети')" style="padding:8px 18px">Включи PRO</button></div>
> 1006:<div class="ov-bg" id="chatBg" onclick="closeChat()"></div>
> 1010:        <button class="ov-back" onclick="closeChat()" title="Назад">
> 1022:        <button class="ov-close" onclick="closeChat()" title="Затвори">
> 1071:                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}"></textarea>
> 1072:            <div class="chat-mic" id="micBtn" onclick="toggleVoice()">
> 1078:            <button class="chat-send" id="chatSend" onclick="sendMsg()" disabled>
> 1088:<div class="ov-bg" id="sigBg" onclick="closeSignalDetail()"></div>
> 1092:        <button class="ov-back" onclick="closeSignalDetail()" title="Назад">
> 1102:        <button class="ov-close" onclick="closeSignalDetail()" title="Затвори">
> 1112:<div class="ov-bg" id="brBg" onclick="closeSignalBrowser()"></div>
> 1116:        <button class="ov-back" onclick="closeSignalBrowser()" title="Назад">
> 1128:        <button class="ov-close" onclick="closeSignalBrowser()" title="Затвори">
> 1138:<div class="info-overlay" id="infoOverlay" onclick="if(event.target===this)closeInfo()" style="position:fixed;inset:0;background:rgba(163,177,198,0.5);backdrop-filter:blur(8px);z-index:100;display:none;align-items:center;justify-content:center;padding:16px">
> 1143:            <button type="button" class="info-card-close" onclick="closeInfo()" aria-label="Затвори" style="width:32px;height:32px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;background:var(--surface);box-shadow:var(--shadow-card-sm);border:none">
> 1421:            h += '<button class="sig-btn-primary" onclick="location.href=\'' + esc(s.action.url) + '\'">' + esc(s.action.label) + '</button>';
> 1423:            h += '<button class="sig-btn-primary" onclick="addToOrderDraft(' + idx + ')">' + esc(s.action.label) + '</button>';
> 1425:            h += '<button class="sig-btn-primary" onclick="closeSignalDetail();setTimeout(function(){openChatQ(\'' + esc(s.title) + '\')},400)">' + esc(s.action.label) + '</button>';
> 1428:    h += '<button class="sig-btn-secondary" onclick="closeSignalDetail();setTimeout(function(){openChatQ(\'' + esc(s.title) + '\')},400)">Попитай AI</button>';
> 1512:            h += '<div class="sig-card ' + u + '" style="margin:4px 0" onclick="closeSignalBrowser();setTimeout(function(){openSignalDetail(' + idx + ')},400)">'
> 1629:            h += '<button class="action-button" onclick="window.open(\'' + esc(a.url || '#') + '\',\'_blank\')">' + esc(a.label) + ' →</button>';
> 
> ## Includes
> 23:require_once __DIR__ . '/config/database.php';
> 24:require_once __DIR__ . '/config/helpers.php';
> 604:<?php include __DIR__ . '/design-kit/partial-header.html'; ?>
> 994:<?php include __DIR__ . '/partials/chat-input-bar.php'; ?>
> 1000:<?php include __DIR__ . '/design-kit/partial-bottom-nav.html'; ?>
> 2005:<?php include __DIR__ . '/partials/shell-scripts.php'; ?>
126a149
> $_SESSION['ui_mode']
128a152
> $_SESSION['user_role']
130a155
> $_GET['from']
133,134c158
< ## Hyperlink destinations (href .php)
< href="/ai-studio.php"
---
> ## Hyperlink destinations
135a160
> href="<?= htmlspecialchars(chatLink('/ai-studio.php')) ?>"
137,142c162,168
< ## AI brain / module action integration points
< 228:        $insights = getInsightsForModule($tenant_id, $store_id, $user_id, 'home', $plan, $role);
< 253:$proactive_pills = [];
< 273:        $proactive_pills = DB::run($sql, [$user_id, $tenant_id, $store_id, $role])->fetchAll(PDO::FETCH_ASSOC);
< 275:} catch (Exception $e) { error_log("S79 proactive pills: " . $e->getMessage()); }
< 1358:function proactivePillTap(el, title) {
---
> ## AI brain integration points
> 8: *        AI insights, proactive pills, chat-send.php integration).
> 292:        $insights = getInsightsForModule($tenant_id, $store_id, $user_id, 'home', $plan, $role);
> 317:$proactive_pills = [];
> 337:        $proactive_pills = DB::run($sql, [$user_id, $tenant_id, $store_id, $role])->fetchAll(PDO::FETCH_ASSOC);
> 339:} catch (Exception $e) { error_log("S79 proactive pills: " . $e->getMessage()); }
> 1709:function proactivePillTap(el, title) {
154a181,187
> id="infoBody"
> id="infoCta"
> id="infoCtaLabel"
> id="infoIc"
> id="infoOverlay"
> id="infoTitle"
> id="infoVoice"
