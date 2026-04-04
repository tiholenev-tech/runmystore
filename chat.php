<?php
/**
 * chat.php — AI First Dashboard
 * Cruip Dark тема. Proactive Briefing. Пулс бутон. Deeplinks. Voice.
 * С17 — уеднаквен bottom nav
 */
session_start();
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$tenant_id = $_SESSION['tenant_id'];
$user_id   = $_SESSION['user_id'];
$store_id  = $_SESSION['store_id'];
$role      = $_SESSION['role'] ?? 'seller';
$user_name = $_SESSION['user_name'] ?? '';

$messages = DB::run(
    'SELECT role, content, created_at FROM chat_messages WHERE tenant_id = ? AND store_id = ? ORDER BY created_at ASC LIMIT 50',
    [$tenant_id, $store_id]
)->fetchAll();

$store = DB::run('SELECT name FROM stores WHERE id = ? LIMIT 1', [$store_id])->fetch();
$store_name = $store ? $store['name'] : 'Магазин';

$unread = DB::run(
    'SELECT COUNT(*) as cnt FROM store_messages WHERE tenant_id = ? AND to_store_id = ? AND is_read = 0',
    [$tenant_id, $store_id]
)->fetch();
$unread_count = $unread ? (int)$unread['cnt'] : 0;

