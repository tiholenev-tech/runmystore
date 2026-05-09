# INVENTORY — chat.php POST-rewrite

**File:** chat.php (157704 bytes, 2912 lines)
**Branch:** s136-chat-rewrite-v2 @ 1ec559a
**Date:** 2026-05-09T16:39:44Z

---

## 1. PHP functions
```
112:function wmoSvg($code) {
118:function wmoText($code) {
148:function periodData($tid, $sid, $r, $from, $to = null) {
164:function cmpPct($a, $b) { return $b > 0 ? round(($a - $b) / $b * 100) : ($a > 0 ? 100 : 0); }
165:function mgn($p) { return $p['rev'] > 0 ? round($p['profit'] / $p['rev'] * 100) : 0; }
278:function insightAction(array $ins): array {
329:function insightUICategory(array $ins): string {
384:function urgencyClass(string $u): string {
2045:function openInfo(key) {
2055:function closeInfo() {
2059:function wfcSetRange(r) {
2064:function lbToggleCard(e, row) {
2069:function syncThemeIcons() {
2105:function toggleTheme(){
2136:function esc(s) {
2142:function fmt(n) { return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
2143:function showToast(m) {
2147:function vib(n) { if (navigator.vibrate) navigator.vibrate(n || 6); }
2156:function updateRevenue() {
2189:function setPeriod(period, el) {
2202:function lbSelectFeedback(e, btn){
2209:function lbDismissCard(e, btn){
2219:function setMode(mode) {
2231:function toggleLogout(e) {
2244:function _openBody() { document.body.classList.add('overlay-open'); }
2245:function _closeBody() { if (!OV.chat && !OV.sig && !OV.br) document.body.classList.remove('overlay-open'); }
2248:function openChat() {
2261:function closeChat(skipHistory) {
2272:function openChatQ(question) {
2281:function scrollChatBottom() {
2287:function openSignalDetail(idx) {
2378:function closeSignalDetail(skipHistory) {
2387:function addToOrderDraft(idx) {
2396:function openSignalBrowser() {
2447:function closeSignalBrowser(skipHistory) {
2526:function addUserBubble(txt) {
2536:function addAIBubble(txt, actions) {
2561:function toggleVoice() {
2602:function stopVoice() {
2614:function markInsightShown(topicId, action, category, pid) {
2627:function proactivePillTap(el, title) {
2662:function animateCountUp(el, finalValue, duration) {
2668:    function tick(now) {
2735:function addMessageWithAnimation(role, txt){
2758:function animateNumberChange(el, newValue, duration){
2764:    function tick(now){
2774:function bounceBadge(el){
2785:    function wrapClose(name, panelId){
2837:function changeContext(targetUrl){
2868:function spawnTopPill(html){
```

## 2. AJAX endpoints (action= / do=)
```
2620:                + '&action=' + encodeURIComponent(action || 'shown')
```

## 3. DB queries (SELECT/INSERT/UPDATE/DELETE)
```
28:    $chk = DB::run('SELECT id FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
36:    $first = DB::run('SELECT id FROM stores WHERE tenant_id=? ORDER BY id LIMIT 1', [$tenant_id])->fetch();
43:$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
47:$store = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1', [$store_id, $tenant_id])->fetch();
49:$all_stores = DB::run('SELECT id, name FROM stores WHERE tenant_id=? ORDER BY name', [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
62:        'SELECT temp_max, temp_min, precipitation_prob, weather_code FROM weather_forecast WHERE store_id=? AND forecast_date=CURDATE() LIMIT 1',
65:        'SELECT forecast_date, temp_max, temp_min, precipitation_prob, weather_code FROM weather_forecast WHERE store_id=? AND forecast_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 13 DAY) ORDER BY forecast_date',
137:        "SELECT COUNT(*) FROM products
151:        'SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=? AND status!="canceled"',
154:        'SELECT COUNT(*) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)>=? AND DATE(created_at)<=? AND status!="canceled"',
159:            'SELECT COALESCE(SUM(si.quantity*(si.unit_price - COALESCE(si.cost_price,0))),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at)>=? AND DATE(s.created_at)<=? AND s.status!="canceled"',
211:$total_products = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1', [$tenant_id])->fetchColumn();
212:$with_cost = (int)DB::run('SELECT COUNT(*) FROM products WHERE tenant_id=? AND is_active=1 AND cost_price>0', [$tenant_id])->fetchColumn();
256:        $sql = "SELECT i.id, i.topic_id, i.fundamental_question, i.title,
404:    'SELECT role, content, created_at FROM chat_messages WHERE tenant_id=? AND store_id=? ORDER BY created_at ASC LIMIT 50',
```

## 4. Form names
```
name="theme-color"
name="viewport"
```

## 5. JS event handlers (inline on*=)
```
Total inline handlers: 37
```

## 6. $_SESSION / $_POST / $_GET / $_FILES keys
```
$_GET['store']
$_SESSION['role']
$_SESSION['store_id']
$_SESSION['tenant_id']
$_SESSION['user_id']
$_SESSION['user_name']
```

## 7. require / include
```
15:require_once __DIR__ . '/config/database.php';
16:require_once __DIR__ . '/config/helpers.php';
1295:<?php include __DIR__ . "/design-kit/partial-header.html"; ?>
2009:<?php include __DIR__ . "/design-kit/partial-bottom-nav.html"; ?>
2905:<?php include __DIR__ . '/partials/shell-scripts.php'; ?>
```

## 8. fetch() / XMLHttpRequest / $.ajax
```
29:        [(int)$_GET['store'], $tenant_id])->fetch();
36:    $first = DB::run('SELECT id FROM stores WHERE tenant_id=? ORDER BY id LIMIT 1', [$tenant_id])->fetch();
43:$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
47:$store = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1', [$store_id, $tenant_id])->fetch();
63:        [$store_id])->fetch(PDO::FETCH_ASSOC);
2506:        const r = await fetch('chat-send.php', {
2616:        fetch('mark-insight-shown.php', {
```

## 9. addEventListener
```
Total addEventListener: 14
```

## 12. Critical scoped feature markers
```
_wizPriceParse: 0
Whisper/STT/voice patterns:
1944:            <div class="chat-mic" id="micBtn" onclick="toggleVoice()">
2267:    stopVoice();
2559:let voiceRec = null, isRecording = false, voiceText = '';
2561:function toggleVoice() {
2562:    if (isRecording) { stopVoice(); return; }
2565:    isRecording = true;
2566:    voiceText = '';
2570:    voiceRec = new SR();
2571:    voiceRec.lang = 'bg-BG';
2572:    voiceRec.continuous = false;
```

## 14. partials/* includes
```
2905:<?php include __DIR__ . '/partials/shell-scripts.php'; ?>
```
