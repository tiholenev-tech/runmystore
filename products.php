<?php
require_once 'config/database.php';
session_start();

if (!isset($_SESSION['tenant_id'])) {
    header('Location: login.php');
    exit;
}

$tenant_id = $_SESSION['tenant_id'];
$role      = $_SESSION['role'] ?? 'seller';
$search    = trim($_GET['q'] ?? '');

$sql = "
    SELECT 
        p.*,
        c.name AS category_name,
        c.variant_type,
        s.name AS supplier_name,
        (SELECT COUNT(*) FROM products ch WHERE ch.parent_id = p.id AND ch.tenant_id = p.tenant_id) AS variant_count,
        (SELECT COALESCE(SUM(i.quantity),0) FROM inventory i WHERE i.product_id = p.id AND i.tenant_id = p.tenant_id) AS total_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.tenant_id = ? AND p.parent_id IS NULL AND p.is_active = 1
";

$params = [$tenant_id];

if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR p.barcode LIKE ? OR p.code LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY p.name ASC";

$products   = DB::run($sql, $params)->fetchAll();
$categories = DB::run("SELECT id, name, variant_type FROM categories WHERE tenant_id = ? AND parent_id IS NULL ORDER BY name", [$tenant_id])->fetchAll();
$suppliers  = DB::run("SELECT id, name FROM suppliers WHERE tenant_id = ? AND is_active = 1 ORDER BY name", [$tenant_id])->fetchAll();
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>Артикули — RunMyStore.ai</title>
<link rel="stylesheet" href="style.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { background: #0f0f0f; color: #e5e5e5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; min-height: 100vh; }

