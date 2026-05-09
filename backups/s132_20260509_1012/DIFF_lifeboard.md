1,4c1,3
< # INVENTORY (pre-rewrite) — life-board.php
< ## Дата: Sat May  9 10:13:07 UTC 2026
< ## Commit: fe9163ac8e1040f80b8ee7227c496dcc7597c51e
< ## File size: 1564 life-board.php lines
---
> # INVENTORY (post-rewrite) — life-board.php
> ## Дата: Sat May  9 10:24:47 UTC 2026
> ## File size: 1473 life-board.php lines
7,12c6,18
< 81:function lbWmoSvg($code) {
< 86:function lbWmoText($code) {
< 145:function lbInsightAction(array $ins): array {
< 1486:function lbToggleCard(e, row){
< 1493:function lbSelectFeedback(e, btn){
< 1501:function lbOpenChat(e, q){
---
> 100:function lbWmoSvg($code) {
> 105:function lbWmoText($code) {
> 116:function lbWmoClass(int $code): string {
> 124:function lbWmoDayIcon(int $code): string {
> 139:function lbDayName(string $date_str, bool $today_first = false): string {
> 145:function lbWith(?string $url): string {
> 211:function lbInsightAction(array $ins): array {
> 1374:function openInfo(key) {
> 1385:function closeInfo() {
> 1389:function wfcSetRange(r) {
> 1395:function lbToggleCard(e, row){
> 1402:function lbSelectFeedback(e, btn){
> 1410:function lbOpenChat(e, q){
17,27c23,34
< 27:    $chk = DB::run('SELECT id FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
< 34:    $first = DB::run('SELECT id FROM stores WHERE tenant_id=? ORDER BY id LIMIT 1', [$tenant_id])->fetch();
< 38:$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
< 42:$store = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1', [$store_id, $tenant_id])->fetch();
< 44:$all_stores = DB::run('SELECT id, name FROM stores WHERE tenant_id=? ORDER BY name', [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
< 53:    'SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)=? AND status!="canceled"',
< 56:    'SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)=? AND status!="canceled"',
< 59:    'SELECT COUNT(*) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)=? AND status!="canceled"',
< 64:        'SELECT COALESCE(SUM(si.quantity*(si.unit_price - COALESCE(si.cost_price,0))),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at)=? AND s.status!="canceled"',
< 77:        'SELECT temp_max, temp_min, precipitation_prob, weather_code FROM weather_forecast WHERE store_id=? AND forecast_date=CURDATE() LIMIT 1',
< 100:        "SELECT COUNT(*) FROM products
---
> 38:    $chk = DB::run('SELECT id FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
> 45:    $first = DB::run('SELECT id FROM stores WHERE tenant_id=? ORDER BY id LIMIT 1', [$tenant_id])->fetch();
> 49:$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
> 53:$store = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1', [$store_id, $tenant_id])->fetch();
> 55:$all_stores = DB::run('SELECT id, name FROM stores WHERE tenant_id=? ORDER BY name', [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
> 64:    'SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)=? AND status!="canceled"',
> 67:    'SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)=? AND status!="canceled"',
> 70:    'SELECT COUNT(*) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)=? AND status!="canceled"',
> 75:        'SELECT COALESCE(SUM(si.quantity*(si.unit_price - COALESCE(si.cost_price,0))),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at)=? AND s.status!="canceled"',
> 88:        'SELECT temp_max, temp_min, precipitation_prob, weather_code FROM weather_forecast WHERE store_id=? AND forecast_date=CURDATE() LIMIT 1',
> 96:        'SELECT forecast_date, temp_max, temp_min, precipitation_prob, weather_code FROM weather_forecast WHERE store_id=? AND forecast_date >= CURDATE() ORDER BY forecast_date ASC LIMIT 14',
> 156:        "SELECT COUNT(*) FROM products
34,41c41,63
< 1326:            <select class="lb-store-picker" onchange="location.href='?store='+this.value" aria-label="Магазин">
< 1385:            <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
< 1398:                    <button type="button" class="lb-action s87v3-tap" onclick="lbOpenChat(event,'<?= $title_js ?>')">Защо?</button>
< 1403:                    <button type="button" class="lb-action s87v3-tap" onclick="lbOpenChat(event,'<?= $title_js ?>')">Покажи</button>
< 1404:                    <button type="button" class="lb-action primary s87v3-tap" onclick="lbOpenChat(event,'<?= $title_js ?>')"><?= htmlspecialchars($action['label']) ?> →</button>
< 1409:                    <button type="button" class="lb-fb-btn s87v3-tap" data-fb="up" onclick="lbSelectFeedback(event,this)" aria-label="Полезно">👍</button>
< 1410:                    <button type="button" class="lb-fb-btn s87v3-tap" data-fb="down" onclick="lbSelectFeedback(event,this)" aria-label="Безполезно">👎</button>
< 1411:                    <button type="button" class="lb-fb-btn s87v3-tap" data-fb="hmm" onclick="lbSelectFeedback(event,this)" aria-label="Неясно">🤔</button>
---
> 1013:        <select class="lb-store-picker" onchange="location.href='?store='+this.value" aria-label="Магазин">
> 1059:        <button type="button" class="op-info-btn" onclick="event.preventDefault();event.stopPropagation();openInfo('sell')" aria-label="Инфо">
> 1069:        <button type="button" class="op-info-btn" onclick="event.preventDefault();event.stopPropagation();openInfo('inventory')" aria-label="Инфо">
> 1079:        <button type="button" class="op-info-btn" onclick="event.preventDefault();event.stopPropagation();openInfo('delivery')" aria-label="Инфо">
> 1089:        <button type="button" class="op-info-btn" onclick="event.preventDefault();event.stopPropagation();openInfo('order')" aria-label="Инфо">
> 1138:      <button type="button" class="wfc-tab" data-tab="3" onclick="wfcSetRange('3')">3д</button>
> 1139:      <button type="button" class="wfc-tab" data-tab="7" onclick="wfcSetRange('7')">7д</button>
> 1140:      <button type="button" class="wfc-tab" data-tab="14" onclick="wfcSetRange('14')">14д</button>
> 1220:      <button type="button" class="help-chip s87v3-tap" onclick="lbOpenChat(event,'Какво ми тежи на склада')"><span class="help-chip-q">?</span><span>Какво ми тежи на склада</span></button>
> 1221:      <button type="button" class="help-chip s87v3-tap" onclick="lbOpenChat(event,'Кои са топ продавачи')"><span class="help-chip-q">?</span><span>Кои са топ продавачи</span></button>
> 1222:      <button type="button" class="help-chip s87v3-tap" onclick="lbOpenChat(event,'Колко да поръчам')"><span class="help-chip-q">?</span><span>Колко да поръчам</span></button>
> 1223:      <button type="button" class="help-chip s87v3-tap" onclick="lbOpenChat(event,'Защо приходите паднаха')"><span class="help-chip-q">?</span><span>Защо приходите паднаха</span></button>
> 1224:      <button type="button" class="help-chip s87v3-tap" onclick="lbOpenChat(event,'Покажи ми Adidas 42')"><span class="help-chip-q">?</span><span>Покажи Adidas 42</span></button>
> 1225:      <button type="button" class="help-chip s87v3-tap" onclick="lbOpenChat(event,'Какво продаваме днес')"><span class="help-chip-q">?</span><span>Какво продаваме днес</span></button>
> 1263:      <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
> 1276:          <button type="button" class="lb-action s87v3-tap" onclick="lbOpenChat(event,'<?= $title_js ?>')">Защо?</button>
> 1281:          <button type="button" class="lb-action s87v3-tap" onclick="lbOpenChat(event,'<?= $title_js ?>')">Покажи</button>
> 1282:          <button type="button" class="lb-action primary s87v3-tap" onclick="lbOpenChat(event,'<?= $title_js ?>')"><?= htmlspecialchars($action['label']) ?> →</button>
> 1287:          <button type="button" class="lb-fb-btn s87v3-tap" data-fb="up" onclick="lbSelectFeedback(event,this)" aria-label="Полезно">👍</button>
> 1288:          <button type="button" class="lb-fb-btn s87v3-tap" data-fb="down" onclick="lbSelectFeedback(event,this)" aria-label="Безполезно">👎</button>
> 1289:          <button type="button" class="lb-fb-btn s87v3-tap" data-fb="hmm" onclick="lbSelectFeedback(event,this)" aria-label="Неясно">🤔</button>
> 1310:<div class="info-overlay" id="infoOverlay" onclick="if(event.target===this)closeInfo()">
> 1315:      <button type="button" class="info-card-close" onclick="closeInfo()" aria-label="Затвори">
48a71
> $_SESSION['ui_mode']
49a73
> $_SESSION['user_role']
55,59c79,83
< href="/ai-studio.php"
< href="/chat.php"
< href="/chat.php#all"
< href="/products.php"
< href="/sale.php"
---
> href="<?= htmlspecialchars(lbWith('/ai-studio.php')) ?>"
> href="<?= htmlspecialchars(lbWith('/chat.php#all')) ?>"
> href="<?= htmlspecialchars(lbWith('/chat.php')) ?>"
> href="<?= htmlspecialchars(lbWith('/products.php')) ?>"
> href="<?= htmlspecialchars(lbWith('/sale.php')) ?>"
61a86,92
> id="infoBody"
> id="infoCta"
> id="infoCtaLabel"
> id="infoIc"
> id="infoOverlay"
> id="infoTitle"
> id="infoVoice"
