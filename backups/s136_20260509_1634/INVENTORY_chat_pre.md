# INVENTORY — chat.php PRE-rewrite

**File:** chat.php (86642 bytes)
**Branch:** s136-chat-rewrite-v2 @ 1ec559a
**Date:** 2026-05-09T16:34:31Z
**Backup:** backups/s136_20260509_1634/chat.php.bak

Per CLAUDE_CODE_DESIGN_PROMPT v3.0 правило #14. Extracted via grep patterns.
Each section MUST appear unchanged (or with explicit rename) in INVENTORY_chat_post.md.

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
836:function toggleTheme(){
867:function esc(s) {
873:function fmt(n) { return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
874:function showToast(m) {
878:function vib(n) { if (navigator.vibrate) navigator.vibrate(n || 6); }
887:function updateRevenue() {
920:function setPeriod(period, el) {
933:function lbSelectFeedback(e, btn){
940:function lbDismissCard(e, btn){
950:function setMode(mode) {
962:function toggleLogout(e) {
975:function _openBody() { document.body.classList.add('overlay-open'); }
976:function _closeBody() { if (!OV.chat && !OV.sig && !OV.br) document.body.classList.remove('overlay-open'); }
979:function openChat() {
992:function closeChat(skipHistory) {
1003:function openChatQ(question) {
1012:function scrollChatBottom() {
1018:function openSignalDetail(idx) {
1109:function closeSignalDetail(skipHistory) {
1118:function addToOrderDraft(idx) {
1127:function openSignalBrowser() {
1178:function closeSignalBrowser(skipHistory) {
1257:function addUserBubble(txt) {
1267:function addAIBubble(txt, actions) {
1292:function toggleVoice() {
1333:function stopVoice() {
1345:function markInsightShown(topicId, action, category, pid) {
1358:function proactivePillTap(el, title) {
1393:function animateCountUp(el, finalValue, duration) {
1399:    function tick(now) {
1466:function addMessageWithAnimation(role, txt){
1489:function animateNumberChange(el, newValue, duration){
1495:    function tick(now){
1505:function bounceBadge(el){
1516:    function wrapClose(name, panelId){
1568:function changeContext(targetUrl){
1599:function spawnTopPill(html){
```

## 2. AJAX endpoints (action= / do=)
```
1351:                + '&action=' + encodeURIComponent(action || 'shown')
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
459:                <select onchange="location.href='?store='+this.value" aria-label="Магазин">
484:            <button type="button" class="s82-dash-pill rev-pill active" onclick="setPeriod('today',this)">Днес</button>
485:            <button type="button" class="s82-dash-pill rev-pill" onclick="setPeriod('7d',this)">7 дни</button>
486:            <button type="button" class="s82-dash-pill rev-pill" onclick="setPeriod('30d',this)">30 дни</button>
487:            <button type="button" class="s82-dash-pill rev-pill" onclick="setPeriod('365d',this)">365 дни</button>
490:            <button type="button" class="s82-dash-pill rev-pill active" id="modeRev" onclick="setMode('rev')">Оборот</button>
491:            <button type="button" class="s82-dash-pill rev-pill" id="modeProfit" onclick="setMode('profit')">Печалба</button>
509:        <span class="health-link" onclick="openChatQ('Как да подобря AI точността?')">Преброй &rarr;</span>
510:        <span class="health-info" onclick="document.querySelector('.health-tooltip').classList.toggle('open')" aria-label="Какво е AI точност?">
619:            <button type="button" class="lb-dismiss" aria-label="Скрий" onclick="lbDismissCard(event,this)">×</button>
626:            <button type="button" class="lb-action" onclick="openChatQ('<?= $title_js ?>')">Защо?</button>
627:            <button type="button" class="lb-action" onclick="openSignalDetail(<?= $idx_in_all ?>)">Покажи</button>
631:            <button type="button" class="lb-action primary" onclick="addToOrderDraft(<?= $idx_in_all ?>)"><?= htmlspecialchars($action['label']) ?> →</button>
633:            <button type="button" class="lb-action primary" onclick="openChatQ('<?= $title_js ?>')"><?= htmlspecialchars($action['label']) ?> →</button>
638:            <button type="button" class="lb-fb-btn" data-fb="up" onclick="lbSelectFeedback(event,this)" aria-label="Полезно">👍</button>
639:            <button type="button" class="lb-fb-btn" data-fb="down" onclick="lbSelectFeedback(event,this)" aria-label="Безполезно">👎</button>
640:            <button type="button" class="lb-fb-btn" data-fb="hmm" onclick="lbSelectFeedback(event,this)" aria-label="Неясно">🤔</button>
645:    <div class="lb-see-more"><button type="button" onclick="openSignalBrowser()">Виж още <?= $remaining ?> теми →</button></div>
655:        <div style="margin-top:10px"><button type="button" class="lb-action primary" onclick="showToast('Включи PRO за AI съвети')" style="padding:8px 18px">Включи PRO</button></div>
684:<div class="ov-bg" id="chatBg" onclick="closeChat()"></div>
688:        <button class="ov-back" onclick="closeChat()" title="Назад">
700:        <button class="ov-close" onclick="closeChat()" title="Затвори">
748:                oninput="this.style.height='';this.style.height=Math.min(this.scrollHeight,80)+'px';document.getElementById('chatSend').disabled=!this.value.trim()"
749:                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendMsg()}"></textarea>
750:            <div class="chat-mic" id="micBtn" onclick="toggleVoice()">
756:            <button class="chat-send" id="chatSend" onclick="sendMsg()" disabled>
766:<div class="ov-bg" id="sigBg" onclick="closeSignalDetail()"></div>
770:        <button class="ov-back" onclick="closeSignalDetail()" title="Назад">
780:        <button class="ov-close" onclick="closeSignalDetail()" title="Затвори">
790:<div class="ov-bg" id="brBg" onclick="closeSignalBrowser()"></div>
794:        <button class="ov-back" onclick="closeSignalBrowser()" title="Назад">
806:        <button class="ov-close" onclick="closeSignalBrowser()" title="Затвори">
1066:            h += '<button class="sig-btn-primary" onclick="location.href=\'' + esc(s.action.url) + '\'">' + esc(s.action.label) + '</button>';
1068:            h += '<button class="sig-btn-primary" onclick="addToOrderDraft(' + idx + ')">' + esc(s.action.label) + '</button>';
1070:            h += '<button class="sig-btn-primary" onclick="closeSignalDetail();setTimeout(function(){openChatQ(\'' + esc(s.title) + '\')},400)">' + esc(s.action.label) + '</button>';
1073:    h += '<button class="sig-btn-secondary" onclick="closeSignalDetail();setTimeout(function(){openChatQ(\'' + esc(s.title) + '\')},400)">Попитай AI</button>';
1160:            h += '<div class="sig-card ' + u + '" style="margin:4px 0" onclick="closeSignalBrowser();setTimeout(function(){openSignalDetail(' + idx + ')},400)">'
1278:            h += '<button class="action-button" onclick="window.open(\'' + esc(a.url || '#') + '\',\'_blank\')">' + esc(a.label) + ' \u2192</button>';
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

## 7. require / include / require_once / include_once
```
15:require_once __DIR__ . '/config/database.php';
16:require_once __DIR__ . '/config/helpers.php';
433:    <?php include __DIR__ . '/design-kit/partial-header.html'; ?>
674:<?php include __DIR__ . '/partials/chat-input-bar.php'; ?>
679:<?php include __DIR__ . '/design-kit/partial-bottom-nav.html'; ?>
1635:<?php include __DIR__ . '/partials/shell-scripts.php'; ?>
```

## 8. fetch() / XMLHttpRequest / $.ajax JS calls (client-side endpoints)
```
29:        [(int)$_GET['store'], $tenant_id])->fetch();
36:    $first = DB::run('SELECT id FROM stores WHERE tenant_id=? ORDER BY id LIMIT 1', [$tenant_id])->fetch();
43:$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
47:$store = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1', [$store_id, $tenant_id])->fetch();
63:        [$store_id])->fetch(PDO::FETCH_ASSOC);
1237:        const r = await fetch('chat-send.php', {
1347:        fetch('mark-insight-shown.php', {
```

## 9. addEventListener (JS event registrations)
```
826:        document.addEventListener('DOMContentLoaded',function(){
966:document.addEventListener('click', e => {
1188:window.addEventListener('popstate', e => {
1195:document.addEventListener('keydown', e => {
1208:    p.addEventListener('touchstart', e => { _touchY = e.touches[0].clientY; }, { passive: true });
1209:    p.addEventListener('touchend', e => {
1376:window.addEventListener('DOMContentLoaded', () => {
1387:    el.addEventListener('click', () => vib(6));
1416:window.addEventListener('load', () => {
1438:    window.addEventListener('scroll', function(){
1455:    window.addEventListener('load', function(){
1540:        document.addEventListener('DOMContentLoaded', function(){
1629:        el.addEventListener('touchend', () => handler(el), { passive: true });
1630:        el.addEventListener('mouseup', () => handler(el));
```

## 10. PHP echo / print / printf statements
```
Total count: 0 occurrences
```

## 11. Class definitions (PHP/JS)
```
112:function wmoSvg($code) {
118:function wmoText($code) {
148:function periodData($tid, $sid, $r, $from, $to = null) {
164:function cmpPct($a, $b) { return $b > 0 ? round(($a - $b) / $b * 100) : ($a > 0 ? 100 : 0); }
165:function mgn($p) { return $p['rev'] > 0 ? round($p['profit'] / $p['rev'] * 100) : 0; }
278:function insightAction(array $ins): array {
329:function insightUICategory(array $ins): string {
384:function urgencyClass(string $u): string {
836:function toggleTheme(){
866:function $(id) { return document.getElementById(id); }
867:function esc(s) {
873:function fmt(n) { return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
874:function showToast(m) {
878:function vib(n) { if (navigator.vibrate) navigator.vibrate(n || 6); }
887:function updateRevenue() {
920:function setPeriod(period, el) {
933:function lbSelectFeedback(e, btn){
940:function lbDismissCard(e, btn){
950:function setMode(mode) {
962:function toggleLogout(e) {
975:function _openBody() { document.body.classList.add('overlay-open'); }
976:function _closeBody() { if (!OV.chat && !OV.sig && !OV.br) document.body.classList.remove('overlay-open'); }
979:function openChat() {
992:function closeChat(skipHistory) {
1003:function openChatQ(question) {
1012:function scrollChatBottom() {
1018:function openSignalDetail(idx) {
1109:function closeSignalDetail(skipHistory) {
1118:function addToOrderDraft(idx) {
1127:function openSignalBrowser() {
```

## 12. Critical scoped feature markers (if any)
```
_wizPriceParse occurrences:
0
Whisper/STT/voice occurrences:
10: * Закон №1: Пешо не пише нищо — voice/tap/photo only.
750:            <div class="chat-mic" id="micBtn" onclick="toggleVoice()">
751:                <div class="voice-ring vr1"></div>
752:                <div class="voice-ring vr2"></div>
753:                <div class="voice-ring vr3"></div>
998:    stopVoice();
1288:// VOICE
1290:let voiceRec = null, isRecording = false, voiceText = '';
1292:function toggleVoice() {
1293:    if (isRecording) { stopVoice(); return; }
```

## 13. Top-level structure markers
```
1:<?php
409:?><!DOCTYPE html>
410:<html lang="bg">
411:<head>
419:<script>try{if(localStorage.getItem('rms_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(_){}</script>
425:</head>
426:<body class="has-rms-shell mode-detailed">
674:<?php include __DIR__ . '/partials/chat-input-bar.php'; ?>
679:<?php include __DIR__ . '/design-kit/partial-bottom-nav.html'; ?>
815:<script>
1633:</script>
1635:<?php include __DIR__ . '/partials/shell-scripts.php'; ?>
1638:<script src="/design-kit/theme-toggle.js?v=<?= @filemtime(__DIR__.'/design-kit/theme-toggle.js') ?: 1 ?>"></script>
1639:<script src="/design-kit/palette.js?v=<?= @filemtime(__DIR__.'/design-kit/palette.js') ?: 1 ?>"></script>
1641:</body>
1642:</html>
```

## 14. partials/* includes (sacrosanct — must remain)
```
672:<!-- INPUT BAR — moved to partials/chat-input-bar.php       -->
674:<?php include __DIR__ . '/partials/chat-input-bar.php'; ?>
1635:<?php include __DIR__ . '/partials/shell-scripts.php'; ?>
```