.nav { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; background: #1a1a1a; border-bottom: 1px solid #2a2a2a; position: sticky; top: 0; z-index: 100; }
.nav-title { font-size: 17px; font-weight: 600; }
.nav-back { color: #a78bfa; font-size: 15px; text-decoration: none; }
.btn-add { background: #a78bfa; color: #fff; border: none; border-radius: 10px; padding: 8px 16px; font-size: 14px; font-weight: 600; cursor: pointer; }

.search-wrap { padding: 12px 16px; background: #1a1a1a; border-bottom: 1px solid #2a2a2a; }
.search-input { width: 100%; background: #2a2a2a; border: 1px solid #3a3a3a; border-radius: 10px; padding: 10px 14px; color: #e5e5e5; font-size: 15px; outline: none; }
.search-input::placeholder { color: #666; }

.stats-bar { display: flex; gap: 12px; padding: 12px 16px; overflow-x: auto; }
.stat-chip { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px; padding: 8px 14px; white-space: nowrap; font-size: 13px; color: #999; }
.stat-chip span { color: #e5e5e5; font-weight: 600; }

.product-list { padding: 8px 16px 100px; }
.product-card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 14px; padding: 14px; margin-bottom: 10px; cursor: pointer; }
.product-card:active { border-color: #a78bfa; }
.product-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.product-name { font-size: 15px; font-weight: 600; line-height: 1.3; }
.product-code { font-size: 12px; color: #666; margin-top: 2px; }
.product-stock { text-align: right; }
.stock-num { font-size: 20px; font-weight: 700; color: #a78bfa; line-height: 1; }
.stock-label { font-size: 11px; color: #666; }
.stock-low { color: #f87171 !important; }
.product-meta { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
.meta-chip { background: #2a2a2a; border-radius: 6px; padding: 3px 8px; font-size: 12px; color: #999; }
.meta-chip.variant { color: #a78bfa; }
.meta-chip.price { color: #34d399; }
.product-bottom { display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding-top: 10px; border-top: 1px solid #2a2a2a; }
.supplier-name { font-size: 12px; color: #666; }
.btn-variants { background: #2a2a2a; border: none; border-radius: 8px; padding: 5px 10px; font-size: 12px; color: #a78bfa; cursor: pointer; }

.empty { text-align: center; padding: 60px 20px; color: #555; }
.empty-icon { font-size: 48px; margin-bottom: 12px; }
.empty-text { font-size: 16px; }
.empty-sub { font-size: 13px; margin-top: 6px; color: #444; }

.bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: #1a1a1a; border-top: 1px solid #2a2a2a; display: flex; padding: 8px 0 20px; z-index: 100; }
.nav-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 3px; text-decoration: none; color: #666; font-size: 10px; }
.nav-item.active { color: #a78bfa; }
.nav-icon { font-size: 22px; }

.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 200; }
.modal-overlay.open { display: flex; align-items: flex-end; }
.modal { background: #1a1a1a; border-radius: 20px 20px 0 0; padding: 20px 16px 40px; width: 100%; max-height: 90vh; overflow-y: auto; }
.modal-handle { width: 40px; height: 4px; background: #3a3a3a; border-radius: 2px; margin: 0 auto 16px; }
.modal-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; }
.form-group { margin-bottom: 14px; }
.form-label { font-size: 13px; color: #999; margin-bottom: 6px; display: block; }
.form-input, .form-select { width: 100%; background: #2a2a2a; border: 1px solid #3a3a3a; border-radius: 10px; padding: 12px 14px; color: #e5e5e5; font-size: 15px; outline: none; -webkit-appearance: none; }
.form-select option { background: #2a2a2a; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.btn-submit { width: 100%; background: #a78bfa; color: #fff; border: none; border-radius: 12px; padding: 15px; font-size: 16px; font-weight: 700; cursor: pointer; margin-top: 8px; }
.btn-cancel { width: 100%; background: #2a2a2a; color: #999; border: none; border-radius: 12px; padding: 13px; font-size: 15px; cursor: pointer; margin-top: 8px; }

.variant-section { display: none; background: #2a2a2a; border-radius: 10px; padding: 14px; margin-top: 10px; }
.variant-section.show { display: block; }
.variant-hint { font-size: 13px; color: #a78bfa; margin-bottom: 10px; }
.variant-textarea { width: 100%; background: #1a1a1a; border: 1px solid #3a3a3a; border-radius: 8px; padding: 10px; color: #e5e5e5; font-size: 14px; resize: none; height: 80px; outline: none; }

.toast { position: fixed; bottom: 90px; left: 50%; transform: translateX(-50%); background: #a78bfa; color: #fff; padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; z-index: 300; opacity: 0; transition: opacity 0.3s; pointer-events: none; white-space: nowrap; }
.toast.show { opacity: 1; }
</style>
</head>
<body>

<div class="nav">
    <a href="chat.php" class="nav-back">← Чат</a>
    <div class="nav-title">Артикули</div>
    <button class="btn-add" onclick="openModal()">+ Добави</button>
</div>

<div class="search-wrap">
    <form method="GET" action="">
        <input type="text" name="q" class="search-input" placeholder="🔍 Търси по име, баркод, код..." value="<?= htmlspecialchars($search) ?>" oninput="this.form.submit()">
    </form>
</div>

<div class="stats-bar">
    <?php
    $low_stock  = array_filter($products, fn($p) => $p['total_stock'] > 0 && $p['total_stock'] <= 3);
    $zero_stock = array_filter($products, fn($p) => $p['total_stock'] == 0);
    ?>
    <div class="stat-chip">Артикули <span><?= count($products) ?></span></div>
    <div class="stat-chip">Ниска нал. <span style="color:<?= count($low_stock) > 0 ? '#fbbf24' : '#e5e5e5' ?>"><?= count($low_stock) ?></span></div>
    <div class="stat-chip">Изчерпани <span style="color:<?= count($zero_stock) > 0 ? '#f87171' : '#e5e5e5' ?>"><?= count($zero_stock) ?></span></div>
</div>

<div class="product-list">
<?php if (empty($products)): ?>
    <div class="empty">
        <div class="empty-icon">📦</div>
        <div class="empty-text"><?= $search ? 'Няма резултати' : 'Нямаш артикули още' ?></div>
        <div class="empty-sub"><?= $search ? 'Опитай с друго търсене' : 'Натисни + Добави или кажи на AI да импортира от снимка' ?></div>
    </div>
<?php else: ?>
    <?php foreach ($products as $p): ?>
    <?php
        $is_low  = $p['total_stock'] > 0 && $p['total_stock'] <= 3;
        $is_zero = $p['total_stock'] == 0;
    ?>
    <div class="product-card" onclick="viewProduct(<?= $p['id'] ?>)">
        <div class="product-top">
            <div>
                <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="product-code">
                    <?= $p['code'] ? 'Код: ' . htmlspecialchars($p['code']) : '' ?>
                    <?= $p['barcode'] ? ' · ' . htmlspecialchars($p['barcode']) : '' ?>
                </div>
            </div>
            <div class="product-stock">
                <div class="stock-num <?= ($is_zero || $is_low) ? 'stock-low' : '' ?>"><?= number_format((float)$p['total_stock'], 0) ?></div>
                <div class="stock-label"><?= htmlspecialchars($p['unit']) ?></div>
            </div>
        </div>
        <div class="product-meta">
            <?php if ($p['category_name']): ?>
            <span class="meta-chip"><?= htmlspecialchars($p['category_name']) ?></span>
            <?php endif; ?>
            <?php if ($p['variant_count'] > 0): ?>
            <span class="meta-chip variant"><?= $p['variant_count'] ?> варианта</span>
            <?php endif; ?>
            <?php if ($p['retail_price'] > 0): ?>
            <span class="meta-chip price">€<?= number_format((float)$p['retail_price'], 2) ?></span>
            <?php endif; ?>
            <?php if ($is_zero): ?>
            <span class="meta-chip" style="color:#f87171">⚠ Изчерпан</span>
            <?php elseif ($is_low): ?>
            <span class="meta-chip" style="color:#fbbf24">⚠ Малко</span>
            <?php endif; ?>
        </div>
        <?php if ($p['supplier_name'] || $p['variant_count'] > 0): ?>
        <div class="product-bottom">
            <div class="supplier-name"><?= $p['supplier_name'] ? '🏭 ' . htmlspecialchars($p['supplier_name']) : '' ?></div>
            <?php if ($p['variant_count'] > 0): ?>
            <button class="btn-variants" onclick="event.stopPropagation(); viewProduct(<?= $p['id'] ?>)">Варианти →</button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<nav class="bottom-nav">
    <a href="chat.php" class="nav-item"><span class="nav-icon">💬</span>Чат</a>
    <a href="products.php" class="nav-item active"><span class="nav-icon">📦</span>Артикули</a>
    <a href="dashboard.php" class="nav-item"><span class="nav-icon">📊</span>Табло</a>
    <a href="chat.php" class="nav-item"><span class="nav-icon">⚙️</span>Настройки</a>
</nav>

<div class="modal-overlay" id="modal" onclick="closeModal(event)">
    <div class="modal">
        <div class="modal-handle"></div>
        <div class="modal-title">Нов артикул</div>
        <form method="POST" action="product-save.php">
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label">Наименование *</label>
                <input type="text" name="name" class="form-input" placeholder="Напр. Дамска блуза Zara" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Код</label>
                    <input type="text" name="code" class="form-input" placeholder="BLZ-001">
                </div>
                <div class="form-group">
                    <label class="form-label">Баркод</label>
                    <input type="text" name="barcode" class="form-input" placeholder="5901234123457">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Категория</label>
                <select name="category_id" class="form-select" onchange="onCategoryChange(this)">
                    <option value="">— Без категория —</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" data-variant="<?= $cat['variant_type'] ?>">
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Доставчик</label>
                <select name="supplier_id" class="form-select">
                    <option value="">— Без доставчик —</option>
                    <?php foreach ($suppliers as $sup): ?>
                    <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Доставна цена (€)</label>
                    <input type="number" name="cost_price" class="form-input" placeholder="0.00" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Продажна цена (€)</label>
                    <input type="number" name="retail_price" class="form-input" placeholder="0.00" step="0.01" min="0">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Мерна единица</label>
                    <select name="unit" class="form-select">
                        <option value="бр">бр</option>
                        <option value="кг">кг</option>
                        <option value="гр">гр</option>
                        <option value="л">л</option>
                        <option value="мл">мл</option>
                        <option value="м">м</option>
                        <option value="чифт">чифт</option>
                        <option value="кутия">кутия</option>
                        <option value="пакет">пакет</option>
                        <option value="комплект">комплект</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Локация</label>
                    <input type="text" name="location" class="form-input" placeholder="Рафт А-3">
                </div>
            </div>

            <div class="variant-section" id="variant-section">
                <div class="variant-hint" id="variant-hint">📐 Тази категория поддържа варианти</div>
                <label class="form-label">Варианти (по желание)</label>
                <textarea name="variants_raw" class="variant-textarea" id="variants-textarea" placeholder="Напр: Червена S-3, Червена M-5, Синя M-2"></textarea>
                <div style="font-size:12px;color:#666;margin-top:6px;">AI ще създаде отделен запис за всеки вариант автоматично.</div>
            </div>

            <button type="submit" class="btn-submit">Запази артикула</button>
            <button type="button" class="btn-cancel" onclick="closeModalBtn()">Отказ</button>
        </form>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
function openModal() { document.getElementById('modal').classList.add('open'); }
function closeModal(e) { if (e.target === document.getElementById('modal')) document.getElementById('modal').classList.remove('open'); }
function closeModalBtn() { document.getElementById('modal').classList.remove('open'); }

function onCategoryChange(sel) {
    const variant = sel.options[sel.selectedIndex].dataset.variant;
    const section = document.getElementById('variant-section');
    const hint    = document.getElementById('variant-hint');
    const ta      = document.getElementById('variants-textarea');
    const hints   = { size_color:'👗 Дрехи — въведи размери и цветове', size:'👟 Обувки — въведи размери', volume:'🧴 Козметика — въведи обеми', capacity:'📱 Електроника — въведи капацитет/цвят' };
    const phs     = { size_color:'Напр: Червена S-3, Червена M-5, Синя M-2', size:'Напр: 38-2, 39-3, 40-5, 41-2', volume:'Напр: 50мл-5, 100мл-8', capacity:'Напр: 128GB Black-2, 256GB White-1' };
    if (variant && variant !== 'none') {
        section.classList.add('show');
        hint.textContent = hints[variant] || '📦 Въведи варианти';
        ta.placeholder = phs[variant] || '';
    } else {
        section.classList.remove('show');
    }
}

function viewProduct(id) { window.location.href = 'product-detail.php?id=' + id; }

function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2500);
}
<?php if (isset($_GET['saved'])): ?>showToast('✅ Артикулът е запазен');<?php endif; ?>
<?php if (isset($_GET['error'])): ?>showToast('❌ Грешка при запазване');<?php endif; ?>
</script>
</body>
</html>
