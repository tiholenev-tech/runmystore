# INVENTORY (pre-rewrite) — life-board.php
## Дата: Sat May  9 10:13:07 UTC 2026
## Commit: fe9163ac8e1040f80b8ee7227c496dcc7597c51e
## File size: 1564 life-board.php lines

## PHP функции
81:function lbWmoSvg($code) {
86:function lbWmoText($code) {
145:function lbInsightAction(array $ins): array {
1486:function lbToggleCard(e, row){
1493:function lbSelectFeedback(e, btn){
1501:function lbOpenChat(e, q){

## AJAX endpoints (action|do params)

## DB queries
27:    $chk = DB::run('SELECT id FROM stores WHERE id=? AND tenant_id=? LIMIT 1',
34:    $first = DB::run('SELECT id FROM stores WHERE tenant_id=? ORDER BY id LIMIT 1', [$tenant_id])->fetch();
38:$tenant = DB::run('SELECT * FROM tenants WHERE id=? LIMIT 1', [$tenant_id])->fetch();
42:$store = DB::run('SELECT name FROM stores WHERE id=? AND tenant_id=? LIMIT 1', [$store_id, $tenant_id])->fetch();
44:$all_stores = DB::run('SELECT id, name FROM stores WHERE tenant_id=? ORDER BY name', [$tenant_id])->fetchAll(PDO::FETCH_ASSOC);
53:    'SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)=? AND status!="canceled"',
56:    'SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)=? AND status!="canceled"',
59:    'SELECT COUNT(*) FROM sales WHERE tenant_id=? AND store_id=? AND DATE(created_at)=? AND status!="canceled"',
64:        'SELECT COALESCE(SUM(si.quantity*(si.unit_price - COALESCE(si.cost_price,0))),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id WHERE s.tenant_id=? AND s.store_id=? AND DATE(s.created_at)=? AND s.status!="canceled"',
77:        'SELECT temp_max, temp_min, precipitation_prob, weather_code FROM weather_forecast WHERE store_id=? AND forecast_date=CURDATE() LIMIT 1',
100:        "SELECT COUNT(*) FROM products

## Form field names
name="theme-color"
name="viewport"

## JS event handlers
1326:            <select class="lb-store-picker" onchange="location.href='?store='+this.value" aria-label="Магазин">
1385:            <div class="lb-collapsed" onclick="lbToggleCard(event,this)">
1398:                    <button type="button" class="lb-action s87v3-tap" onclick="lbOpenChat(event,'<?= $title_js ?>')">Защо?</button>
1403:                    <button type="button" class="lb-action s87v3-tap" onclick="lbOpenChat(event,'<?= $title_js ?>')">Покажи</button>
1404:                    <button type="button" class="lb-action primary s87v3-tap" onclick="lbOpenChat(event,'<?= $title_js ?>')"><?= htmlspecialchars($action['label']) ?> →</button>
1409:                    <button type="button" class="lb-fb-btn s87v3-tap" data-fb="up" onclick="lbSelectFeedback(event,this)" aria-label="Полезно">👍</button>
1410:                    <button type="button" class="lb-fb-btn s87v3-tap" data-fb="down" onclick="lbSelectFeedback(event,this)" aria-label="Безполезно">👎</button>
1411:                    <button type="button" class="lb-fb-btn s87v3-tap" data-fb="hmm" onclick="lbSelectFeedback(event,this)" aria-label="Неясно">🤔</button>

## Включени файлове (require/include)

## SESSION ключове
$_SESSION['role']
$_SESSION['store_id']
$_SESSION['tenant_id']
$_SESSION['user_id']

## POST/GET ключове
$_GET['store']

## Hyperlink destinations (href .php)
href="/ai-studio.php"
href="/chat.php"
href="/chat.php#all"
href="/products.php"
href="/sale.php"

## IDs
