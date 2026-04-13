<?php
/**
 * inventory.php — RunMyStore.ai
 * S61: Inventory v4.0 — Онбординг + Места CRUD + Броене scaffold
 */
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'config/database.php';
require_once 'config/config.php';
$pdo = DB::get();

$user_id   = $_SESSION['user_id'];
$tenant_id = $_SESSION['tenant_id'];
$store_id  = $_SESSION['store_id'] ?? null;
$user_role = $_SESSION['role'] ?? 'seller';
$is_owner  = ($user_role === 'owner');

$tenant       = DB::run("SELECT * FROM tenants WHERE id = ?", [$tenant_id])->fetch(PDO::FETCH_ASSOC);
$lang         = $tenant['lang'] ?? 'bg';
$currency     = htmlspecialchars($tenant['currency'] ?? 'лв');

$upload_dir = __DIR__ . '/uploads/zones/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// ═══════════════════════════════════════════════════════
// AJAX
// ═══════════════════════════════════════════════════════
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $ajax = $_GET['ajax'];

    if ($ajax === 'get_zones') {
        $zones = DB::run(
            "SELECT * FROM store_zones WHERE store_id = ? AND is_active = 1 ORDER BY sort_order, id",
            [$store_id]
        )->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['ok' => true, 'zones' => $zones]);
        exit;
    }

    if ($ajax === 'save_zone') {
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        $zone_id   = (int)($d['id'] ?? 0);
        $name      = trim($d['name'] ?? '');
        $zone_type = in_array($d['zone_type'] ?? '', ['shop','storage','cashier','other']) ? $d['zone_type'] : 'shop';
        $photo_url = $d['photo_url'] ?? null;
        $sort_order = (int)($d['sort_order'] ?? 99);
        if (!$name) { echo json_encode(['ok'=>false,'error'=>'Няма ид']); exit; }
        if ($zone_id > 0) {
            DB::run("UPDATE store_zones SET name=?,zone_type=?,sort_order=? WHERE id=? AND store_id=?",
                [$name, $zone_type, $sort_order, $zone_id, $store_id]);
            if ($photo_url)
                DB::run("UPDATE store_zones SET photo_url=? WHERE id=? AND store_id=?",
                    [$photo_url, $zone_id, $store_id]);
        } else {
            DB::run("INSERT INTO store_zones (store_id,name,zone_type,photo_url,sort_order) VALUES (?,?,?,?,?)",
                [$store_id, $name, $zone_type, $photo_url, $sort_order]);
            $zone_id = (int)$pdo->lastInsertId();
        }
        echo json_encode(['ok'=>true,'id'=>$zone_id]);
        exit;
    }

    if ($ajax === 'update_zone_photo') {
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        $zone_id   = (int)($d['id'] ?? 0);
        $photo_url = trim($d['photo_url'] ?? '');
        if (!$zone_id || !$photo_url) { echo json_encode(['ok'=>false]); exit; }
        DB::run("UPDATE store_zones SET photo_url=? WHERE id=? AND store_id=?",
            [$photo_url, $zone_id, $store_id]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($ajax === 'delete_zone') {
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        $zone_id = (int)($d['id'] ?? 0);
        DB::run("UPDATE store_zones SET is_active=0 WHERE id=? AND store_id=?", [$zone_id, $store_id]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($ajax === 'upload_zone_photo') {
        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['ok'=>false,'error'=>'Грешка при качване']); exit;
        }
        $file = $_FILES['photo'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            echo json_encode(['ok'=>false,'error'=>'Невалиден формат']); exit;
        }
        if ($file['size'] > 8*1024*1024) {
            echo json_encode(['ok'=>false,'error'=>'Файлът е прекалено голям']); exit;
        }
        $fname = 'zone_'.$tenant_id.'_'.$store_id.'_'.time().'_'.rand(1000,9999).'.'.$ext;
        move_uploaded_file($file['tmp_name'], $upload_dir.$fname);
        echo json_encode(['ok'=>true,'url'=>'uploads/zones/'.$fname]);
        exit;
    }

    if ($ajax === 'get_stats') {
        $total   = (int)DB::run("SELECT COUNT(DISTINCT p.id) FROM products p JOIN inventory i ON i.product_id=p.id WHERE i.store_id=? AND p.is_active=1", [$store_id])->fetchColumn();
        $counted = (int)DB::run("SELECT COUNT(DISTINCT p.id) FROM products p JOIN inventory i ON i.product_id=p.id WHERE i.store_id=? AND p.is_active=1 AND i.is_counted=1", [$store_id])->fetchColumn();
        $zones   = (int)DB::run("SELECT COUNT(*) FROM store_zones WHERE store_id=? AND is_active=1", [$store_id])->fetchColumn();
        $sess    = DB::run("SELECT id,status,mode,items_counted,items_total,zones_completed,zones_total FROM inventory_count_sessions WHERE store_id=? AND status IN ('in_progress','paused') ORDER BY created_at DESC LIMIT 1", [$store_id])->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['ok'=>true,'total'=>$total,'counted'=>$counted,'zones'=>$zones,'session'=>$sess ?: null]);
        exit;
    }

    if ($ajax === 'start_session') {
        $d = json_decode(file_get_contents('php://input'), true) ?? [];
        $mode        = ($d['mode'] ?? 'quick') === 'full' ? 'full' : 'quick';
        $zones_total = (int)DB::run("SELECT COUNT(*) FROM store_zones WHERE store_id=? AND is_active=1", [$store_id])->fetchColumn();
        $items_total = (int)DB::run(
            "SELECT COUNT(DISTINCT p.id) FROM products p JOIN inventory i ON i.product_id=p.id WHERE i.store_id=? AND p.is_active=1",
            [$store_id]
        )->fetchColumn();
        DB::run("UPDATE inventory_count_sessions SET status='paused' WHERE store_id=? AND status='draft'", [$store_id]);
        DB::run("INSERT INTO inventory_count_sessions (store_id,tenant_id,status,mode,baseline_at,started_at,zones_total,items_total) VALUES (?,?,'in_progress',?,NOW(),NOW(),?,?)",
            [$store_id, $tenant_id, $mode, $zones_total, $items_total]);
        $session_id = (int)$pdo->lastInsertId();
        echo json_encode(['ok'=>true,'session_id'=>$session_id,'mode'=>$mode]);
        exit;
    }

    echo json_encode(['ok'=>false,'error'=>'Unknown']);
    exit;
}