$quick_cmds = [
    ['icon' => '📦', 'label' => 'Склад',      'msg' => 'Покажи склада'],
    ['icon' => '💰', 'label' => 'Продажби',   'msg' => 'Колко продадох днес?'],
    ['icon' => '⚠️', 'label' => 'Ниска нал.', 'msg' => 'Кои артикули са под минимума?'],
];
if (in_array($role, ['owner','manager'])) {
    $quick_cmds[] = ['icon' => '🚚', 'label' => 'Доставка', 'msg' => 'Нова доставка'];
}
if ($role === 'owner') {
    $quick_cmds[] = ['icon' => '📊', 'label' => 'Печалба', 'msg' => 'Каква е печалбата ми днес?'];
}
$quick_cmds[] = ['icon' => '🎁', 'label' => 'Лоялна', 'msg' => 'Лоялна програма'];
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>RunMyStore.ai</title>
<link rel="stylesheet" href="./css/vendors/aos.css">
<link rel="stylesheet" href="./style.css">
<style>
:root { --nav-h: 56px; }
*, *::before, *::after { box-sizing: border-box; -webkit-tap-highlight-color: transparent; margin: 0; padding: 0; }
body { background: #0b0f1a; color: #e2e8f0; font-family: Inter, sans-serif; height: 100dvh; display: flex; flex-direction: column; overflow: hidden; padding-bottom: var(--nav-h); }

/* ── SVG BACKGROUNDS ── */
.bg-illus { position: fixed; inset: 0; pointer-events: none; z-index: 0; overflow: hidden; }
.bg-illus img { position: absolute; max-width: none; }
.bg-illus .ill1 { left: 50%; top: 0; transform: translateX(-25%); width: 846px; height: 594px; }
.bg-illus .ill2 { left: 50%; top: 400px; transform: translateX(-100%); width: 760px; height: 668px; opacity: .5; }
.bg-illus .ill3 { left: 50%; top: 440px; transform: translateX(-33%); width: 760px; height: 668px; }

/* ── HEADER ── */
.hdr { position: relative; z-index: 50; background: rgba(11,15,26,.92); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); flex-shrink: 0; box-shadow: 0 4px 20px rgba(99,102,241,0.05); }
.hdr::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 1px; background: linear-gradient(to right, transparent, rgba(99,102,241,.25), transparent); }
.hdr-top { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px 0; gap: 8px; }
.brand { font-size: 18px; font-weight: 800; flex: 1; background: linear-gradient(to right, #e5e7eb, #c7d2fe, #f9fafb, #a5b4fc, #e5e7eb); background-size: 200% auto; -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; animation: gShift 6s linear infinite; font-family: 'Nacelle', Inter, sans-serif; filter: drop-shadow(0 0 8px rgba(165,180,252,0.3)); }
.store-pill { font-size: 11px; font-weight: 600; color: rgba(165,180,252,.9); background: rgba(99,102,241,.08); border: 1px solid rgba(99,102,241,.15); border-radius: 20px; padding: 4px 10px; max-width: 110px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; box-shadow: 0 0 10px rgba(99,102,241,0.1); }
.hdr-btn { width: 34px; height: 34px; border-radius: 10px; background: rgba(99,102,241,.1); border: 1px solid rgba(99,102,241,.2); display: flex; align-items: center; justify-content: center; cursor: pointer; color: #a5b4fc; position: relative; flex-shrink: 0; transition: all 0.2s; box-shadow: 0 0 8px rgba(99,102,241,0.1); }
.hdr-btn:active { background: rgba(99,102,241,.25); box-shadow: 0 0 15px rgba(99,102,241,0.4); }
.hdr-badge { position: absolute; top: -4px; right: -4px; min-width: 16px; height: 16px; border-radius: 8px; background: #ef4444; font-size: 9px; font-weight: 800; color: #fff; display: flex; align-items: center; justify-content: center; padding: 0 3px; box-shadow: 0 0 8px rgba(239,68,68,0.5); }

/* ── TABS ── */
.tabs { display: flex; padding: 10px 16px 0; }
.tab { flex: 1; padding: 8px 4px; font-size: 13px; font-weight: 700; color: #4b5563; text-align: center; border-bottom: 2px solid transparent; cursor: pointer; text-decoration: none; display: block; transition: all .2s; }
.tab.active { color: #818cf8; border-bottom-color: #6366f1; text-shadow: 0 0 12px rgba(99,102,241,0.6); }
.tab-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 16px; height: 16px; border-radius: 8px; background: #ef4444; font-size: 9px; font-weight: 800; color: #fff; margin-left: 5px; padding: 0 3px; box-shadow: 0 0 8px rgba(239,68,68,0.5); }

/* ── BRIEFING ── */
.brief-area { padding: 12px 14px 4px; flex-shrink: 0; position: relative; z-index: 1; }
.brief-greeting { font-size: 15px; font-weight: 700; color: #f1f5f9; margin-bottom: 8px; text-shadow: 0 0 8px rgba(255,255,255,0.2); }
.brief-card { border-radius: 12px; padding: 10px 12px; margin-bottom: 6px; display: flex; align-items: flex-start; gap: 8px; position: relative; text-decoration: none; animation: fadeUp .35s ease both; transition: box-shadow 0.2s; }
.brief-card.red    { background: rgba(239,68,68,.07);  border: 1px solid rgba(239,68,68,.2); box-shadow: 0 0 12px rgba(239,68,68,0.15); }
.brief-card.orange { background: rgba(245,158,11,.07); border: 1px solid rgba(245,158,11,.2); box-shadow: 0 0 12px rgba(245,158,11,0.15); }
.brief-card.yellow { background: rgba(234,179,8,.07);  border: 1px solid rgba(234,179,8,.2); box-shadow: 0 0 12px rgba(234,179,8,0.15); }
.brief-card.green  { background: rgba(34,197,94,.07);  border: 1px solid rgba(34,197,94,.2); box-shadow: 0 0 12px rgba(34,197,94,0.15); }
.brief-text { flex: 1; font-size: 12px; color: #e2e8f0; line-height: 1.5; }
.brief-close { width: 20px; height: 20px; border-radius: 50%; background: rgba(255,255,255,.05); border: none; color: #4b5563; font-size: 11px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: background 0.2s; }
.brief-close:hover { background: rgba(255,255,255,.1); }
.brief-loading { font-size: 12px; color: #6b7280; padding: 8px 0; }

/* ── CHAT AREA ── */
.chat-area { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 10px 12px 6px; display: flex; flex-direction: column; -webkit-overflow-scrolling: touch; scrollbar-width: none; position: relative; z-index: 1; }
.chat-area::-webkit-scrollbar { display: none; }
.msg-group { margin-bottom: 10px; animation: fadeUp .3s ease both; }
.msg-meta { font-size: 10px; color: #4b5563; margin-bottom: 3px; display: flex; align-items: center; gap: 6px; }
.msg-meta.right { justify-content: flex-end; }
.ai-ava { width: 24px; height: 24px; border-radius: 50%; flex-shrink: 0; background: linear-gradient(135deg, #6366f1, #8b5cf6); display: flex; align-items: center; justify-content: center; box-shadow: 0 0 10px rgba(99,102,241,.6); }
.ai-ava-bars { display: flex; gap: 2px; align-items: center; height: 10px; }
.ai-ava-bar { width: 2px; border-radius: 1px; background: #fff; animation: barDance 1s ease-in-out infinite; box-shadow: 0 0 4px #fff; }
.ai-ava-bar:nth-child(1) { height: 3px; }
.ai-ava-bar:nth-child(2) { height: 7px; animation-delay: .15s; }
.ai-ava-bar:nth-child(3) { height: 10px; animation-delay: .3s; }
.ai-ava-bar:nth-child(4) { height: 5px; animation-delay: .45s; }
.msg { max-width: 85%; padding: 10px 13px; font-size: 13px; line-height: 1.55; word-break: break-word; position: relative; }
.msg.ai { background: rgba(15,15,40,.85); border: 1px solid rgba(99,102,241,.14); color: #e2e8f0; border-radius: 4px 16px 16px 16px; box-shadow: 0 4px 14px rgba(99,102,241,0.12); }
.msg.user { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; border-radius: 16px 16px 4px 16px; margin-left: auto; box-shadow: inset 0 1px 0 rgba(255,255,255,.16), 0 4px 14px rgba(139,92,246,0.3); }
.msg a.deeplink { display: inline-flex; align-items: center; gap: 4px; padding: 5px 11px; background: rgba(99,102,241,.12); border: 1px solid rgba(99,102,241,.25); border-radius: 10px; color: #a5b4fc; font-size: 12px; font-weight: 700; text-decoration: none; margin: 4px 2px 0; transition: all 0.2s; box-shadow: 0 0 8px rgba(99,102,241,0.1); }
.msg a.deeplink:active { background: rgba(99,102,241,.3); box-shadow: 0 0 12px rgba(99,102,241,0.3); }
.typing-wrap { display: none; padding: 10px 13px; background: rgba(15,15,40,.85); border: 1px solid rgba(99,102,241,.14); border-radius: 4px 16px 16px 16px; width: fit-content; margin-bottom: 10px; box-shadow: 0 4px 14px rgba(99,102,241,0.12); }
.typing-dots { display: flex; gap: 4px; align-items: center; }
.dot { width: 7px; height: 7px; border-radius: 50%; background: #6366f1; animation: bounce 1.2s infinite; box-shadow: 0 0 6px rgba(99,102,241,0.5); }
.dot:nth-child(2) { animation-delay: .2s; }
.dot:nth-child(3) { animation-delay: .4s; }
.welcome { text-align: center; padding: 30px 20px 10px; color: #4b5563; font-size: 13px; }
.welcome-title { font-size: 20px; font-weight: 800; margin-bottom: 6px; background: linear-gradient(to right, #e5e7eb, #c7d2fe, #f9fafb, #a5b4fc, #e5e7eb); background-size: 200% auto; -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; animation: gShift 6s linear infinite; filter: drop-shadow(0 0 8px rgba(165,180,252,0.3)); }

/* ── QUICK COMMANDS ── */
.quick-wrap { padding: 6px 12px; flex-shrink: 0; position: relative; z-index: 1; }
.quick-row { display: flex; gap: 6px; flex-wrap: wrap; }
.quick-btn { padding: 6px 12px; border-radius: 16px; font-size: 11px; font-weight: 700; border: 1px solid rgba(99,102,241,.18); color: #a5b4fc; background: rgba(99,102,241,.08); cursor: pointer; font-family: inherit; white-space: nowrap; display: flex; align-items: center; gap: 4px; transition: all 0.2s; box-shadow: 0 2px 8px rgba(99,102,241,0.1); }
.quick-btn:active { background: rgba(99,102,241,.22); border-color: rgba(99,102,241,.4); box-shadow: 0 0 12px rgba(99,102,241,0.4); }

/* ── INPUT AREA ── */
.input-area { background: rgba(11,15,26,.94); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); border-top: 1px solid rgba(99,102,241,.12); padding: 10px 12px 14px; flex-shrink: 0; position: relative; z-index: 1; box-shadow: 0 -4px 20px rgba(0,0,0,0.3); }
.input-row { display: flex; gap: 8px; align-items: center; }
.text-input { flex: 1; background: rgba(255,255,255,.05); border: 1px solid rgba(99,102,241,.18); border-radius: 22px; color: #e2e8f0; font-size: 14px; padding: 11px 16px; font-family: inherit; outline: none; resize: none; max-height: 80px; line-height: 1.4; transition: all .2s; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2); }
.text-input:focus { border-color: rgba(99,102,241,.45); box-shadow: 0 0 15px rgba(99,102,241,0.25), inset 0 2px 4px rgba(0,0,0,0.2); }
.text-input::placeholder { color: #374151; }

/* ── VOICE BUTTON ── */
.voice-wrap { position: relative; flex-shrink: 0; width: 52px; height: 52px; cursor: pointer; }
.voice-ring { position: absolute; border-radius: 50%; border: 1px solid rgba(99,102,241,.4); animation: waveOut 2s ease-out infinite; pointer-events: none; }
.voice-ring:nth-child(1) { inset: -4px; box-shadow: inset 0 0 8px rgba(99,102,241,0.2); }
.voice-ring:nth-child(2) { inset: -10px; animation-delay: .55s; }
.voice-ring:nth-child(3) { inset: -16px; animation-delay: 1.1s; }
.voice-inner { width: 52px; height: 52px; border-radius: 50%; background: linear-gradient(135deg, #6366f1, #8b5cf6); display: flex; align-items: center; justify-content: center; position: relative; z-index: 1; box-shadow: 0 0 20px rgba(99,102,241,.5), 0 0 40px rgba(99,102,241,.15); transition: box-shadow 0.3s; }
.voice-bars { display: flex; gap: 3px; align-items: center; height: 18px; }
.voice-bar { width: 3px; border-radius: 2px; background: #fff; animation: barDance 1s ease-in-out infinite; box-shadow: 0 0 4px rgba(255,255,255,0.8); }
.voice-bar:nth-child(1) { height: 6px; }
.voice-bar:nth-child(2) { height: 14px; animation-delay: .15s; }
.voice-bar:nth-child(3) { height: 18px; animation-delay: .3s; }
.voice-bar:nth-child(4) { height: 10px; animation-delay: .45s; }
.voice-bar:nth-child(5) { height: 6px; animation-delay: .6s; }
.voice-wrap.recording .voice-inner { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 0 25px rgba(239,68,68,.7), 0 0 50px rgba(239,68,68,.3); }
.voice-wrap.recording .voice-ring { border-color: rgba(239,68,68,.4); box-shadow: inset 0 0 8px rgba(239,68,68,0.2); }
.send-btn { width: 42px; height: 42px; border-radius: 50%; background: rgba(99,102,241,.12); border: 1px solid rgba(99,102,241,.22); color: #a5b4fc; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all 0.2s; }
.send-btn:not(:disabled) { box-shadow: 0 0 12px rgba(99,102,241,0.25); }
.send-btn:active:not(:disabled) { background: rgba(99,102,241,.28); transform: scale(.92); box-shadow: 0 0 18px rgba(99,102,241,0.5); }
.send-btn:disabled { opacity: .3; cursor: default; }

/* ── RECORDING OVERLAY ── */
.rec-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.87); z-index: 400; display: none; flex-direction: column; align-items: center; justify-content: center; backdrop-filter: blur(14px); }
.rec-overlay.show { display: flex; }
.rec-circle { width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, #ef4444, #dc2626); display: flex; align-items: center; justify-content: center; margin-bottom: 24px; animation: recPulse 1s ease-out infinite; box-shadow: 0 0 30px rgba(239,68,68,0.6); }
.rec-wave-bars { display: flex; gap: 4px; align-items: center; height: 28px; }
.rec-bar { width: 4px; border-radius: 2px; background: #fff; animation: barDance .7s ease-in-out infinite; box-shadow: 0 0 6px rgba(255,255,255,0.8); }
.rec-bar:nth-child(1) { height: 10px; }
.rec-bar:nth-child(2) { height: 20px; animation-delay: .1s; }
.rec-bar:nth-child(3) { height: 28px; animation-delay: .2s; }
.rec-bar:nth-child(4) { height: 16px; animation-delay: .3s; }
.rec-bar:nth-child(5) { height: 10px; animation-delay: .4s; }
.rec-title { font-size: 18px; font-weight: 800; color: #f1f5f9; margin-bottom: 6px; text-shadow: 0 0 10px rgba(255,255,255,0.3); }
.rec-sub { font-size: 13px; color: #6b7280; margin-bottom: 24px; }
.rec-stop { padding: 11px 28px; background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.3); border-radius: 24px; color: #ef4444; font-size: 14px; font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.2s; box-shadow: 0 0 15px rgba(239,68,68,0.15); }
.rec-stop:hover { box-shadow: 0 0 20px rgba(239,68,68,0.3); background: rgba(239,68,68,.2); }

/* ── ACTION CONFIRM ── */
.act-ovl { position: fixed; inset: 0; background: rgba(0,0,0,.6); backdrop-filter: blur(6px); z-index: 350; display: none; align-items: flex-end; justify-content: center; }
.act-ovl.show { display: flex; }
.act-box { background: #111827; border: 1px solid rgba(99,102,241,.2); border-radius: 20px 20px 0 0; width: 100%; max-width: 420px; padding: 24px 20px 32px; box-shadow: 0 -10px 40px rgba(99,102,241,0.15); }
.act-title { font-size: 15px; font-weight: 700; color: #f1f5f9; margin-bottom: 8px; text-align: center; text-shadow: 0 0 8px rgba(255,255,255,0.2); }
.act-desc { font-size: 13px; color: #9ca3af; margin-bottom: 20px; text-align: center; line-height: 1.5; }
.act-btns { display: flex; gap: 10px; }
.act-yes { flex: 1; padding: 13px; border: none; border-radius: 14px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; font-family: inherit; box-shadow: 0 4px 15px rgba(99,102,241,0.3); transition: all 0.2s; }
.act-yes:active { box-shadow: 0 2px 8px rgba(99,102,241,0.4); transform: scale(0.98); }
.act-no { flex: 1; padding: 13px; border: 1px solid rgba(99,102,241,.2); border-radius: 14px; background: transparent; color: #6b7280; font-size: 14px; font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.2s; }
.act-no:hover { background: rgba(255,255,255,0.05); }

/* ── BOTTOM NAV (унифициран С17) ── */
.bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; z-index: 100; background: rgba(11,15,26,0.95); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-top: 1px solid rgba(99,102,241,0.15); display: flex; height: 56px; box-shadow: 0 -5px 25px rgba(99,102,241,0.08); }
.bnav-tab { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; font-size: 0.6rem; color: rgba(165,180,252,0.65); text-decoration: none; transition: color 0.2s; }
.bnav-tab.active { color: #818cf8; text-shadow: 0 0 12px rgba(99,102,241,0.8); }
.bnav-tab .bnav-icon { font-size: 1.2rem; }

/* ── Indigo line separators (сини ленти) ── */
.indigo-sep { height: 1px; background: linear-gradient(to right, transparent, rgba(99,102,241,0.35), transparent); margin: 0 12px; box-shadow: 0 0 8px rgba(99,102,241,0.3); }

/* ── TOAST ── */
.toast { position: fixed; bottom: 72px; left: 50%; transform: translateX(-50%); background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; padding: 10px 20px; border-radius: 12px; font-size: 13px; font-weight: 700; z-index: 500; opacity: 0; transition: opacity .3s; pointer-events: none; white-space: nowrap; box-shadow: 0 4px 20px rgba(99,102,241,0.5); text-shadow: 0 1px 2px rgba(0,0,0,0.2); }
.toast.show { opacity: 1; }

/* ── ANIMATIONS ── */
@keyframes gShift { 0% { background-position: 0% center } 100% { background-position: 200% center } }
@keyframes fadeUp { from { opacity: 0; transform: translateY(8px) } to { opacity: 1; transform: translateY(0) } }
@keyframes bounce { 0%,60%,100% { transform: translateY(0) } 30% { transform: translateY(-6px) } }
@keyframes barDance { 0%,100% { transform: scaleY(1) } 50% { transform: scaleY(.25) } }
@keyframes waveOut { 0% { transform: scale(1); opacity: .6 } 100% { transform: scale(1.8); opacity: 0 } }
@keyframes recPulse { 0% { box-shadow: 0 0 0 0 rgba(239,68,68,.6) } 70% { box-shadow: 0 0 0 22px rgba(239,68,68,0) } 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0) } }
</style>
</head>
<body>

<div class="bg-illus" aria-hidden="true">
  <img class="ill1" src="./images/page-illustration.svg" alt="">
  <img class="ill2" src="./images/blurred-shape-gray.svg" alt="">
  <img class="ill3" src="./images/blurred-shape.svg" alt="">
</div>

<div class="hdr">
  <div class="hdr-top">
    <div class="brand">RunMyStore.ai</div>
    <div class="store-pill"><?= htmlspecialchars($store_name) ?></div>
    <div class="hdr-btn" onclick="doPulse()" title="Пулс">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
    </div>
    <div class="hdr-btn" onclick="fillAndSend('Покажи всички нотификации')">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
    </div>
  </div>
  <div class="tabs">
    <div class="tab active">✦ AI Асистент</div>
    <a class="tab" href="store-chat.php">Чат Обекти<?php if ($unread_count > 0): ?><span class="tab-badge"><?= $unread_count ?></span><?php endif; ?></a>
  </div>
  <div class="indigo-sep"></div>
</div>

<div class="brief-area" id="briefArea">
  <div class="brief-loading" id="briefLoading">Зареждам...</div>
</div>

<div class="chat-area" id="chatArea">
  <?php if (empty($messages)): ?>
  <div class="welcome">
    <div class="welcome-title">Здравей<?= $user_name ? ', ' . htmlspecialchars($user_name) : '' ?>!</div>
    Аз съм твоят AI асистент за <?= htmlspecialchars($store_name) ?>.<br>
    Натисни микрофона или пиши.
  </div>
  <?php else: ?>
  <?php foreach ($messages as $msg): ?>
  <div class="msg-group">
    <?php if ($msg['role'] === 'assistant'): ?>
      <div class="msg-meta"><div class="ai-ava"><div class="ai-ava-bars"><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div></div></div> AI</div>
      <div class="msg ai"><?= parseDeeplinks(nl2br(htmlspecialchars($msg['content']))) ?></div>
    <?php else: ?>
      <div class="msg-meta right"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
      <div style="display:flex;justify-content:flex-end"><div class="msg user"><?= nl2br(htmlspecialchars($msg['content'])) ?></div></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
  <div class="typing-wrap" id="typing"><div class="typing-dots"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div></div>
</div>

<div class="quick-wrap">
  <div class="quick-row">
    <?php foreach ($quick_cmds as $cmd): ?>
    <button class="quick-btn" onclick="fillAndSend(<?= htmlspecialchars(json_encode($cmd['msg']), ENT_QUOTES) ?>)"><?= $cmd['icon'] ?> <?= htmlspecialchars($cmd['label']) ?></button>
    <?php endforeach; ?>
  </div>
</div>

<div class="indigo-sep"></div>

<div class="input-area">
  <div class="input-row">
    <textarea class="text-input" id="chatInput" placeholder="Кажи или пиши..." rows="1" oninput="autoResize(this)" onkeydown="handleKey(event)"></textarea>
    <div class="voice-wrap" id="voiceWrap" onclick="toggleVoice()">
      <div class="voice-ring"></div><div class="voice-ring"></div><div class="voice-ring"></div>
      <div class="voice-inner"><div class="voice-bars"><div class="voice-bar"></div><div class="voice-bar"></div><div class="voice-bar"></div><div class="voice-bar"></div><div class="voice-bar"></div></div></div>
    </div>
    <button class="send-btn" id="btnSend" onclick="sendMessage()" disabled>
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>
  </div>
</div>

<div class="act-ovl" id="actOvl">
  <div class="act-box">
    <div class="act-title" id="actTitle">Потвърждение</div>
    <div class="act-desc" id="actDesc"></div>
    <div class="act-btns">
      <button class="act-yes" onclick="confirmAction()">Да</button>
      <button class="act-no" onclick="cancelAction()">Не</button>
    </div>
  </div>
</div>

<div class="rec-overlay" id="recOverlay">
  <div class="rec-circle"><div class="rec-wave-bars"><div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div><div class="rec-bar"></div></div></div>
  <div class="rec-title">Слушам...</div>
  <div class="rec-sub">Говори свободно на български</div>
  <button class="rec-stop" onclick="stopVoice()">Спри записа</button>
</div>

<nav class="bottom-nav">
    <a href="chat.php" class="bnav-tab active"><span class="bnav-icon">✦</span>AI</a>
    <a href="warehouse.php" class="bnav-tab"><span class="bnav-icon">📦</span>Склад</a>
    <a href="stats.php" class="bnav-tab"><span class="bnav-icon">📊</span>Справки</a>
    <a href="actions.php" class="bnav-tab"><span class="bnav-icon">⚡</span>Въвеждане</a>
</nav>

<div class="toast" id="toast"></div>

<?php
function parseDeeplinks($html) {
    $map = [
        '📦' => 'products.php?filter=low',
        '⚠️' => 'purchase-orders.php',
        '📊' => 'stats.php',
        '💰' => 'sale.php',
        '🔄' => 'transfers.php',
    ];
    return preg_replace_callback('/\[([^\]]+?)→\]/u', function($m) use ($map) {
        $text = trim($m[1]);
        $href = '#';
        foreach ($map as $emoji => $url) {
            if (mb_strpos($text, $emoji) !== false) { $href = $url; break; }
        }
        return '<a class="deeplink" href="' . $href . '">' . htmlspecialchars($text) . ' →</a>';
    }, $html);
}
?>

<script>
const chatArea  = document.getElementById('chatArea');
const chatInput = document.getElementById('chatInput');
const btnSend   = document.getElementById('btnSend');
const typing    = document.getElementById('typing');
const voiceWrap = document.getElementById('voiceWrap');
const recOverlay= document.getElementById('recOverlay');
let voiceRec = null, isRecording = false, pendingAction = null;

const dlMap = {'📦':'products.php?filter=low','⚠️':'purchase-orders.php','📊':'stats.php','💰':'sale.php','🔄':'transfers.php'};

function parseDeeplinksJS(text) {
  return text.replace(/\[([^\]]+?)→\]/gu, (m, inner) => {
    let href = '#';
    for (const [emoji, url] of Object.entries(dlMap)) {
      if (inner.includes(emoji)) { href = url; break; }
    }
    return `<a class="deeplink" href="${href}">${esc(inner.trim())} →</a>`;
  });
}

function scrollBottom() { chatArea.scrollTop = chatArea.scrollHeight; }
scrollBottom();

chatInput.addEventListener('input', function() { btnSend.disabled = !this.value.trim(); });
function autoResize(el) { el.style.height = ''; el.style.height = Math.min(el.scrollHeight, 80) + 'px'; }
function handleKey(e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); } }
function fillAndSend(text) { chatInput.value = text; btnSend.disabled = false; sendMessage(); }

async function sendMessage() {
  const text = chatInput.value.trim();
  if (!text) return;
  addUserMsg(text);
  chatInput.value = ''; chatInput.style.height = ''; btnSend.disabled = true;
  typing.style.display = 'block'; scrollBottom();
  try {
    const res  = await fetch('chat-send.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({message:text}) });
    const data = await res.json();
    typing.style.display = 'none';
    const reply = data.reply || data.error || 'Грешка';
    addAIMsg(reply);
    if (data.action) {
      pendingAction = data.action;
      document.getElementById('actDesc').textContent = data.action.details || JSON.stringify(data.action);
      document.getElementById('actOvl').classList.add('show');
    }
  } catch(e) {
    typing.style.display = 'none';
    addAIMsg('Грешка при свързване.');
  }
}

function addUserMsg(text) {
  const g = document.createElement('div'); g.className = 'msg-group';
  g.innerHTML = `<div class="msg-meta right">${new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'})}</div><div style="display:flex;justify-content:flex-end"><div class="msg user">${esc(text)}</div></div>`;
  chatArea.insertBefore(g, typing); scrollBottom();
}

function addAIMsg(text) {
  const g = document.createElement('div'); g.className = 'msg-group';
  const parsed = parseDeeplinksJS(esc(text).replace(/\n/g,'<br>'));
  g.innerHTML = `<div class="msg-meta"><div class="ai-ava"><div class="ai-ava-bars"><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div><div class="ai-ava-bar"></div></div></div> AI</div><div class="msg ai">${parsed}</div>`;
  chatArea.insertBefore(g, typing); scrollBottom();
}

function esc(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function confirmAction() {
  document.getElementById('actOvl').classList.remove('show');
  showToast('Ще бъде изпълнено');
  pendingAction = null;
}
function cancelAction() {
  document.getElementById('actOvl').classList.remove('show');
  pendingAction = null;
}

async function doPulse() {
  showToast('Проверявам...');
  try {
    const r = await fetch('ai-helper.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'pulse'}) });
    const d = await r.json();
    showToast(d.message || 'Готово');
  } catch(e) { showToast('Грешка'); }
}

async function loadBriefing() {
  const area = document.getElementById('briefArea');
  try {
    const r = await fetch('ai-helper.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'briefing'}) });
    const d = await r.json();
    let html = '';
    if (d.greeting) html += `<div class="brief-greeting">${esc(d.greeting)}</div>`;
    if (d.items && d.items.length) {
      d.items.forEach((item, i) => {
        const p = item.priority || 'green';
        const dl = item.deeplink ? ` onclick="location.href='${item.deeplink}'" style="cursor:pointer"` : '';
        html += `<div class="brief-card ${p}" id="bc${i}"${dl}>
          <div class="brief-text">${esc(item.text)}</div>
          <button class="brief-close" onclick="event.stopPropagation();closeBrief('bc${i}')">✕</button>
        </div>`;
      });
    }
    if (!html) html = `<div class="brief-greeting">Всичко е наред! 👍</div>`;
    area.innerHTML = html;
  } catch(e) {
    area.innerHTML = '';
  }
}

function closeBrief(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.transition = 'all .3s'; el.style.opacity = '0'; el.style.maxHeight = el.offsetHeight + 'px';
  setTimeout(() => { el.style.maxHeight = '0'; el.style.marginBottom = '0'; el.style.padding = '0'; }, 50);
  setTimeout(() => el.remove(), 350);
}

async function toggleVoice() {
  if (isRecording) { stopVoice(); return; }
  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) { showToast('Браузърът не поддържа гласово въвеждане'); return; }
  isRecording = true;
  voiceWrap.classList.add('recording');
  recOverlay.classList.add('show');
  voiceRec = new SR();
  voiceRec.lang = 'bg-BG';
  voiceRec.interimResults = false;
  voiceRec.maxAlternatives = 1;
  voiceRec.continuous = false;
  voiceRec.onresult = (e) => {
    const text = e.results[0][0].transcript;
    stopVoice();
    chatInput.value = text; btnSend.disabled = false;
    sendMessage();
  };
  voiceRec.onerror = (e) => {
    stopVoice();
    if (e.error === 'no-speech') showToast('Не чух — опитай пак');
    else if (e.error === 'not-allowed') showToast('Разреши микрофона в настройките');
    else showToast('Грешка: ' + e.error);
  };
  voiceRec.onend = () => { if (isRecording) stopVoice(); };
  try { voiceRec.start(); } catch(e) { stopVoice(); showToast('Грешка при стартиране'); }
}

function stopVoice() {
  isRecording = false;
  voiceWrap.classList.remove('recording');
  recOverlay.classList.remove('show');
  if (voiceRec) { try { voiceRec.stop(); } catch(e){} voiceRec = null; }
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

loadBriefing();
</script>
</body>
</html>
