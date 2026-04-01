<?php
session_start();
if (!isset($_SESSION['tenant_id'])) { header('Location: login.php'); exit; }

require_once 'config/database.php';
require_once 'config/config.php';

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION['user_id'];
$store_id  = $_SESSION['store_id'];
$role      = $_SESSION['role'];

// Tenant info
$stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch();

// User info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$currency        = $tenant['currency'] ?? 'EUR';
$supato_mode     = $tenant['supato_mode'] ?? 0;
$max_discount    = ($role === 'seller') ? (float)($user['max_discount_pct'] ?? 50) : 100;
$notify_discount = ($role === 'seller') ? (float)($user['discount_notify_pct'] ?? 30) : 100;

// Wholesale clients
$clients = $pdo->prepare("SELECT id, name FROM customers WHERE tenant_id = ? AND is_wholesale = 1 AND is_active = 1 ORDER BY name");
$clients->execute([$tenant_id]);
$wholesale_clients = $clients->fetchAll();

// Parked sales from session
if (!isset($_SESSION['parked_sales'])) $_SESSION['parked_sales'] = [];
$parked_count = count($_SESSION['parked_sales']);

$sale_label = $supato_mode ? 'Изходящо движение' : 'Продажба';
?>
<!doctype html>
<html lang="bg">
<head>
    <meta charset="utf-8"/>
    <title>Продажба — RunMyStore</title>
    <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no"/>
    <link href="./css/vendors/aos.css" rel="stylesheet"/>
    <link href="./style.css" rel="stylesheet"/>
    <style>
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { overscroll-behavior: none; }

        /* ── HEADER ── */
        .sale-header {
            position: fixed; top: 0; left: 0; right: 0; z-index: 50;
            background: #0f0f17;
            padding: 10px 12px 8px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(99,102,241,0.15);
        }
        .sale-header.wholesale-mode { background: #1a1a2e; border-bottom-color: rgba(99,102,241,0.4); }
        .wholesale-badge {
            font-size: 10px; font-weight: 700; letter-spacing: 0.05em;
            background: rgba(99,102,241,0.2); color: #818cf8;
            border: 1px solid rgba(99,102,241,0.3);
            padding: 2px 8px; border-radius: 20px;
        }

        /* ── CAMERA ── */
        .camera-wrap {
            position: fixed; top: 56px; left: 0; right: 0; z-index: 40;
            height: 160px; background: #000; overflow: hidden;
            transition: height 0.3s ease;
        }
        .camera-wrap.hidden-cam { height: 0; }
        #camera-video { width: 100%; height: 100%; object-fit: cover; }
        .camera-overlay {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            pointer-events: none;
        }
        .camera-frame {
            width: 160px; height: 60px;
            border: 2px solid rgba(99,102,241,0.7);
            border-radius: 8px;
            box-shadow: 0 0 0 2000px rgba(0,0,0,0.3);
        }
        .camera-frame.scanned {
            border-color: #22c55e;
            box-shadow: 0 0 0 2000px rgba(0,0,0,0.3), 0 0 20px rgba(34,197,94,0.5);
        }
        .cam-close {
            position: absolute; top: 8px; right: 8px;
            background: rgba(0,0,0,0.6); border: none; color: #fff;
            width: 28px; height: 28px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; cursor: pointer; pointer-events: all;
        }
        .cam-toggle {
            position: fixed; top: 58px; right: 12px; z-index: 45;
            background: rgba(99,102,241,0.2); border: 1px solid rgba(99,102,241,0.3);
            color: #818cf8; border-radius: 8px; padding: 4px 10px;
            font-size: 11px; font-weight: 600; cursor: pointer;
            transition: all 0.2s;
        }

        /* ── SEARCH ── */
        .search-wrap {
            position: fixed; left: 0; right: 0; z-index: 39;
            background: #0f0f17; padding: 8px 12px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex; gap: 8px; align-items: center;
            transition: top 0.3s ease;
        }
        .search-input {
            flex: 1; background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1);
            color: #e2e8f0; border-radius: 10px;
            padding: 9px 12px; font-size: 15px; outline: none;
            transition: border-color 0.2s;
        }
        .search-input:focus { border-color: rgba(99,102,241,0.5); }
        .mic-btn {
            width: 40px; height: 40px; border-radius: 10px; border: none;
            background: rgba(99,102,241,0.15); color: #818cf8;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; flex-shrink: 0; transition: all 0.2s;
        }
        .mic-btn.recording { background: rgba(239,68,68,0.2); color: #f87171; animation: pulse 1s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }

        /* ── SEARCH RESULTS ── */
        .search-results {
            position: fixed; left: 0; right: 0; z-index: 38;
            background: #13131f; border-bottom: 1px solid rgba(255,255,255,0.06);
            max-height: 200px; overflow-y: auto; display: none;
            transition: top 0.3s ease;
        }
        .search-result-item {
            padding: 10px 12px; display: flex; align-items: center;
            justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.04);
            cursor: pointer; transition: background 0.15s;
        }
        .search-result-item:active { background: rgba(99,102,241,0.1); }
        .result-code { font-size: 11px; color: #6366f1; font-weight: 600; }
        .result-name { font-size: 13px; color: #e2e8f0; }
        .result-price { font-size: 13px; font-weight: 600; color: #a5b4fc; }
        .result-sold { font-size: 10px; color: #475569; }

        /* ── ITEMS LIST ── */
        .items-list {
            position: fixed; left: 0; right: 0;
            overflow-y: auto; padding: 8px 12px;
            transition: top 0.3s ease, bottom 0.3s ease;
        }
        .sale-item {
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; padding: 10px 12px;
            margin-bottom: 8px; display: flex;
            align-items: center; justify-content: space-between;
            position: relative; overflow: hidden;
            transition: transform 0.2s, background 0.2s;
        }
        .sale-item.swipe-left { transform: translateX(-80px); }
        .item-name { font-size: 13px; color: #e2e8f0; font-weight: 500; }
        .item-detail { font-size: 11px; color: #64748b; margin-top: 2px; }
        .item-price { font-size: 14px; font-weight: 700; color: #a5b4fc; text-align: right; }
        .item-qty { font-size: 11px; color: #64748b; text-align: right; }
        .item-delete-btn {
            position: absolute; right: -80px; top: 0; bottom: 0;
            width: 72px; background: rgba(239,68,68,0.8);
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 12px; font-weight: 600;
            cursor: pointer; transition: right 0.2s;
        }
        .sale-item.swipe-left .item-delete-btn { right: 0; }
        .empty-state {
            text-align: center; padding: 40px 20px; color: #475569;
        }
        .empty-state svg { opacity: 0.3; margin: 0 auto 12px; display: block; }

        /* ── BOTTOM BAR ── */
        .bottom-bar {
            position: fixed; bottom: 60px; left: 0; right: 0; z-index: 50;
            background: #13131f;
            border-top: 1px solid rgba(255,255,255,0.08);
            padding: 10px 12px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .bottom-bar-info { display: flex; align-items: center; gap: 12px; }
        .bar-count { font-size: 12px; color: #64748b; }
        .bar-total { font-size: 18px; font-weight: 700; color: #e2e8f0; }
        .bar-expand {
            background: rgba(99,102,241,0.15); border: 1px solid rgba(99,102,241,0.3);
            color: #818cf8; border-radius: 10px; padding: 8px 16px;
            font-size: 13px; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; gap: 6px;
            transition: all 0.2s;
        }

        /* ── BOTTOM NAV ── */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 60;
            background: #0f0f17;
            border-top: 1px solid rgba(255,255,255,0.08);
            display: flex; height: 60px;
        }
        .nav-item {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 3px; text-decoration: none; color: #3f3f5a;
            font-size: 10px; font-weight: 500; transition: color 0.2s;
        }
        .nav-item.active { color: #6366f1; }
        .nav-item svg { width: 22px; height: 22px; }

        /* ── POPUP ── */
        .popup-overlay {
            position: fixed; inset: 0; z-index: 100;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);
            display: flex; align-items: flex-end; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity 0.2s;
        }
        .popup-overlay.open { opacity: 1; pointer-events: all; }
        .popup-box {
            background: #13131f; border-radius: 20px 20px 0 0;
            padding: 20px 16px 32px; width: 100%; max-width: 480px;
            border-top: 1px solid rgba(255,255,255,0.1);
            transform: translateY(100%); transition: transform 0.3s ease;
        }
        .popup-overlay.open .popup-box { transform: translateY(0); }
        .popup-product-name { font-size: 16px; font-weight: 700; color: #e2e8f0; margin-bottom: 2px; }
        .popup-product-price { font-size: 22px; font-weight: 700; color: #6366f1; margin-bottom: 16px; }

        /* Qty control */
        .qty-control {
            display: flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.05); border-radius: 12px;
            padding: 8px 12px; margin-bottom: 12px;
        }
        .qty-btn {
            width: 36px; height: 36px; border-radius: 8px; border: none;
            background: rgba(99,102,241,0.2); color: #818cf8;
            font-size: 20px; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.15s;
        }
        .qty-btn:active { background: rgba(99,102,241,0.4); }
        .qty-display {
            flex: 1; text-align: center; font-size: 28px; font-weight: 700;
            color: #e2e8f0; min-width: 60px;
        }

        /* Numpad */
        .numpad {
            display: grid; grid-template-columns: repeat(5, 1fr);
            gap: 6px; margin-bottom: 14px;
        }
        .num-btn {
            height: 44px; border-radius: 10px; border: none;
            background: rgba(255,255,255,0.07); color: #e2e8f0;
            font-size: 18px; font-weight: 600; cursor: pointer;
            transition: background 0.15s;
        }
        .num-btn:active { background: rgba(99,102,241,0.3); }

        /* Discount */
        .discount-row {
            display: flex; align-items: center; gap: 8px; margin-bottom: 16px;
        }
        .discount-label { font-size: 12px; color: #64748b; flex-shrink: 0; }
        .discount-input {
            width: 70px; background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0;
            border-radius: 8px; padding: 6px 10px; font-size: 14px;
            text-align: center; outline: none;
        }
        .discount-note { font-size: 11px; color: #f87171; display: none; }

        /* Popup actions */
        .popup-actions { display: flex; gap: 8px; }
        .btn-cancel {
            flex: 1; padding: 13px; border-radius: 12px; border: none;
            background: rgba(255,255,255,0.07); color: #94a3b8;
            font-size: 15px; font-weight: 600; cursor: pointer;
        }
        .btn-add {
            flex: 2; padding: 13px; border-radius: 12px; border: none;
            background: linear-gradient(to top, #4f46e5, #6366f1);
            color: #fff; font-size: 15px; font-weight: 700; cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-add:active { opacity: 0.8; }

        /* ── SUMMARY SHEET ── */
        .summary-overlay {
            position: fixed; inset: 0; z-index: 90;
            background: rgba(0,0,0,0.5);
            opacity: 0; pointer-events: none; transition: opacity 0.3s;
        }
        .summary-overlay.open { opacity: 1; pointer-events: all; }
        .summary-sheet {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 91;
            background: #0f0f17; border-radius: 20px 20px 0 0;
            border-top: 1px solid rgba(255,255,255,0.1);
            max-height: 90vh; overflow-y: auto;
            transform: translateY(100%); transition: transform 0.3s ease;
            padding-bottom: 80px;
        }
        .summary-sheet.open { transform: translateY(0); }
        .sheet-handle {
            width: 40px; height: 4px; background: rgba(255,255,255,0.2);
            border-radius: 2px; margin: 12px auto 16px;
        }
        .sheet-title {
            font-size: 16px; font-weight: 700; color: #e2e8f0;
            padding: 0 16px 12px; border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        .summary-item {
            padding: 10px 16px; display: flex;
            justify-content: space-between; align-items: flex-start;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .summary-item-name { font-size: 13px; color: #e2e8f0; }
        .summary-item-detail { font-size: 11px; color: #64748b; margin-top: 2px; }
        .summary-item-total { font-size: 14px; font-weight: 600; color: #a5b4fc; }
        .summary-totals {
            padding: 14px 16px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .total-row {
            display: flex; justify-content: space-between;
            margin-bottom: 6px; font-size: 13px; color: #94a3b8;
        }
        .total-row.final {
            font-size: 18px; font-weight: 700; color: #e2e8f0;
            margin-top: 10px; padding-top: 10px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .global-discount-wrap {
            display: flex; align-items: center; gap: 8px;
        }
        .global-discount-input {
            width: 70px; background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0;
            border-radius: 8px; padding: 6px 10px; font-size: 14px;
            text-align: center; outline: none;
        }

        /* Payment buttons */
        .payment-btns {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 8px; padding: 12px 16px;
        }
        .pay-btn {
            padding: 13px; border-radius: 12px; border: none;
            font-size: 14px; font-weight: 600; cursor: pointer;
            transition: opacity 0.2s;
        }
        .pay-btn:active { opacity: 0.7; }
        .pay-cash { background: rgba(34,197,94,0.15); color: #4ade80; border: 1px solid rgba(34,197,94,0.3); }
        .pay-card { background: rgba(99,102,241,0.15); color: #818cf8; border: 1px solid rgba(99,102,241,0.3); }
        .pay-transfer { background: rgba(251,191,36,0.15); color: #fbbf24; border: 1px solid rgba(251,191,36,0.3); }
        .pay-deferred { background: rgba(239,68,68,0.1); color: #f87171; border: 1px solid rgba(239,68,68,0.2); }

        /* Cash payment */
        .cash-panel { padding: 0 16px 16px; display: none; }
        .cash-panel.open { display: block; }
        .cash-label { font-size: 12px; color: #64748b; margin-bottom: 8px; }
        .quick-cash {
            display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px;
        }
        .quick-cash-btn {
            padding: 8px 14px; border-radius: 10px; border: none;
            background: rgba(255,255,255,0.07); color: #e2e8f0;
            font-size: 13px; font-weight: 600; cursor: pointer;
            transition: background 0.15s;
        }
        .quick-cash-btn.selected { background: rgba(99,102,241,0.3); color: #a5b4fc; }
        .cash-input-wrap { display: flex; gap: 8px; align-items: center; margin-bottom: 10px; }
        .cash-input {
            flex: 1; background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0;
            border-radius: 10px; padding: 10px 14px; font-size: 16px; outline: none;
        }
        .change-display {
            background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.2);
            border-radius: 10px; padding: 10px 14px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .change-label { font-size: 12px; color: #64748b; }
        .change-amount { font-size: 20px; font-weight: 700; color: #4ade80; }
        .finalize-btn {
            width: 100%; padding: 15px; border-radius: 14px; border: none;
            background: linear-gradient(to top, #4f46e5, #6366f1);
            color: #fff; font-size: 16px; font-weight: 700; cursor: pointer;
            margin-top: 12px; transition: opacity 0.2s;
        }
        .finalize-btn:active { opacity: 0.8; }

        /* ── WHOLESALE CLIENT ── */
        .client-btn {
            display: flex; align-items: center; gap: 6px;
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
            color: #94a3b8; border-radius: 8px; padding: 5px 10px;
            font-size: 12px; font-weight: 500; cursor: pointer;
            transition: all 0.2s; white-space: nowrap;
        }
        .client-btn.selected { background: rgba(99,102,241,0.2); color: #818cf8; border-color: rgba(99,102,241,0.4); }
        .client-list-overlay {
            position: fixed; inset: 0; z-index: 110;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);
            display: flex; align-items: flex-end;
            opacity: 0; pointer-events: none; transition: opacity 0.2s;
        }
        .client-list-overlay.open { opacity: 1; pointer-events: all; }
        .client-list-box {
            background: #13131f; border-radius: 20px 20px 0 0;
            padding: 16px; width: 100%; max-height: 60vh; overflow-y: auto;
            transform: translateY(100%); transition: transform 0.3s;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .client-list-overlay.open .client-list-box { transform: translateY(0); }
        .client-search {
            width: 100%; background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.1); color: #e2e8f0;
            border-radius: 10px; padding: 9px 12px; font-size: 14px;
            outline: none; margin-bottom: 10px;
        }
        .client-item {
            padding: 12px; border-radius: 10px; cursor: pointer;
            color: #e2e8f0; font-size: 14px;
            transition: background 0.15s;
        }
        .client-item:active { background: rgba(99,102,241,0.15); }

        /* ── PARK ── */
        .park-badge {
            position: relative; display: flex; align-items: center;
        }
        .park-count {
            position: absolute; top: -4px; right: -4px;
            background: #ef4444; color: #fff; border-radius: 50%;
            width: 16px; height: 16px; font-size: 9px; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }

        /* ── VOICE ── */
        .voice-overlay {
            position: fixed; inset: 0; z-index: 120;
            background: rgba(0,0,0,0.85); backdrop-filter: blur(8px);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity 0.3s;
        }
        .voice-overlay.open { opacity: 1; pointer-events: all; }
        .voice-circle {
            width: 100px; height: 100px; border-radius: 50%;
            background: rgba(99,102,241,0.2); border: 2px solid #6366f1;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 24px; animation: voicePulse 1.5s infinite;
        }
        @keyframes voicePulse {
            0%,100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(99,102,241,0.4); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 20px rgba(99,102,241,0); }
        }
        .voice-text { font-size: 14px; color: #94a3b8; text-align: center; padding: 0 40px; }
        .voice-cancel {
            margin-top: 32px; background: rgba(255,255,255,0.1);
            border: none; color: #e2e8f0; border-radius: 12px;
            padding: 12px 32px; font-size: 14px; cursor: pointer;
        }

        /* ── BEEP ── */
        .scan-flash {
            position: fixed; inset: 0; z-index: 200;
            background: rgba(34,197,94,0.15);
            pointer-events: none; opacity: 0;
            transition: opacity 0.1s;
        }
        .scan-flash.flash { opacity: 1; }
    </style>
</head>
<body class="bg-gray-950 font-inter text-base text-gray-200 antialiased" x-data="saleApp()" x-init="init()">

<!-- Scan flash -->
<div class="scan-flash" :class="{'flash': scanFlash}" @transitionend="scanFlash=false"></div>

<!-- HEADER -->
<div class="sale-header" :class="{'wholesale-mode': wholesaleMode}">
    <div class="flex items-center gap-10">
        <a href="actions.php" class="text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="text-sm font-semibold text-gray-200"><?= $sale_label ?></span>
    </div>
    <div class="flex items-center gap-8">
        <!-- Wholesale client button -->
        <button class="client-btn" :class="{'selected': selectedClient}" @click="openClientList()">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0H5m14 0h2M5 21H3"/>
            </svg>
            <span x-text="selectedClient ? selectedClient.name : '+ Едро'"></span>
        </button>
        <!-- Parked -->
        <button class="park-badge" @click="openParked()" x-show="parkedSales.length > 0">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6M4 6h16M4 18h16"/>
            </svg>
            <span class="park-count" x-text="parkedSales.length"></span>
        </button>
    </div>
</div>

<!-- CAMERA -->
<div class="camera-wrap" :class="{'hidden-cam': !cameraOpen}" id="cameraWrap">
    <video id="camera-video" autoplay playsinline muted></video>
    <div class="camera-overlay">
        <div class="camera-frame" :class="{'scanned': scanSuccess}" id="cameraFrame"></div>
    </div>
    <button class="cam-close" @click="toggleCamera()">✕</button>
</div>
<button class="cam-toggle" :style="'top: ' + (cameraOpen ? '218px' : '66px')" @click="toggleCamera()">
    <span x-text="cameraOpen ? 'Скрий' : '📷 Камера'"></span>
</button>

<!-- SEARCH -->
<div class="search-wrap" :style="'top: ' + searchTop + 'px'">
    <input
        type="text"
        class="search-input"
        placeholder="Код или наименование..."
        x-model="searchQuery"
        @input="searchProducts()"
        @focus="showResults = searchResults.length > 0"
        inputmode="numeric"
        id="searchInput"
    />
    <button class="mic-btn" :class="{'recording': voiceRecording}" @click="startVoice()">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/>
        </svg>
    </button>
</div>

<!-- SEARCH RESULTS -->
<div class="search-results" :style="'top: ' + resultsTop + 'px; display: ' + (showResults ? 'block' : 'none')">
    <template x-for="p in searchResults" :key="p.id">
        <div class="search-result-item" @click="openPopup(p)">
            <div>
                <div class="result-code" x-text="p.code"></div>
                <div class="result-name" x-text="p.name"></div>
                <div class="result-sold" x-text="'Продадени: ' + p.sold_count + ' бр'"></div>
            </div>
            <div class="result-price" x-text="formatPrice(wholesaleMode && selectedClient ? p.wholesale_price : p.retail_price)"></div>
        </div>
    </template>
</div>

<!-- ITEMS LIST -->
<div class="items-list" :style="'top: ' + listTop + 'px; bottom: 116px'" id="itemsList">
    <template x-if="items.length === 0">
        <div class="empty-state">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-1.5 6h13M9 21a1 1 0 100-2 1 1 0 000 2zm10 0a1 1 0 100-2 1 1 0 000 2z"/>
            </svg>
            <p class="text-sm">Сканирай или въведи код</p>
        </div>
    </template>
    <template x-for="(item, idx) in items" :key="idx">
        <div
            class="sale-item"
            :class="{'swipe-left': item.swiped}"
            @touchstart="touchStart($event, idx)"
            @touchmove="touchMove($event, idx)"
            @touchend="touchEnd($event, idx)"
        >
            <div style="flex:1">
                <div class="item-name" x-text="item.name"></div>
                <div class="item-detail" x-text="item.qty + ' × ' + formatPrice(item.price) + (item.discount > 0 ? ' (-' + item.discount + '%)' : '')"></div>
            </div>
            <div style="text-align:right">
                <div class="item-price" x-text="formatPrice(itemTotal(item))"></div>
                <div class="item-qty" x-text="item.qty + ' бр'"></div>
            </div>
            <div class="item-delete-btn" @click="removeItem(idx)">Изтрий</div>
        </div>
    </template>
</div>

<!-- BOTTOM BAR -->
<div class="bottom-bar">
    <div class="bottom-bar-info">
        <span class="bar-count" x-text="items.length + ' арт.'"></span>
        <span class="bar-total" x-text="formatPrice(grandTotal())"></span>
    </div>
    <button class="bar-expand" @click="openSummary()">
        <span x-text="items.length === 0 ? 'Обобщение' : 'Плащане'"></span>
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
        </svg>
    </button>
</div>

<!-- BOTTOM NAV -->
<nav class="bottom-nav">
    <a href="chat.php" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
        <span>Чат</span>
    </a>
    <a href="warehouse.php" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        <span>Склад</span>
    </a>
    <a href="stats.php" class="nav-item">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <span>Статистики</span>
    </a>
    <a href="actions.php" class="nav-item active">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        <span>Въвеждане</span>
    </a>
</nav>

<!-- ═══════════════════════════════════════════
     POPUP — Артикул: количество + отстъпка
═══════════════════════════════════════════ -->
<div class="popup-overlay" :class="{'open': popupOpen}" @click.self="closePopup()">
    <div class="popup-box">
        <div class="popup-product-name" x-text="popup.name"></div>
        <div class="popup-product-price" x-text="formatPrice(popup.price)"></div>

        <!-- Qty -->
        <div class="qty-control">
            <button class="qty-btn" @click="popupQtyChange(-1)">−</button>
            <div class="qty-display" x-text="popup.qty"></div>
            <button class="qty-btn" @click="popupQtyChange(1)">+</button>
        </div>

        <!-- Numpad -->
        <div class="numpad">
            <template x-for="n in [1,2,3,4,5,6,7,8,9,0]" :key="n">
                <button class="num-btn" @click="popupNumPress(n)" x-text="n"></button>
            </template>
        </div>

        <!-- Discount -->
        <div class="discount-row">
            <span class="discount-label">Отстъпка %</span>
            <input
                type="number"
                class="discount-input"
                x-model="popup.discount"
                min="0"
                :max="maxDiscount"
                @input="checkDiscount()"
                placeholder="0"
            />
            <span class="discount-note" x-show="discountWarning" id="discountNote">Максимум <?= $max_discount ?>%</span>
        </div>

        <div class="popup-actions">
            <button class="btn-cancel" @click="closePopup()">Откажи</button>
            <button class="btn-add" @click="addToSale()">Добави</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     SUMMARY SHEET
═══════════════════════════════════════════ -->
<div class="summary-overlay" :class="{'open': summaryOpen}" @click.self="closeSummary()"></div>
<div class="summary-sheet" :class="{'open': summaryOpen}" id="summarySheet"
     @touchstart="sheetTouchStart($event)"
     @touchmove="sheetTouchMove($event)"
     @touchend="sheetTouchEnd($event)">
    <div class="sheet-handle"></div>
    <div class="sheet-title">Обобщение</div>

    <!-- Items -->
    <template x-for="(item, idx) in items" :key="idx">
        <div class="summary-item" @click="editItem(idx)">
            <div>
                <div class="summary-item-name" x-text="item.name"></div>
                <div class="summary-item-detail" x-text="item.qty + ' × ' + formatPrice(item.price) + (item.discount > 0 ? ' −' + item.discount + '%' : '')"></div>
            </div>
            <div class="summary-item-total" x-text="formatPrice(itemTotal(item))"></div>
        </div>
    </template>

    <!-- Totals -->
    <div class="summary-totals">
        <div class="total-row">
            <span>Междинна сума</span>
            <span x-text="formatPrice(subtotal())"></span>
        </div>
        <div class="total-row" x-show="globalDiscount > 0">
            <span>Обща отстъпка</span>
            <span x-text="'−' + formatPrice(globalDiscountAmount())"></span>
        </div>
        <div class="total-row">
            <div class="global-discount-wrap">
                <span>Обща отстъпка %</span>
                <input type="number" class="global-discount-input" x-model="globalDiscount" min="0" :max="maxDiscount" placeholder="0"/>
            </div>
        </div>
        <div class="total-row final">
            <span>За плащане</span>
            <span x-text="formatPrice(grandTotal())"></span>
        </div>
    </div>

    <!-- Payment buttons -->
    <div class="payment-btns">
        <button class="pay-btn pay-cash" @click="selectPayment('cash')">💵 Брой</button>
        <button class="pay-btn pay-card" @click="selectPayment('card')">💳 Карта</button>
        <button class="pay-btn pay-transfer" @click="selectPayment('transfer')">🏦 Превод</button>
        <button class="pay-btn pay-deferred" @click="selectPayment('deferred')">⏳ Отложено</button>
    </div>

    <!-- Park button -->
    <div style="padding: 0 16px 8px;">
        <button style="width:100%; padding:12px; border-radius:12px; border:none; background:rgba(255,255,255,0.05); color:#64748b; font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px;"
                @click="parkSale()">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6"/>
            </svg>
            Паркирай продажбата
        </button>
    </div>

    <!-- Cash panel -->
    <div class="cash-panel" :class="{'open': paymentMethod === 'cash'}" id="cashPanel">
        <div class="cash-label">Получено от клиента</div>
        <div class="quick-cash">
            <template x-for="amt in quickCashAmounts()" :key="amt">
                <button class="quick-cash-btn" :class="{'selected': receivedAmount == amt}" @click="setReceived(amt)" x-text="formatPrice(amt)"></button>
            </template>
        </div>
        <div class="cash-input-wrap">
            <input type="number" class="cash-input" x-model="receivedAmount" placeholder="Въведи сума" @input="calcChange()"/>
        </div>
        <div class="change-display" x-show="receivedAmount > 0">
            <span class="change-label">Ресто</span>
            <span class="change-amount" x-text="formatPrice(changeAmount())"></span>
        </div>
        <button class="finalize-btn" @click="finalizeSale()" :disabled="items.length === 0">
            ФИНАЛИЗИРАЙ
        </button>
    </div>

    <!-- Card panel -->
    <div x-show="paymentMethod === 'card'" style="padding: 0 16px 16px;">
        <?php if (defined('STRIPE_ENABLED') && STRIPE_ENABLED): ?>
        <button class="finalize-btn" style="background: linear-gradient(to top, #4f46e5, #818cf8);" @click="finalizeSale()">
            💳 Stripe Terminal
        </button>
        <?php endif; ?>
        <button class="finalize-btn" style="margin-top:8px;" @click="finalizeSale()">
            ✅ Платено с карта (собствен ПОС)
        </button>
    </div>

    <!-- Transfer panel -->
    <div x-show="paymentMethod === 'transfer'" style="padding: 0 16px 16px;">
        <button class="finalize-btn" @click="finalizeSale()">ФИНАЛИЗИРАЙ — Банков превод</button>
    </div>

    <!-- Deferred panel -->
    <div x-show="paymentMethod === 'deferred'" style="padding: 0 16px 16px;">
        <div class="cash-label" style="margin-bottom:8px;">Падеж</div>
        <input type="date" class="cash-input" x-model="deferredDate" style="margin-bottom:12px;"/>
        <button class="finalize-btn" style="background: linear-gradient(to top, #dc2626, #ef4444);" @click="finalizeSale()">
            ФИНАЛИЗИРАЙ — Отложено плащане
        </button>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     WHOLESALE CLIENT LIST
═══════════════════════════════════════════ -->
<div class="client-list-overlay" :class="{'open': clientListOpen}" @click.self="clientListOpen=false">
    <div class="client-list-box">
        <input type="text" class="client-search" placeholder="Търси клиент..." x-model="clientSearch"/>
        <div class="client-item" style="color:#64748b; font-size:12px;" @click="clearClient()">— Без клиент (дребно)</div>
        <template x-for="c in filteredClients()" :key="c.id">
            <div class="client-item" @click="selectClient(c)" x-text="c.name"></div>
        </template>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     VOICE OVERLAY
═══════════════════════════════════════════ -->
<div class="voice-overlay" :class="{'open': voiceOverlay}">
    <div class="voice-circle">
        <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="#6366f1" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 10v2a7 7 0 01-14 0v-2M12 19v4M8 23h8"/>
        </svg>
    </div>
    <div class="voice-text" x-text="voiceText || 'Говори... Кажи какво си продал'"></div>
    <button class="voice-cancel" @click="stopVoice()">Откажи</button>
</div>

<script src="./js/vendors/alpinejs-focus.min.js"></script>
<script src="./js/vendors/alpinejs.min.js" defer></script>

<script>
const CURRENCY = '<?= $currency ?>';
const MAX_DISCOUNT = <?= $max_discount ?>;
const NOTIFY_DISCOUNT = <?= $notify_discount ?>;
const ROLE = '<?= $role ?>';
const SUPATO_MODE = <?= $supato_mode ?>;
const TENANT_ID = <?= $tenant_id ?>;
const STORE_ID = <?= $store_id ?>;
const USER_ID = <?= $user_id ?>;

// Wholesale clients from PHP
const WHOLESALE_CLIENTS = <?= json_encode($wholesale_clients) ?>;

function saleApp() {
    return {
        // Layout
        cameraOpen: true,
        searchTop: 216,
        resultsTop: 264,
        listTop: 312,

        // Search
        searchQuery: '',
        searchResults: [],
        showResults: false,

        // Items
        items: [],

        // Popup
        popupOpen: false,
        popup: { id: null, name: '', price: 0, qty: 1, discount: 0, wholesale_price: 0, retail_price: 0 },
        discountWarning: false,
        editingIndex: -1,

        // Summary
        summaryOpen: false,
        globalDiscount: 0,
        paymentMethod: null,
        receivedAmount: '',
        deferredDate: '',

        // Client
        wholesaleMode: false,
        selectedClient: null,
        clientListOpen: false,
        clientSearch: '',

        // Parked
        parkedSales: [],

        // Voice
        voiceOverlay: false,
        voiceRecording: false,
        voiceText: '',
        recognition: null,

        // Camera
        videoStream: null,
        scanSuccess: false,
        scanFlash: false,
        barcodeScanner: null,

        // Touch swipe
        touchStartX: {},

        init() {
            this.parkedSales = JSON.parse(sessionStorage.getItem('parkedSales') || '[]');
            this.startCamera();
            this.adjustLayout();
            window.addEventListener('resize', () => this.adjustLayout());
        },

        adjustLayout() {
            this.searchTop = this.cameraOpen ? 216 : 64;
            this.resultsTop = this.searchTop + 56;
            this.listTop = this.resultsTop + (this.showResults ? 0 : 0) + 56;
        },

        // ── CAMERA ──
        async startCamera() {
            try {
                this.videoStream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment', width: { ideal: 1280 } }
                });
                const video = document.getElementById('camera-video');
                video.srcObject = this.videoStream;
                this.startBarcodeScanning();
            } catch(e) {
                this.cameraOpen = false;
                this.adjustLayout();
            }
        },

        startBarcodeScanning() {
            if (!('BarcodeDetector' in window)) return;
            const detector = new BarcodeDetector({ formats: ['ean_13','ean_8','code_128','qr_code','code_39'] });
            const video = document.getElementById('camera-video');
            let scanning = true;
            const scan = async () => {
                if (!scanning || !this.cameraOpen) return;
                try {
                    const barcodes = await detector.detect(video);
                    if (barcodes.length > 0) {
                        const code = barcodes[0].rawValue;
                        scanning = false;
                        await this.handleBarcode(code);
                        setTimeout(() => { scanning = true; }, 2000);
                    }
                } catch(e) {}
                requestAnimationFrame(scan);
            };
            requestAnimationFrame(scan);
        },

        async handleBarcode(code) {
            this.triggerScanEffect();
            const resp = await fetch('sale-search.php?q=' + encodeURIComponent(code) + '&barcode=1&tenant_id=' + TENANT_ID + '&store_id=' + STORE_ID);
            const data = await resp.json();
            if (data.length === 1) {
                // Direct add with qty 1
                const p = data[0];
                const price = this.wholesaleMode && this.selectedClient ? p.wholesale_price : p.retail_price;
                this.items.push({ id: p.id, name: p.name, code: p.code, price: parseFloat(price), qty: 1, discount: 0 });
            } else if (data.length > 1) {
                this.searchResults = data;
                this.showResults = true;
            }
        },

        triggerScanEffect() {
            this.scanFlash = true;
            this.scanSuccess = true;
            this.playBeep();
            setTimeout(() => { this.scanSuccess = false; }, 500);
            setTimeout(() => { this.scanFlash = false; }, 200);
        },

        playBeep() {
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain); gain.connect(ctx.destination);
                osc.frequency.value = 1200; osc.type = 'sine';
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.15);
                osc.start(); osc.stop(ctx.currentTime + 0.15);
            } catch(e) {}
        },

        toggleCamera() {
            this.cameraOpen = !this.cameraOpen;
            this.adjustLayout();
            if (!this.cameraOpen && this.videoStream) {
                this.videoStream.getTracks().forEach(t => t.stop());
                this.videoStream = null;
            } else if (this.cameraOpen) {
                this.startCamera();
            }
        },

        // ── SEARCH ──
        async searchProducts() {
            const q = this.searchQuery.trim();
            if (q.length === 0) { this.showResults = false; return; }
            const resp = await fetch('sale-search.php?q=' + encodeURIComponent(q) + '&tenant_id=' + TENANT_ID + '&store_id=' + STORE_ID);
            const data = await resp.json();
            this.searchResults = data;
            this.showResults = data.length > 0;
            this.listTop = this.resultsTop + (this.showResults ? Math.min(data.length * 58, 200) : 0) + 56;
        },

        // ── POPUP ──
        openPopup(p) {
            this.showResults = false;
            this.searchQuery = '';
            const price = this.wholesaleMode && this.selectedClient ? p.wholesale_price : p.retail_price;
            this.popup = { id: p.id, name: p.name, price: parseFloat(price), qty: 1, discount: 0,
                           wholesale_price: p.wholesale_price, retail_price: p.retail_price };
            this.discountWarning = false;
            this.editingIndex = -1;
            this.popupOpen = true;
        },

        closePopup() {
            this.popupOpen = false;
        },

        popupNumPress(n) {
            const current = String(this.popup.qty);
            if (current === '1' && this.popup._firstPress !== false) {
                this.popup.qty = n;
                this.popup._firstPress = false;
            } else {
                this.popup.qty = parseInt(String(this.popup.qty) + String(n)) || 1;
            }
        },

        popupQtyChange(delta) {
            this.popup.qty = Math.max(1, (parseInt(this.popup.qty) || 1) + delta);
            this.popup._firstPress = false;
        },

        checkDiscount() {
            this.discountWarning = parseFloat(this.popup.discount) > MAX_DISCOUNT;
            if (this.discountWarning) this.popup.discount = MAX_DISCOUNT;
        },

        addToSale() {
            if (this.editingIndex >= 0) {
                this.items[this.editingIndex] = { ...this.popup };
            } else {
                // Check if same product already in cart
                const existing = this.items.findIndex(i => i.id === this.popup.id && i.discount === parseFloat(this.popup.discount || 0));
                if (existing >= 0) {
                    this.items[existing].qty += parseInt(this.popup.qty);
                } else {
                    this.items.push({
                        id: this.popup.id,
                        name: this.popup.name,
                        price: this.popup.price,
                        qty: parseInt(this.popup.qty),
                        discount: parseFloat(this.popup.discount || 0),
                        swiped: false
                    });
                }
            }
            this.closePopup();
            // Notify if discount too high
            if (ROLE === 'seller' && parseFloat(this.popup.discount) >= NOTIFY_DISCOUNT) {
                this.notifyOwnerDiscount(this.popup);
            }
        },

        editItem(idx) {
            const item = this.items[idx];
            this.popup = { ...item, _firstPress: false };
            this.editingIndex = idx;
            this.popupOpen = true;
            this.closeSummary();
        },

        removeItem(idx) {
            this.items.splice(idx, 1);
        },

        // ── SWIPE ──
        touchStart(e, idx) {
            this.touchStartX[idx] = e.touches[0].clientX;
        },
        touchMove(e, idx) {
            const diff = this.touchStartX[idx] - e.touches[0].clientX;
            if (diff > 30) this.items[idx].swiped = true;
            else if (diff < -10) this.items[idx].swiped = false;
        },
        touchEnd(e, idx) {},

        // ── TOTALS ──
        itemTotal(item) {
            const disc = parseFloat(item.discount) || 0;
            return item.price * item.qty * (1 - disc / 100);
        },
        subtotal() {
            return this.items.reduce((sum, i) => sum + this.itemTotal(i), 0);
        },
        globalDiscountAmount() {
            return this.subtotal() * (parseFloat(this.globalDiscount) || 0) / 100;
        },
        grandTotal() {
            return this.subtotal() - this.globalDiscountAmount();
        },

        // ── SUMMARY ──
        openSummary() {
            this.summaryOpen = true;
        },
        closeSummary() {
            this.summaryOpen = false;
        },

        // Sheet drag to close
        sheetStartY: 0,
        sheetTouchStart(e) { this.sheetStartY = e.touches[0].clientY; },
        sheetTouchMove(e) {},
        sheetTouchEnd(e) {
            const diff = e.changedTouches[0].clientY - this.sheetStartY;
            if (diff > 80) this.closeSummary();
        },

        // ── PAYMENT ──
        selectPayment(method) {
            this.paymentMethod = method;
            if (method === 'cash') {
                this.receivedAmount = '';
                this.$nextTick(() => {
                    document.querySelector('.cash-input')?.focus();
                });
            }
        },

        quickCashAmounts() {
            const total = this.grandTotal();
            const bills = [5,10,20,50,100,200,500];
            const result = [];
            for (const b of bills) {
                if (b >= total && result.length < 4) result.push(b);
                if (result.length === 4) break;
            }
            // Smart: round up
            const rounded = Math.ceil(total / 5) * 5;
            if (!result.includes(rounded) && rounded >= total) result.unshift(rounded);
            return result.slice(0, 4);
        },

        setReceived(amt) {
            this.receivedAmount = amt;
        },

        calcChange() {},

        changeAmount() {
            const change = parseFloat(this.receivedAmount) - this.grandTotal();
            return change >= 0 ? change : 0;
        },

        async finalizeSale() {
            if (this.items.length === 0) return;
            const payload = {
                items: this.items,
                payment_method: this.paymentMethod,
                total: this.grandTotal(),
                global_discount: this.globalDiscount,
                client_id: this.selectedClient?.id || null,
                received_amount: this.receivedAmount || null,
                deferred_date: this.deferredDate || null,
                store_id: STORE_ID,
                tenant_id: TENANT_ID,
                user_id: USER_ID,
                supato_mode: SUPATO_MODE
            };
            try {
                const resp = await fetch('sale-save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await resp.json();
                if (result.success) {
                    this.items = [];
                    this.globalDiscount = 0;
                    this.paymentMethod = null;
                    this.selectedClient = null;
                    this.wholesaleMode = false;
                    this.closeSummary();
                    this.showSuccessToast(result.sale_id);
                } else {
                    alert('Грешка: ' + (result.error || 'Непозната грешка'));
                }
            } catch(e) {
                alert('Грешка при запис');
            }
        },

        showSuccessToast(saleId) {
            // Simple toast
            const toast = document.createElement('div');
            toast.style.cssText = 'position:fixed;top:80px;left:50%;transform:translateX(-50%);background:rgba(34,197,94,0.9);color:#fff;padding:12px 24px;border-radius:12px;font-weight:600;font-size:14px;z-index:999;';
            toast.textContent = '✓ Продажбата е записана';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2500);
        },

        // ── PARK ──
        parkSale() {
            if (this.items.length === 0) return;
            this.parkedSales.push({
                items: [...this.items],
                client: this.selectedClient,
                time: new Date().toLocaleTimeString('bg')
            });
            sessionStorage.setItem('parkedSales', JSON.stringify(this.parkedSales));
            this.items = [];
            this.selectedClient = null;
            this.wholesaleMode = false;
            this.closeSummary();
        },

        openParked() {
            // Simple: restore first parked
            if (this.parkedSales.length === 0) return;
            const parked = this.parkedSales.shift();
            sessionStorage.setItem('parkedSales', JSON.stringify(this.parkedSales));
            this.items = parked.items;
            this.selectedClient = parked.client;
            this.wholesaleMode = !!parked.client;
        },

        // ── CLIENT ──
        openClientList() {
            this.clientSearch = '';
            this.clientListOpen = true;
        },

        selectClient(c) {
            this.selectedClient = c;
            this.wholesaleMode = true;
            this.clientListOpen = false;
            // Reprice items
            this.items = this.items.map(item => {
                const found = this.searchResults.find(p => p.id === item.id);
                return item;
            });
        },

        clearClient() {
            this.selectedClient = null;
            this.wholesaleMode = false;
            this.clientListOpen = false;
        },

        filteredClients() {
            const q = this.clientSearch.toLowerCase();
            return WHOLESALE_CLIENTS.filter(c => c.name.toLowerCase().includes(q));
        },

        // ── VOICE ──
        startVoice() {
            if (!('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) {
                alert('Гласовият вход не се поддържа в този браузър');
                return;
            }
            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            this.recognition = new SR();
            this.recognition.lang = 'bg-BG';
            this.recognition.continuous = false;
            this.recognition.interimResults = false;
            this.recognition.onstart = () => {
                this.voiceOverlay = true;
                this.voiceRecording = true;
                this.voiceText = 'Слушам...';
            };
            this.recognition.onresult = (e) => {
                const transcript = e.results[0][0].transcript;
                this.voiceText = '"' + transcript + '"';
                setTimeout(() => {
                    this.stopVoice();
                    this.processVoiceCommand(transcript);
                }, 800);
            };
            this.recognition.onerror = () => { this.stopVoice(); };
            this.recognition.start();
        },

        stopVoice() {
            this.voiceOverlay = false;
            this.voiceRecording = false;
            if (this.recognition) { this.recognition.stop(); this.recognition = null; }
        },

        async processVoiceCommand(text) {
            const resp = await fetch('sale-voice.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text, tenant_id: TENANT_ID, store_id: STORE_ID })
            });
            const data = await resp.json();
            if (data.items && data.items.length > 0) {
                // Show confirm before adding
                const names = data.items.map(i => i.qty + '× ' + i.name).join('\n');
                if (confirm('Добавям:\n' + names + '\n\nПотвърди?')) {
                    data.items.forEach(i => {
                        const price = this.wholesaleMode ? i.wholesale_price : i.retail_price;
                        this.items.push({ id: i.id, name: i.name, price: parseFloat(price), qty: i.qty, discount: 0, swiped: false });
                    });
                }
            }
        },

        async notifyOwnerDiscount(item) {
            await fetch('notify-discount.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ item, tenant_id: TENANT_ID, user_id: USER_ID, store_id: STORE_ID })
            });
        },

        // ── HELPERS ──
        formatPrice(amount) {
            return parseFloat(amount || 0).toFixed(2) + ' ' + CURRENCY;
        },

        maxDiscount: MAX_DISCOUNT
    };
}
</script>
</body>
</html>