$zones_count    = (int)DB::run("SELECT COUNT(*) FROM store_zones WHERE store_id=? AND is_active=1", [$store_id])->fetchColumn();
$active_session = DB::run("SELECT id,status,mode,items_counted,items_total,zones_completed,zones_total FROM inventory_count_sessions WHERE store_id=? AND status IN ('in_progress','paused') ORDER BY created_at DESC LIMIT 1", [$store_id])->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Скрити пари — RunMyStore.ai</title>
<link rel="stylesheet" href="style.css">
<style>
body{background:#030712;font-family:'Montserrat',sans-serif;color:#e2e8f0;margin:0;padding:0;overflow-x:hidden}
.inv-screen{display:none;min-height:100dvh;padding-bottom:90px;flex-direction:column}
.inv-screen.active{display:flex}
.inv-header{display:flex;align-items:center;gap:12px;padding:14px 16px 12px;position:sticky;top:0;z-index:50;background:rgba(3,7,18,.94);backdrop-filter:blur(12px);border-bottom:1px solid rgba(99,102,241,.15)}
.inv-header h1{font-size:16px;font-weight:700;margin:0;color:#e2e8f0}
.back-btn{width:36px;height:36px;background:rgba(99,102,241,.15);border:none;border-radius:10px;color:#a5b4fc;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.back-btn svg{width:18px;height:18px}
.info-btn{width:26px;height:26px;border-radius:50%;background:rgba(99,102,241,.2);border:1px solid rgba(99,102,241,.3);color:#a5b4fc;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0}
/* AI Bubble */
.ai-bwrap{padding:16px}
.ai-bubble{background:rgba(99,102,241,.12);border:1px solid rgba(99,102,241,.25);border-radius:16px 16px 16px 4px;padding:14px 16px;font-size:14px;line-height:1.65}
.ai-avatar{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;margin-bottom:8px}
.ai-avatar svg{width:13px;height:13px;color:#fff}
.ai-bubble strong{color:#c7d2fe}
/* Chips */
.choice-chips{display:flex;flex-direction:column;gap:10px;padding:0 16px 16px}
.choice-chip{background:rgba(15,23,42,.8);border:1.5px solid rgba(99,102,241,.28);border-radius:14px;padding:13px 14px;font-size:14px;font-weight:600;color:#e2e8f0;cursor:pointer;text-align:left;transition:all .2s;display:flex;align-items:center;gap:12px}
.choice-chip:active,.choice-chip.selected{background:rgba(99,102,241,.22);border-color:#6366f1}
.chip-icon{width:36px;height:36px;border-radius:10px;background:rgba(99,102,241,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.chip-icon svg{width:18px;height:18px;color:#a5b4fc}
.chip-text small{display:block;font-size:11px;color:#94a3b8;font-weight:400;margin-top:2px}
/* Buttons */
.inv-btn-primary{padding:15px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:14px;font-size:15px;font-weight:700;color:#fff;cursor:pointer;width:100%;transition:opacity .2s}
.inv-btn-primary:disabled{opacity:.38}
/* Onboarding steps */
.onb-steps{display:flex;gap:6px;padding:0 16px 12px}
.onb-step{flex:1;height:3px;border-radius:2px;background:rgba(99,102,241,.15);transition:background .3s}
.onb-step.done{background:#6366f1}
.onb-step.active{background:rgba(99,102,241,.5)}
/* Sections */
.inv-section-hdr{font-size:11px;font-weight:700;color:#64748b;letter-spacing:.08em;text-transform:uppercase;padding:14px 16px 8px;display:flex;align-items:center;gap:8px}
/* Stats */
.stats-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;padding:14px 16px}
.stat-card{background:rgba(15,23,42,.7);border:1px solid rgba(99,102,241,.15);border-radius:12px;padding:12px 8px;text-align:center}
.stat-val{font-size:22px;font-weight:800;color:#c7d2fe}
.stat-lbl{font-size:10px;color:#94a3b8;margin-top:2px}
/* Zone card */
.zone-card{background:rgba(15,23,42,.7);border:1px solid rgba(99,102,241,.2);border-radius:14px;overflow:hidden;display:flex;align-items:stretch;margin:0 16px 10px}
.zone-card-photo{width:74px;flex-shrink:0;background:rgba(99,102,241,.08);display:flex;align-items:center;justify-content:center;cursor:pointer;min-height:82px;position:relative;overflow:hidden}
.zone-card-photo img{width:100%;height:100%;object-fit:cover;position:absolute;inset:0}
.zone-card-photo.no-ph{border:2px dashed rgba(239,68,68,.4)}
.zp-inner{display:flex;flex-direction:column;align-items:center;gap:4px;z-index:1}
.zp-inner svg{width:20px;height:20px;color:#4f46e5}
.zp-inner span{font-size:9px;color:#6366f1;font-weight:600;text-align:center;line-height:1.2}
.zone-card-body{flex:1;padding:12px;display:flex;flex-direction:column;gap:3px}
.zone-card-name{font-size:14px;font-weight:700}
.zone-card-type{font-size:11px;color:#94a3b8}
.zone-card-acts{display:flex;flex-direction:column;align-items:center;gap:6px;padding:8px 10px;justify-content:center}
.zact{width:32px;height:32px;border:none;border-radius:8px;background:rgba(99,102,241,.15);color:#a5b4fc;cursor:pointer;display:flex;align-items:center;justify-content:center}
.zact svg{width:14px;height:14px}
.zact.del{background:rgba(239,68,68,.12);color:#f87171}
/* Add zone btn */
.add-zone-btn{margin:4px 16px 12px;padding:14px;background:rgba(99,102,241,.08);border:2px dashed rgba(99,102,241,.28);border-radius:14px;color:#a5b4fc;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .2s}
.add-zone-btn svg{width:18px;height:18px}
/* Progress */
.prog-bar{height:5px;background:rgba(99,102,241,.15);border-radius:3px;overflow:hidden}
.prog-fill{height:100%;background:linear-gradient(90deg,#6366f1,#8b5cf6);border-radius:3px;transition:width .5s}
.prog-lbl{font-size:11px;color:#94a3b8;margin-top:5px}
/* Voice overlay */
.rec-ov{display:none;position:fixed;inset:0;z-index:1000;background:rgba(3,7,18,.72);backdrop-filter:blur(8px);align-items:flex-end;justify-content:center;padding-bottom:100px}
.rec-ov.active{display:flex}
.rec-box{background:rgba(15,23,42,.97);border:1px solid rgba(99,102,241,.4);border-radius:20px;padding:20px;width:calc(100% - 32px);box-shadow:0 0 30px rgba(99,102,241,.3)}
.rec-ind{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.rec-dot{width:12px;height:12px;border-radius:50%;background:#ef4444;animation:recPulse 1s infinite}
.rec-dot.done{background:#22c55e;animation:none}
@keyframes recPulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.8)}}
.rec-lbl{font-size:13px;font-weight:700}
.rec-trans{min-height:46px;font-size:16px;color:#c7d2fe;margin-bottom:14px;line-height:1.4}
.rec-send{width:100%;padding:14px;border:none;border-radius:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;font-size:15px;font-weight:700;cursor:pointer}
.rec-cancel{width:100%;margin-top:8px;padding:10px;border:none;border-radius:12px;background:transparent;color:#94a3b8;font-size:13px;cursor:pointer}
/* Zone modal */
.modal-ov{display:none;position:fixed;inset:0;z-index:600;background:rgba(0,0,0,.75);backdrop-filter:blur(8px);align-items:flex-end;justify-content:center}
.modal-ov.open{display:flex}
.modal-box{background:#0c1525;border:1px solid rgba(99,102,241,.3);border-radius:20px 20px 0 0;padding:20px;width:100%;max-height:88dvh;overflow-y:auto}
.mhandle{width:36px;height:4px;background:rgba(99,102,241,.3);border-radius:2px;margin:0 auto 16px}
/* Zone type tabs */
.zt-tabs{display:flex;gap:6px;flex-wrap:wrap}
.zt-tab{padding:7px 13px;border-radius:20px;font-size:12px;font-weight:600;border:1.5px solid rgba(99,102,241,.2);background:transparent;color:#94a3b8;cursor:pointer;transition:all .2s}
.zt-tab.active{background:rgba(99,102,241,.22);border-color:#6366f1;color:#a5b4fc}
/* Photo preview */
.photo-preview{width:100%;height:140px;background:rgba(99,102,241,.07);border:2px dashed rgba(99,102,241,.3);border-radius:12px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;cursor:pointer;position:relative;overflow:hidden}
.photo-preview img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:10px}
/* Name field */
.name-field{display:flex;gap:8px;align-items:center}
.name-display{flex:1;min-height:44px;padding:10px 14px;background:rgba(99,102,241,.07);border:1.5px solid rgba(99,102,241,.2);border-radius:10px;font-size:15px;color:#e2e8f0;display:flex;align-items:center;cursor:pointer}
.voice-mini-btn{padding:10px 14px;border:1.5px solid rgba(239,68,68,.3);border-radius:10px;background:rgba(239,68,68,.1);color:#fca5a5;cursor:pointer;display:flex;align-items:center;gap:6px;font-size:13px;font-weight:700;white-space:nowrap;flex-shrink:0}
.voice-mini-btn svg{width:16px;height:16px}
/* Count zone card */
.czc{background:rgba(15,23,42,.7);border:1px solid rgba(99,102,241,.2);border-radius:16px;overflow:hidden;margin:0 16px 10px;cursor:pointer;display:flex;align-items:stretch;transition:opacity .2s}
.czc:active{opacity:.75}
.czc.done{border-color:rgba(34,197,94,.3)}
.czc-photo{width:78px;flex-shrink:0;background:rgba(99,102,241,.08);display:flex;align-items:center;justify-content:center;min-height:78px;position:relative;overflow:hidden}
.czc-photo img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.czc-body{flex:1;padding:13px}
.czc-name{font-size:15px;font-weight:700;margin-bottom:4px}
.czc-sub{font-size:12px;color:#94a3b8}
.czc-arr{display:flex;align-items:center;padding:0 12px;color:#6366f1}
.czc-arr svg{width:18px;height:18px}
/* Session banner */
.sess-banner{margin:12px 16px;background:rgba(99,102,241,.14);border:1px solid rgba(99,102,241,.28);border-radius:14px;padding:14px}
/* Toast */
.inv-toast{position:fixed;bottom:92px;left:50%;transform:translateX(-50%);background:rgba(12,21,37,.97);border:1px solid rgba(99,102,241,.3);border-radius:12px;padding:10px 18px;font-size:13px;color:#e2e8f0;z-index:2000;white-space:nowrap;pointer-events:none;opacity:0;transition:opacity .3s}
.inv-toast.show{opacity:1}
.inv-toast.err{border-color:rgba(239,68,68,.4)}
.inv-toast.ok{border-color:rgba(34,197,94,.4)}
/* ── BOTTOM NAV ── */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;height:56px;background:rgba(3,7,18,.97);border-top:1px solid rgba(255,255,255,.04);display:flex;z-index:100}
.bottom-nav-tab{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;font-size:8px;font-weight:600;text-decoration:none;transition:all .2s}
.bottom-nav-tab svg{width:18px;height:18px;stroke-width:1.5;fill:none}
.bottom-nav-tab.active{color:#a5b4fc}
.bottom-nav-tab.active svg{stroke:#a5b4fc}
.bottom-nav-tab.inactive{color:rgba(165,180,252,.45)}
.bottom-nav-tab.inactive svg{stroke:rgba(165,180,252,.45)}
</style>
</head>
<body>

<!-- ══════════════════════════════════
     HUB
══════════════════════════════════ -->
<div id="screen-hub" class="inv-screen">
  <div class="inv-header">
    <button class="back-btn" onclick="history.back()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
    <h1>Скрити пари</h1>
    <button class="info-btn" style="margin-left:auto" onclick="showInfo('hub')">i</button>
  </div>

  <div class="stats-row">
    <div class="stat-card"><div class="stat-val" id="stTotal">—</div><div class="stat-lbl">Артикула</div></div>
    <div class="stat-card"><div class="stat-val" id="stCounted">—</div><div class="stat-lbl">Преброени</div></div>
    <div class="stat-card"><div class="stat-val" id="stZones">—</div><div class="stat-lbl">Места</div></div>
  </div>

  <div id="sessBanner" class="sess-banner" style="display:none">
    <div style="font-size:13px;font-weight:700;color:#c7d2fe;margin-bottom:6px">Броенето продължава</div>
    <div id="sessLbl" style="font-size:12px;color:#94a3b8;margin-bottom:8px"></div>
    <div class="prog-bar"><div class="prog-fill" id="sessBar" style="width:0%"></div></div>
    <button onclick="goCountingScreen()" style="margin-top:12px;width:100%;padding:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border:none;border-radius:10px;color:#fff;font-size:14px;font-weight:700;cursor:pointer">Продължи броенето</button>
  </div>

  <div id="noSessPanel" style="display:none;padding:0 16px 4px">
    <button onclick="goCountingScreen()" class="inv-btn-primary">
      <span style="display:flex;align-items:center;justify-content:center;gap:10px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Започни броене
      </span>
    </button>
  </div>

  <div class="inv-section-hdr">
    Места
    <button class="info-btn" onclick="showInfo('zones')">i</button>
    <button onclick="openZoneModal()" style="margin-left:auto;padding:5px 12px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.28);border-radius:8px;color:#a5b4fc;font-size:12px;font-weight:600;cursor:pointer">+ Добави</button>
  </div>
  <div id="hubZones"></div>
  <button class="add-zone-btn" onclick="openZoneModal()">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Добави място
  </button>
</div>


<!-- ══════════════════════════════════
     WELCOME (step 1)
══════════════════════════════════ -->
<div id="screen-welcome" class="inv-screen">
  <div class="inv-header">
    <button class="back-btn" onclick="history.back()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
    <h1>Скрити пари</h1>
  </div>
  <div class="onb-steps">
    <div class="onb-step active"></div><div class="onb-step"></div><div class="onb-step"></div><div class="onb-step"></div><div class="onb-step"></div>
  </div>
  <div class="ai-bwrap">
    <div class="ai-bubble">
      <div class="ai-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg></div>
      <strong>Мисля, че имаш пари скрити в магазина.</strong><br><br>
      Стока, за която не знаеш точно колко имаш. Артикули, забравени на рафта. Неща, дадени на кредит без запис.<br><br>
      Искаш ли да видим заедно?
    </div>
  </div>
  <div class="choice-chips">
    <div class="choice-chip" onclick="step1('start')">
      <div class="chip-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg></div>
      <div class="chip-text">Да, да започваме<small>Обхождаме магазина стъпка по стъпка</small></div>
    </div>
    <div class="choice-chip" onclick="step1('csv')">
      <div class="chip-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg></div>
      <div class="chip-text">Имам файл с артикули<small>CSV или Excel импорт</small></div>
    </div>
    <div class="choice-chip" onclick="step1('later')">
      <div class="chip-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
      <div class="chip-text">По-късно<small>Ще се върна когато имам повече време</small></div>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════
     SIZE (step 2)
══════════════════════════════════ -->
<div id="screen-size" class="inv-screen">
  <div class="inv-header">
    <button class="back-btn" onclick="showScreen('screen-welcome')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
    <h1>Скрити пари</h1>
  </div>
  <div class="onb-steps">
    <div class="onb-step done"></div><div class="onb-step active"></div><div class="onb-step"></div><div class="onb-step"></div><div class="onb-step"></div>
  </div>
  <div class="ai-bwrap">
    <div class="ai-bubble">
      <div class="ai-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg></div>
      Колко артикула имаш приблизително?<br><small style="color:#94a3b8">Не е нужно да е точно — оценка е достатъчна.</small>
    </div>
  </div>
  <div class="choice-chips" id="countChips">
    <div class="choice-chip" onclick="selCount(100,this)">
      <div class="chip-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/></svg></div>
      <div class="chip-text">До 100 артикула<small>Малък магазин — лесно ще стане</small></div>
    </div>
    <div class="choice-chip" onclick="selCount(300,this)">
      <div class="chip-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg></div>
      <div class="chip-text">100 – 500 артикула<small>Среден магазин</small></div>
    </div>
    <div class="choice-chip" onclick="selCount(700,this)">
      <div class="chip-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg></div>
      <div class="chip-text">Над 500 артикула<small>Голям магазин — правим го на части</small></div>
    </div>
  </div>
  <div id="varQ" style="display:none">
    <div class="ai-bwrap" style="padding-top:0">
      <div class="ai-bubble">Повечето артикули имат ли размери или цветове?</div>
    </div>
    <div class="choice-chips" id="varChips">
      <div class="choice-chip" onclick="selVar(true,this)">
        <div class="chip-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
        <div class="chip-text">Да, повечето<small>S, M, L или различни цветове</small></div>
      </div>
      <div class="choice-chip" onclick="selVar(false,this)">
        <div class="chip-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
        <div class="chip-text">Не<small>Всеки артикул е един вид</small></div>
      </div>
    </div>
  </div>
  <div style="padding:0 16px 16px">
    <button class="inv-btn-primary" id="sizeBtn" disabled onclick="showScreen('screen-zones')">Напред →</button>
  </div>
</div>


<!-- ══════════════════════════════════
     ZONES (step 3)
══════════════════════════════════ -->
<div id="screen-zones" class="inv-screen">
  <div class="inv-header">
    <button class="back-btn" onclick="showScreen('screen-size')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
    <h1>Места в магазина</h1>
    <button class="info-btn" style="margin-left:auto" onclick="showInfo('zones')">i</button>
  </div>
  <div class="onb-steps">
    <div class="onb-step done"></div><div class="onb-step done"></div><div class="onb-step active"></div><div class="onb-step"></div><div class="onb-step"></div>
  </div>
  <div class="ai-bwrap">
    <div class="ai-bubble">
      <div class="ai-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg></div>
      Сега ще обходим магазина заедно. Снимай <strong>ВСЯКО отделно място</strong> — щендер, рафт, витрина, маса, кука, кашон.<br><br>
      Кръсти го <strong>както ти му казваш.</strong>
    </div>
  </div>
  <div id="onbZones"></div>
  <button class="add-zone-btn" onclick="openZoneModal('onb')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Добави място
  </button>
  <div style="padding:0 16px 16px">
    <button class="inv-btn-primary" id="zonesBtn" disabled onclick="goConfirm()">Готово с местата →</button>
    <div style="font-size:11px;color:#64748b;text-align:center;margin-top:8px">Нужно е поне 1 място</div>
  </div>
</div>


<!-- ══════════════════════════════════
     CONFIRM (step 4)
══════════════════════════════════ -->
<div id="screen-confirm" class="inv-screen">
  <div class="inv-header">
    <button class="back-btn" onclick="showScreen('screen-zones')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
    <h1>Потвърди местата</h1>
  </div>
  <div class="onb-steps">
    <div class="onb-step done"></div><div class="onb-step done"></div><div class="onb-step done"></div><div class="onb-step active"></div><div class="onb-step"></div>
  </div>
  <div class="ai-bwrap">
    <div class="ai-bubble">
      <div class="ai-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg></div>
      Ето всички места. Всичко ли е точно?
    </div>
  </div>
  <div id="confirmZones" style="padding:0 16px"></div>
  <div class="choice-chips" style="padding-top:8px">
    <div class="choice-chip" onclick="showScreen('screen-start');renderStartZones()">
      <div class="chip-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></div>
      <div class="chip-text">Точно е<small>Продължаваме напред</small></div>
    </div>
    <div class="choice-chip" onclick="showScreen('screen-zones')">
      <div class="chip-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></div>
      <div class="chip-text">Промени<small>Добави или редактирай места</small></div>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════
     START REC (step 5)
══════════════════════════════════ -->
<div id="screen-start" class="inv-screen">
  <div class="inv-header">
    <button class="back-btn" onclick="showScreen('screen-confirm')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
    <h1>Откъде да започнем</h1>
  </div>
  <div class="onb-steps">
    <div class="onb-step done"></div><div class="onb-step done"></div><div class="onb-step done"></div><div class="onb-step done"></div><div class="onb-step active"></div>
  </div>
  <div class="ai-bwrap">
    <div class="ai-bubble">
      <div class="ai-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg></div>
      <strong>Препоръчвам: Започни от склада.</strong><br><br>
      Там е масата от стоката и се брои по-лесно — без клиенти, без разсейване. Когато стигнеш магазина, повечето артикули вече ще са преброени.
    </div>
  </div>
  <div id="startZones" style="padding:0 16px;display:flex;flex-direction:column;gap:10px"></div>
  <div style="padding:16px">
    <button onclick="doStartCounting()" class="inv-btn-primary">Да започваме!</button>
    <button onclick="skipToHub()" style="width:100%;margin-top:10px;background:transparent;border:none;color:#64748b;font-size:13px;cursor:pointer;padding:8px">Ще започна по-късно</button>
  </div>
</div>


<!-- ══════════════════════════════════
     COUNTING (zone picker)
══════════════════════════════════ -->
<div id="screen-counting" class="inv-screen">
  <div class="inv-header">
    <button class="back-btn" onclick="showScreen('screen-hub')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
    <h1>Броене</h1>
    <div id="cntModeTag" style="margin-left:auto;font-size:11px;padding:4px 10px;background:rgba(99,102,241,.2);border-radius:20px;color:#a5b4fc;font-weight:700"></div>
  </div>
  <div style="padding:12px 16px">
    <div class="prog-bar"><div class="prog-fill" id="cntBar" style="width:0%"></div></div>
    <div class="prog-lbl" id="cntLbl">0 от 0 места преброени</div>
  </div>
  <div class="ai-bwrap" style="padding-top:0">
    <div class="ai-bubble" style="font-size:13px">Избери от кое място да започнеш.</div>
  </div>
  <div id="cntZones"></div>
</div>


<!-- ══════════════════════════════════
     MODAL: Zone
══════════════════════════════════ -->
<div class="modal-ov" id="zoneModal">
  <div class="modal-box">
    <div class="mhandle"></div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
      <div style="font-size:16px;font-weight:700" id="zmTitle">Добави място</div>
      <button onclick="closeZoneModal()" style="background:none;border:none;color:#64748b;font-size:22px;cursor:pointer;line-height:1">✕</button>
    </div>

    <!-- Photo -->
    <div style="margin-bottom:16px">
      <div style="font-size:12px;font-weight:700;color:#94a3b8;margin-bottom:8px;display:flex;align-items:center;gap:6px">
        СНИМКА <span style="color:#ef4444;font-size:10px">* задължителна</span>
      </div>
      <div class="photo-preview" onclick="triggerPhoto()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:36px;height:36px;color:#4f46e5"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
        <span style="font-size:13px;font-weight:600;color:#6366f1">Снимай</span>
        <span style="font-size:11px;color:#94a3b8">или избери от галерията</span>
        <img id="zmPhotoImg" src="" alt="" style="display:none">
      </div>
      <input type="file" id="zmPhotoInput" accept="image/*" capture="environment" style="display:none" onchange="handlePhotoUpload(this)">
    </div>

    <!-- Name -->
    <div style="margin-bottom:16px">
      <div style="font-size:12px;font-weight:700;color:#94a3b8;margin-bottom:8px">ИМЕ НА МЯСТОТО</div>
      <div class="name-field">
        <div class="name-display" id="zmNameDisp" onclick="startZoneVoice()">
          <span id="zmNameTxt" style="color:#64748b">Кажи с глас...</span>
        </div>
        <button class="voice-mini-btn" onclick="startZoneVoice()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/><line x1="8" y1="23" x2="16" y2="23"/></svg>
        </button>
      </div>
    </div>

    <!-- Type -->
    <div style="margin-bottom:20px">
      <div style="font-size:12px;font-weight:700;color:#94a3b8;margin-bottom:8px">ВИД МЯСТО</div>
      <div class="zt-tabs">
        <button class="zt-tab active" onclick="selZoneType('shop',this)">Магазин</button>
        <button class="zt-tab" onclick="selZoneType('storage',this)">Склад</button>
        <button class="zt-tab" onclick="selZoneType('cashier',this)">Каса</button>
        <button class="zt-tab" onclick="selZoneType('other',this)">Друго</button>
      </div>
    </div>

    <button onclick="saveZone()" class="inv-btn-primary">Запази</button>
  </div>
</div>


<!-- ══════════════════════════════════
     MODAL: Info
══════════════════════════════════ -->
<div class="modal-ov" id="infoModal">
  <div class="modal-box">
    <div class="mhandle"></div>
    <div style="font-size:16px;font-weight:700;margin-bottom:12px" id="infoTitle"></div>
    <div id="infoBody" style="font-size:14px;color:#94a3b8;line-height:1.7"></div>
    <button onclick="document.getElementById('infoModal').classList.remove('open')"
      style="width:100%;margin-top:16px;padding:13px;background:rgba(99,102,241,.15);border:1px solid rgba(99,102,241,.28);border-radius:12px;color:#a5b4fc;font-size:14px;font-weight:700;cursor:pointer">Разбрах</button>
  </div>
</div>


<!-- Voice Overlay -->
<div class="rec-ov" id="recOv">
  <div class="rec-box">
    <div class="rec-ind">
      <div class="rec-dot" id="recDot"></div>
      <div class="rec-lbl" id="recLbl">ЗАПИСВА...</div>
    </div>
    <div class="rec-trans" id="recTrans"></div>
    <button class="rec-send" id="recSendBtn" style="display:none" onclick="recSend()">Запази →</button>
    <button class="rec-cancel" onclick="recCancel()">Отказ</button>
  </div>
</div>

<!-- Toast -->
<div class="inv-toast" id="iToast"></div>

<nav class="bottom-nav">
  <a href="chat.php" class="bottom-nav-tab inactive">
    <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>AI
  </a>
  <a href="warehouse.php" class="bottom-nav-tab active">
    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
      <path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>Склад
  </a>
  <a href="stats.php" class="bottom-nav-tab inactive">
    <svg viewBox="0 0 24 24" stroke-linecap="round">
      <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/>
      <line x1="6" y1="20" x2="6" y2="14"/></svg>Справки
  </a>
  <a href="sale.php" class="bottom-nav-tab inactive">
    <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
      <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>Продажба
  </a>
</nav>

<?php if (file_exists(__DIR__ . "/includes/ai-chat-overlay.php")) { include __DIR__ . "/includes/ai-chat-overlay.php"; } ?>

<script>
// ══════════════════════════════════════════════════════
// STATE
// ══════════════════════════════════════════════════════
const INV = {
    approxCount: 0, hasVar: false, mode: 'quick',
    zones: [], session: null,
    zm: { id:0, name:'', type:'shop', photo:null, ctx:'hub' },
};
const PHP = {
    zonesCount: <?= $zones_count ?>,
    hasSession:  <?= $active_session ? 'true' : 'false' ?>,
    session:     <?= json_encode($active_session ?: null) ?>,
};

// ══════════════════════════════════════════════════════
// BOOT
// ══════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    if (PHP.zonesCount > 0) { showScreen('screen-hub'); loadHub(); }
    else showScreen('screen-welcome');
});

// ══════════════════════════════════════════════════════
// SCREENS
// ══════════════════════════════════════════════════════
function showScreen(id) {
    document.querySelectorAll('.inv-screen').forEach(s => s.classList.remove('active'));
    const el = document.getElementById(id);
    if (el) { el.classList.add('active'); window.scrollTo(0,0); }
}

// ══════════════════════════════════════════════════════
// HUB
// ══════════════════════════════════════════════════════
async function loadHub() {
    const d = await api('get_stats');
    if (!d.ok) return;
    document.getElementById('stTotal').textContent   = d.total;
    document.getElementById('stCounted').textContent = d.counted;
    document.getElementById('stZones').textContent   = d.zones;
    INV.session = d.session;
    if (d.session) {
        const pct = d.session.items_total > 0
            ? Math.round(d.session.items_counted / d.session.items_total * 100) : 0;
        document.getElementById('sessBanner').style.display = 'block';
        document.getElementById('noSessPanel').style.display = 'none';
        document.getElementById('sessLbl').textContent =
            `${d.session.items_counted} от ${d.session.items_total} артикула (${pct}%)`;
        document.getElementById('sessBar').style.width = pct + '%';
    } else {
        document.getElementById('sessBanner').style.display = 'none';
        document.getElementById('noSessPanel').style.display = 'block';
    }
    await loadZones();
    renderHubZones();
}

async function loadZones() {
    const d = await api('get_zones');
    if (d.ok) INV.zones = d.zones;
}

function renderHubZones() {
    const w = document.getElementById('hubZones');
    if (!INV.zones.length) {
        w.innerHTML = '<div style="text-align:center;padding:20px;color:#64748b;font-size:13px">Добави поне едно място</div>';
        return;
    }
    w.innerHTML = INV.zones.map(z => zoneCard(z, 'hub')).join('');
}

function renderOnbZones() {
    const w = document.getElementById('onbZones');
    w.innerHTML = INV.zones.map(z => zoneCard(z, 'onb')).join('');
    document.getElementById('zonesBtn').disabled = INV.zones.length === 0;
}

function zoneCard(z, ctx) {
    const hasPh = !!z.photo_url;
    const phHtml = hasPh
        ? `<img src="${esc(z.photo_url)}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">`
        : `<div class="zp-inner"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg><span>Снимка</span></div>`;
    return `<div class="zone-card" id="zc${z.id}">
        <div class="zone-card-photo${hasPh?'':' no-ph'}" onclick="addPhotoToZone(${z.id})" style="position:relative;overflow:hidden">${phHtml}</div>
        <div class="zone-card-body">
            <div class="zone-card-name">${esc(z.name)}</div>
            <div class="zone-card-type">${ztLabel(z.zone_type)}</div>
            ${!hasPh?'<div style="font-size:10px;color:#f59e0b;margin-top:4px">Липсва снимка</div>':''}
        </div>
        <div class="zone-card-acts">
            <button class="zact" onclick="editZone(${z.id})" title="Редактирай"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
            <button class="zact del" onclick="delZone(${z.id})" title="Изтрий"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg></button>
        </div>
    </div>`;
}

// ══════════════════════════════════════════════════════
// ONBOARDING STEPS
// ══════════════════════════════════════════════════════
function step1(choice) {
    if (choice === 'start')     showScreen('screen-size');
    else if (choice === 'csv')  toast('CSV импорт — скоро');
    else { toast('Можеш да се върнеш от Склад → Скрити пари'); setTimeout(()=>history.back(),1600); }
}

function selCount(n, el) {
    INV.approxCount = n;
    document.querySelectorAll('#countChips .choice-chip').forEach(c=>c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('varQ').style.display = 'block';
    document.getElementById('varQ').scrollIntoView({behavior:'smooth',block:'nearest'});
}

function selVar(v, el) {
    INV.hasVar = v;
    document.querySelectorAll('#varChips .choice-chip').forEach(c=>c.classList.remove('selected'));
    el.classList.add('selected');
    INV.mode = (INV.approxCount > 500 || (INV.approxCount > 200 && v)) ? 'full' : 'quick';
    document.getElementById('sizeBtn').disabled = false;
}

function goConfirm() {
    renderConfirmZones();
    showScreen('screen-confirm');
}

function renderConfirmZones() {
    const w = document.getElementById('confirmZones');
    const groups = {shop:[],storage:[],cashier:[],other:[]};
    INV.zones.forEach(z => groups[z.zone_type].push(z));
    const labels = {shop:'МАГАЗИН',storage:'СКЛАД',cashier:'КАСА',other:'ДРУГО'};
    let html = '';
    for (const [t, arr] of Object.entries(groups)) {
        if (!arr.length) continue;
        html += `<div style="font-size:11px;font-weight:700;color:#64748b;letter-spacing:.08em;text-transform:uppercase;margin:12px 0 6px">${labels[t]} (${arr.length})</div>`;
        arr.forEach(z => {
            const ph = z.photo_url
                ? `<img src="${esc(z.photo_url)}" style="width:44px;height:44px;object-fit:cover;border-radius:8px;flex-shrink:0">`
                : `<div style="width:44px;height:44px;background:rgba(99,102,241,.1);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:18px;height:18px;color:#4f46e5"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg></div>`;
            const warn = !z.photo_url ? '<span style="font-size:10px;color:#f59e0b;margin-left:6px">Без снимка</span>' : '';
            html += `<div style="display:flex;align-items:center;gap:10px;padding:8px;background:rgba(15,23,42,.6);border-radius:10px;margin-bottom:6px">
                ${ph}
                <div style="flex:1;font-size:14px;font-weight:600">${esc(z.name)}${warn}</div>
                <button onclick="editZone(${z.id});showScreen('screen-zones')" style="background:none;border:none;color:#6366f1;font-size:18px;cursor:pointer;padding:4px">✎</button>
            </div>`;
        });
    }
    w.innerHTML = html || '<div style="color:#64748b;text-align:center;padding:16px">Няма добавени места</div>';
}

function renderStartZones() {
    const w = document.getElementById('startZones');
    const sorted = [...INV.zones].sort((a,b) => {
        if (a.zone_type==='storage' && b.zone_type!=='storage') return -1;
        if (b.zone_type==='storage' && a.zone_type!=='storage') return 1;
        return 0;
    });
    w.innerHTML = sorted.map(z => {
        const ph = z.photo_url
            ? `<img src="${esc(z.photo_url)}" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0">`
            : `<div style="display:flex;align-items:center;justify-content:center;height:100%"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:24px;height:24px;color:#4f46e5"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg></div>`;
        const rec = z.zone_type === 'storage'
            ? '<span style="font-size:10px;background:rgba(99,102,241,.2);color:#a5b4fc;padding:2px 8px;border-radius:10px;font-weight:700">ПРЕПОРЪЧАНО</span>' : '';
        return `<div onclick="doStartCounting()" style="background:rgba(15,23,42,.7);border:1px solid rgba(99,102,241,.2);border-radius:14px;overflow:hidden;display:flex;align-items:stretch;cursor:pointer">
            <div style="width:78px;flex-shrink:0;position:relative;min-height:76px;background:rgba(99,102,241,.08)">${ph}</div>
            <div style="flex:1;padding:12px;display:flex;flex-direction:column;gap:4px">
                <div style="font-size:14px;font-weight:700">${esc(z.name)}</div>
                <div style="font-size:11px;color:#94a3b8">${ztLabel(z.zone_type)}</div>
                ${rec}
            </div>
            <div style="display:flex;align-items:center;padding:0 12px;color:#6366f1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px"><polyline points="9 18 15 12 9 6"/></svg></div>
        </div>`;
    }).join('');
}

async function doStartCounting() {
    const res = await api('start_session', {mode: INV.mode}, 'POST');
    if (!res.ok) { toast('Грешка при стартиране', 'err'); return; }
    INV.session = {id:res.session_id, mode:res.mode, items_counted:0, items_total:0, zones_completed:0, zones_total:INV.zones.length};
    showScreen('screen-counting');
    renderCountingZones();
}

function skipToHub() {
    showScreen('screen-hub');
    loadHub();
}

// ══════════════════════════════════════════════════════
// COUNTING
// ══════════════════════════════════════════════════════
async function goCountingScreen() {
    await loadZones();
    if (!INV.session) {
        const res = await api('start_session', {mode: INV.mode || 'quick'}, 'POST');
        if (res.ok) INV.session = {id:res.session_id, mode:res.mode, items_counted:0, items_total:0, zones_completed:0, zones_total:INV.zones.length};
    }
    showScreen('screen-counting');
    renderCountingZones();
}

function renderCountingZones() {
    const mode = INV.session?.mode || INV.mode || 'quick';
    document.getElementById('cntModeTag').textContent = mode === 'full' ? 'ПЪЛЕН РЕЖИМ' : 'БЪРЗ РЕЖИМ';
    const done = INV.session?.zones_completed || 0;
    const total = INV.zones.length;
    document.getElementById('cntLbl').textContent = `${done} от ${total} места преброени`;
    document.getElementById('cntBar').style.width = total > 0 ? (done/total*100)+'%' : '0%';
    const w = document.getElementById('cntZones');
    w.innerHTML = INV.zones.map(z => {
        const ph = z.photo_url
            ? `<img src="${esc(z.photo_url)}" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">`
            : `<div style="display:flex;align-items:center;justify-content:center;height:100%;background:rgba(99,102,241,.08)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:24px;height:24px;color:#4f46e5"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg></div>`;
        return `<div class="czc" onclick="enterZone(${z.id})">
            <div class="czc-photo" style="position:relative;overflow:hidden">${ph}</div>
            <div class="czc-body">
                <div class="czc-name">${esc(z.name)}</div>
                <div class="czc-sub">${ztLabel(z.zone_type)}</div>
            </div>
            <div class="czc-arr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
        </div>`;
    }).join('');
}

function enterZone(zId) {
    // S62: full counting engine per zone
    toast('Броене по артикул — S62');
}

// ══════════════════════════════════════════════════════
// ZONE MODAL
// ══════════════════════════════════════════════════════
function openZoneModal(ctx) {
    INV.zm = { id:0, name:'', type:'shop', photo:null, ctx: ctx||'hub' };
    document.getElementById('zmTitle').textContent = 'Добави място';
    document.getElementById('zmNameTxt').textContent = 'Кажи с глас...';
    document.getElementById('zmNameTxt').style.color = '#64748b';
    document.getElementById('zmPhotoImg').style.display = 'none';
    document.getElementById('zmPhotoImg').src = '';
    document.querySelectorAll('.zt-tab').forEach((t,i) => t.classList.toggle('active', i===0));
    document.getElementById('zoneModal').classList.add('open');
}

function editZone(id) {
    const z = INV.zones.find(x => x.id == id);
    if (!z) return;
    INV.zm = { id:z.id, name:z.name, type:z.zone_type, photo:z.photo_url, ctx:'hub' };
    document.getElementById('zmTitle').textContent = 'Редактирай место';
    document.getElementById('zmNameTxt').textContent = z.name;
    document.getElementById('zmNameTxt').style.color = '#e2e8f0';
    if (z.photo_url) {
        const img = document.getElementById('zmPhotoImg');
        img.src = z.photo_url; img.style.display = 'block';
    } else {
        document.getElementById('zmPhotoImg').style.display = 'none';
    }
    document.querySelectorAll('.zt-tab').forEach(t => {
        t.classList.toggle('active', t.getAttribute('onclick').includes("'"+z.zone_type+"'"));
    });
    document.getElementById('zoneModal').classList.add('open');
}

function closeZoneModal() {
    document.getElementById('zoneModal').classList.remove('open');
}

function selZoneType(t, el) {
    INV.zm.type = t;
    document.querySelectorAll('.zt-tab').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
}

function triggerPhoto() { document.getElementById('zmPhotoInput').click(); }

function addPhotoToZone(zId) {
    document.getElementById('zmPhotoInput').dataset.direct = zId;
    document.getElementById('zmPhotoInput').click();
}

async function handlePhotoUpload(input) {
    const file = input.files[0]; if (!file) return;
    const fd = new FormData();
    fd.append('photo', file);
    toast('Качвам...');
    const d = await fetch('inventory.php?ajax=upload_zone_photo', {method:'POST', body:fd}).then(r=>r.json());
    input.value = '';
    if (!d.ok) { toast(d.error||'Грешка', 'err'); return; }
    INV.zm.photo = d.url;
    const img = document.getElementById('zmPhotoImg');
    img.src = d.url; img.style.display = 'block';
    toast('Снимката е качена', 'ok');
    // Direct zone photo update (tap on card)
    if (input.dataset.direct) {
        const zId = parseInt(input.dataset.direct);
        input.dataset.direct = '';
        await api('update_zone_photo', {id:zId, photo_url:d.url}, 'POST');
        const z = INV.zones.find(x=>x.id===zId);
        if (z) z.photo_url = d.url;
        const onHub = document.getElementById('screen-hub').classList.contains('active');
        onHub ? renderHubZones() : renderOnbZones();
    }
}

async function saveZone() {
    const name = INV.zm.name;
    if (!name) { toast('Кажи ид на место', 'err'); return; }
    const existingPhoto = INV.zm.id > 0
        ? INV.zones.find(z=>z.id===INV.zm.id)?.photo_url : null;
    if (!INV.zm.photo && !existingPhoto) { toast('Снимката е задължителна', 'err'); return; }
    const payload = {
        id: INV.zm.id, name, zone_type: INV.zm.type,
        photo_url: INV.zm.photo || existingPhoto,
        sort_order: INV.zones.length,
    };
    const res = await api('save_zone', payload, 'POST');
    if (!res.ok) { toast(res.error||'Грешка', 'err'); return; }
    closeZoneModal();
    toast('Запазено', 'ok');
    await loadZones();
    const onHub = document.getElementById('screen-hub').classList.contains('active');
    onHub ? renderHubZones() : renderOnbZones();
}

async function delZone(id) {
    if (!confirm('Изтрий това место?')) return;
    await api('delete_zone', {id}, 'POST');
    INV.zones = INV.zones.filter(z=>z.id!==id);
    const onHub = document.getElementById('screen-hub').classList.contains('active');
    onHub ? renderHubZones() : renderOnbZones();
    toast('Изтрито');
}

// ══════════════════════════════════════════════════════
// VOICE
// ══════════════════════════════════════════════════════
let _rec = null, _recCb = null;

function startVoice(cb, label) {
    _recCb = cb;
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { toast('Гласът не се поддържа', 'err'); return; }
    _rec = new SR();
    _rec.lang = 'bg-BG'; _rec.continuous = false; _rec.interimResults = true;
    document.getElementById('recOv').classList.add('active');
    document.getElementById('recDot').classList.remove('done');
    document.getElementById('recLbl').textContent = label || 'ЗАПИСВА...';
    document.getElementById('recTrans').textContent = '';
    document.getElementById('recSendBtn').style.display = 'none';
    _rec.onresult = e => {
        let t = '';
        for (let i=0;i<e.results.length;i++) t+=e.results[i][0].transcript;
        document.getElementById('recTrans').textContent = t;
        if (e.results[e.results.length-1].isFinal) {
            document.getElementById('recDot').classList.add('done');
            document.getElementById('recLbl').textContent = 'ГОТОВО';
            document.getElementById('recSendBtn').style.display = 'block';
        }
    };
    _rec.onerror = () => {
        document.getElementById('recOv').classList.remove('active');
        toast('Грешка при запис', 'err');
    };
    _rec.start();
}

function recSend() {
    const t = document.getElementById('recTrans').textContent.trim();
    document.getElementById('recOv').classList.remove('active');
    if (_recCb && t) _recCb(t);
}

function recCancel() {
    if (_rec) _rec.stop();
    document.getElementById('recOv').classList.remove('active');
}

function startZoneVoice() {
    startVoice(text => {
        INV.zm.name = text;
        document.getElementById('zmNameTxt').textContent = text;
        document.getElementById('zmNameTxt').style.color = '#e2e8f0';
    }, 'КАЗВАЙ ИМЕТО...');
}

// ══════════════════════════════════════════════════════
// INFO
// ══════════════════════════════════════════════════════
const INFO = {
    hub: { title:'Скрити пари', body:'Системата следи колко стока имаш на всяко място. Всеки преброен артикул увеличава точността на AI съветите. Броенето се прави веднъж дневно, по 10-15 минути.' },
    zones: { title:'Места в магазина', body:'<strong>Защо снимки?</strong> AI помни как изглежда всяко място — когато ти каже "Рафт 2" ти знаеш точно кое е.<br><br><strong>Примери:</strong> Щендер до входа, Рафт зад касата долен, Витрина дясна, Стойка с чорапи, Склад рафт 1 ляво...<br><br>За склад — снимай рафт по рафт, отделение по отделение.' },
};

function showInfo(k) {
    const c = INFO[k]; if (!c) return;
    document.getElementById('infoTitle').textContent = c.title;
    document.getElementById('infoBody').innerHTML = c.body;
    document.getElementById('infoModal').classList.add('open');
}

// ══════════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════════
function ztLabel(t) { return {shop:'Магазин',storage:'Склад',cashier:'Каса',other:'Друго'}[t]||t; }

function esc(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function api(endpoint, body, method) {
    if (!method || method==='GET') {
        return fetch(`inventory.php?ajax=${endpoint}`).then(r=>r.json());
    }
    return fetch(`inventory.php?ajax=${endpoint}`, {
        method, headers:{'Content-Type':'application/json'},
        body: JSON.stringify(body)
    }).then(r=>r.json());
}

function toast(msg, type) {
    const t = document.getElementById('iToast');
    t.textContent = msg;
    t.className = 'inv-toast show' + (type?' '+type:'');
    clearTimeout(t._t);
    t._t = setTimeout(()=>t.classList.remove('show'), 2500);
}
</script>
</body>
</html>
