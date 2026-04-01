<?php
// chat.php — AI First команден център
session_start();
require_once __DIR__ . '/config/database.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$tenant_id  = $_SESSION['tenant_id'];
$user_id    = $_SESSION['user_id'];
$store_id   = $_SESSION['store_id'];
$role       = $_SESSION['role'] ?? 'seller';

$messages = DB::run(
    'SELECT role, content, created_at FROM chat_messages
     WHERE tenant_id = ? ORDER BY created_at ASC LIMIT 50',
    [$tenant_id]
)->fetchAll();

$store = DB::run('SELECT name FROM stores WHERE id = ? LIMIT 1', [$store_id])->fetch();
$store_name = $store ? $store['name'] : 'Магазин';

$unread = DB::run(
    'SELECT COUNT(*) as cnt FROM store_messages WHERE tenant_id = ? AND to_store_id = ? AND is_read = 0',
    [$tenant_id, $store_id]
)->fetch();
$unread_count = $unread ? (int)$unread['cnt'] : 0;

// Проактивни нотификации — последните 3 непрочетени
$notifications = DB::run(
    'SELECT type, title, message FROM notifications
     WHERE tenant_id = ? AND is_read = 0 AND (expires_at IS NULL OR expires_at > NOW())
     ORDER BY created_at DESC LIMIT 3',
    [$tenant_id]
)->fetchAll();

// Бързи команди по роля
$quick_cmds = [
    ['icon' => '📦', 'label' => 'Склад', 'msg' => 'Покажи склада'],
    ['icon' => '💰', 'label' => 'Продажби', 'msg' => 'Колко продадох днес?'],
    ['icon' => '⚠️', 'label' => 'Ниска нал.', 'msg' => 'Кои артикули са под минимума?'],
];
if (in_array($role, ['owner', 'manager'])) {
    $quick_cmds[] = ['icon' => '🚚', 'label' => 'Доставка', 'msg' => 'Нова доставка'];
    $quick_cmds[] = ['icon' => '🔄', 'label' => 'Трансфер', 'msg' => 'Направи трансфер'];
}
if ($role === 'owner') {
    $quick_cmds[] = ['icon' => '📊', 'label' => 'Печалба', 'msg' => 'Каква е печалбата ми днес?'];
}
$quick_cmds[] = ['icon' => '🎁', 'label' => 'Лоялна', 'msg' => 'Лоялна програма'];

// Тип иконка по нотификация
function notif_icon($type) {
    $map = ['low_stock'=>'⚠️','out_stock'=>'🔴','delivery'=>'📦','transfer'=>'🔄','sale_spike'=>'🎉','debt'=>'💳'];
    return $map[$type] ?? '🔔';
}
function notif_class($type) {
    if (in_array($type, ['out_stock','debt'])) return 'danger';
    if (in_array($type, ['low_stock','transfer'])) return 'warning';
    return 'success';
}
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>RunMyStore.ai — AI Асистент</title>
<link rel="stylesheet" href="./css/vendors/aos.css">
<link rel="stylesheet" href="./style.css">
<style>
:root {
  --nav-h: 80px;
  --input-h: 72px;
  --header-h: 120px;
  --indigo-500: #6366f1;
  --indigo-400: #818cf8;
  --indigo-300: #a5b4fc;
  --indigo-200: #c7d2fe;
  --gray-950: #030712;
  --gray-900: #111827;
  --gray-800: #1f2937;
  --gray-700: #374151;
  --gray-600: #4b5563;
  --gray-500: #6b7280;
  --gray-400: #9ca3af;
  --danger: #ef4444;
  --warning: #f59e0b;
  --success: #22c55e;
}

*, *::before, *::after { 
  box-sizing: border-box; 
  -webkit-tap-highlight-color: transparent; 
  margin: 0; 
  padding: 0; 
}

body {
  background: var(--gray-950);
  color: #e2e8f0;
  font-family: 'Montserrat', sans-serif;
  height: 100dvh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
}

/* Ambient Glow Background */
body::before {
  content: '';
  position: fixed;
  top: -300px;
  left: 50%;
  transform: translateX(-50%);
  width: 900px;
  height: 600px;
  background: radial-gradient(ellipse, rgba(99,102,241,.12) 0%, rgba(139,92,246,.08) 30%, transparent 70%);
  pointer-events: none;
  z-index: 0;
  animation: pulse-glow 8s ease-in-out infinite;
}

@keyframes pulse-glow {
  0%, 100% { opacity: 0.6; transform: translateX(-50%) scale(1); }
  50% { opacity: 1; transform: translateX(-50%) scale(1.1); }
}

/* Header - Minimal Glass */
.hdr {
  position: relative;
  z-index: 50;
  background: linear-gradient(180deg, rgba(3,7,18,.98) 0%, rgba(3,7,18,.85) 100%);
  backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(99,102,241,.15);
  padding: 16px 20px 12px;
  flex-shrink: 0;
}

.hdr-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
  gap: 12px;
}

.brand-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
}

.brand-icon {
  width: 36px;
  height: 36px;
  border-radius: 12px;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  box-shadow: 0 4px 20px rgba(99,102,241,.4);
  animation: float 3s ease-in-out infinite;
}

@keyframes float {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-3px); }
}

.brand {
  font-size: 20px;
  font-weight: 800;
  background: linear-gradient(90deg, #f1f5f9 0%, #a5b4fc 50%, #f1f5f9 100%);
  background-size: 200% auto;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  animation: shimmer 4s linear infinite;
}

@keyframes shimmer {
  0% { background-position: 0% center; }
  100% { background-position: 200% center; }
}

.store-pill {
  font-size: 11px;
  font-weight: 700;
  color: rgba(165,180,252,.9);
  background: rgba(99,102,241,.12);
  border: 1px solid rgba(99,102,241,.25);
  border-radius: 20px;
  padding: 6px 14px;
  max-width: 140px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  box-shadow: 0 2px 10px rgba(99,102,241,.1);
}

.hdr-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: rgba(99,102,241,.1);
  border: 1px solid rgba(99,102,241,.2);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--indigo-300);
  transition: all 0.3s ease;
  position: relative;
  flex-shrink: 0;
}

.hdr-icon:hover, .hdr-icon:active {
  background: rgba(99,102,241,.2);
  border-color: rgba(99,102,241,.4);
  transform: scale(1.05);
}

.hdr-badge {
  position: absolute;
  top: -4px;
  right: -4px;
  min-width: 18px;
  height: 18px;
  border-radius: 9px;
  background: linear-gradient(135deg, #ef4444, #dc2626);
  font-size: 10px;
  font-weight: 800;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 5px;
  box-shadow: 0 2px 8px rgba(239,68,68,.4);
  animation: pulse-badge 2s infinite;
}

@keyframes pulse-badge {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.1); }
}

/* Tabs - Floating Pills */
.tabs { 
  display: flex; 
  gap: 8px; 
  padding: 0 4px;
}

.tab {
  flex: 1;
  padding: 10px 16px;
  font-size: 13px;
  font-weight: 700;
  color: var(--gray-500);
  text-align: center;
  border-radius: 12px;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background: rgba(15,15,40,.5);
  border: 1px solid transparent;
  position: relative;
  overflow: hidden;
}

.tab::before {
  content: '';
  position: absolute;
  inset: 0;
  background: linear-gradient(135deg, rgba(99,102,241,.2), rgba(139,92,246,.1));
  opacity: 0;
  transition: opacity 0.3s;
}

.tab.active { 
  color: #fff;
  background: linear-gradient(135deg, rgba(99,102,241,.3), rgba(139,92,246,.2));
  border-color: rgba(99,102,241,.4);
  box-shadow: 0 4px 20px rgba(99,102,241,.2), inset 0 1px 0 rgba(255,255,255,.1);
}

.tab.active::before {
  opacity: 1;
}

.tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  border-radius: 9px;
  background: linear-gradient(135deg, #ef4444, #dc2626);
  font-size: 10px;
  font-weight: 800;
  color: #fff;
  padding: 0 5px;
  box-shadow: 0 2px 8px rgba(239,68,68,.3);
}

/* Chat Area - Scrollable */
.chat-area {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 16px 16px 100px;
  display: flex;
  flex-direction: column;
  gap: 0;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
  position: relative;
  z-index: 1;
}

.chat-area::-webkit-scrollbar { display: none; }

/* Proactive Cards - Priority Display */
.pro-wrap { 
  margin-bottom: 20px; 
  animation: slideDown 0.5s ease;
}

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-20px); }
  to { opacity: 1; transform: translateY(0); }
}

.pro-label {
  font-size: 11px;
  font-weight: 800;
  color: var(--indigo-400);
  text-transform: uppercase;
  letter-spacing: 1.5px;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.pro-label::before {
  content: '✦';
  color: var(--indigo-500);
  font-size: 14px;
  animation: spin 4s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.pro-card {
  border-radius: 16px;
  padding: 16px;
  margin-bottom: 10px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  animation: slideIn 0.4s ease both;
  position: relative;
  overflow: hidden;
  backdrop-filter: blur(10px);
  border: 1px solid;
  transition: transform 0.2s, box-shadow 0.2s;
}

.pro-card:active {
  transform: scale(0.98);
}

.pro-card.danger { 
  background: linear-gradient(135deg, rgba(239,68,68,.12), rgba(239,68,68,.05));
  border-color: rgba(239,68,68,.3);
  box-shadow: 0 4px 20px rgba(239,68,68,.1);
}

.pro-card.warning { 
  background: linear-gradient(135deg, rgba(245,158,11,.12), rgba(245,158,11,.05));
  border-color: rgba(245,158,11,.3);
  box-shadow: 0 4px 20px rgba(245,158,11,.1);
}

.pro-card.success { 
  background: linear-gradient(135deg, rgba(34,197,94,.12), rgba(34,197,94,.05));
  border-color: rgba(34,197,94,.3);
  box-shadow: 0 4px 20px rgba(34,197,94,.1);
}

.pro-icon { 
  font-size: 24px; 
  flex-shrink: 0;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,.2));
}

.pro-body { flex: 1; min-width: 0; }

.pro-title { 
  font-size: 14px; 
  font-weight: 800; 
  color: #f1f5f9; 
  margin-bottom: 4px;
  letter-spacing: -0.2px;
}

.pro-sub { 
  font-size: 12px; 
  color: var(--gray-400); 
  line-height: 1.5; 
  margin-bottom: 12px;
}

.pro-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 700;
  background: linear-gradient(135deg, rgba(99,102,241,.2), rgba(139,92,246,.15));
  border: 1px solid rgba(99,102,241,.3);
  color: var(--indigo-300);
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s;
  box-shadow: 0 2px 10px rgba(99,102,241,.1);
}

.pro-action:hover, .pro-action:active {
  background: linear-gradient(135deg, rgba(99,102,241,.3), rgba(139,92,246,.25));
  transform: translateY(-1px);
  box-shadow: 0 4px 15px rgba(99,102,241,.2);
}

.pro-close {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: rgba(255,255,255,.08);
  border: none;
  color: var(--gray-500);
  font-size: 12px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.pro-close:hover, .pro-close:active {
  background: rgba(255,255,255,.15);
  color: var(--gray-300);
  transform: rotate(90deg);
}

/* Messages - Bubble Style */
.msg-group { 
  margin-bottom: 16px; 
  animation: fadeUp 0.4s ease both;
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}

.msg-meta {
  font-size: 11px;
  color: var(--gray-500);
  margin-bottom: 6px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.msg-meta.right { justify-content: flex-end; }

.ai-ava {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  flex-shrink: 0;
  box-shadow: 0 2px 10px rgba(99,102,241,.3);
  animation: pulse-ava 3s infinite;
}

@keyframes pulse-ava {
  0%, 100% { box-shadow: 0 2px 10px rgba(99,102,241,.3); }
  50% { box-shadow: 0 4px 20px rgba(99,102,241,.5); }
}

.msg {
  max-width: 85%;
  padding: 14px 18px;
  font-size: 14px;
  line-height: 1.6;
  word-break: break-word;
  position: relative;
}

.msg.ai {
  background: linear-gradient(135deg, rgba(15,15,40,.95), rgba(20,20,50,.9));
  border: 1px solid rgba(99,102,241,.2);
  color: #e2e8f0;
  border-radius: 4px 20px 20px 20px;
  align-self: flex-start;
  box-shadow: 0 4px 20px rgba(0,0,0,.2), inset 0 1px 0 rgba(255,255,255,.05);
}

.msg.user {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  color: #fff;
  border-radius: 20px 20px 4px 20px;
  margin-left: auto;
  box-shadow: 0 4px 20px rgba(99,102,241,.4), inset 0 1px 0 rgba(255,255,255,.2);
}

.action-chips {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
  margin-top: 12px;
}

.action-chip {
  padding: 8px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  background: rgba(99,102,241,.15);
  border: 1px solid rgba(99,102,241,.3);
  color: var(--indigo-300);
  cursor: pointer;
  transition: all 0.2s;
  white-space: nowrap;
  font-family: inherit;
  backdrop-filter: blur(4px);
}

.action-chip:hover, .action-chip:active {
  background: rgba(99,102,241,.3);
  border-color: rgba(99,102,241,.5);
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(99,102,241,.2);
}

/* Typing Indicator */
.typing-wrap {
  display: none;
  padding: 16px 20px;
  background: linear-gradient(135deg, rgba(15,15,40,.95), rgba(20,20,50,.9));
  border: 1px solid rgba(99,102,241,.2);
  border-radius: 4px 20px 20px 20px;
  width: fit-content;
  margin-bottom: 16px;
  box-shadow: 0 4px 20px rgba(0,0,0,.2);
}

.typing-dots { display: flex; gap: 6px; align-items: center; }

.dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  animation: bounce 1.4s infinite ease-in-out;
}

.dot:nth-child(2) { animation-delay: 0.2s; }
.dot:nth-child(3) { animation-delay: 0.4s; }

@keyframes bounce {
  0%, 60%, 100% { transform: translateY(0); }
  30% { transform: translateY(-8px); }
}

/* Welcome Screen */
.welcome {
  text-align: center;
  padding: 40px 24px 20px;
  color: var(--gray-500);
  font-size: 15px;
  animation: fadeIn 0.8s ease;
}

@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

.welcome-icon {
  width: 80px;
  height: 80px;
  margin: 0 auto 24px;
  border-radius: 24px;
  background: linear-gradient(135deg, rgba(99,102,241,.2), rgba(139,92,246,.1));
  border: 1px solid rgba(99,102,241,.3);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 36px;
  box-shadow: 0 8px 40px rgba(99,102,241,.2);
  animation: float 4s ease-in-out infinite;
}

.welcome-title {
  font-size: 24px;
  font-weight: 800;
  margin-bottom: 8px;
  background: linear-gradient(90deg, #f1f5f9 0%, #a5b4fc 50%, #f1f5f9 100%);
  background-size: 200% auto;
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  animation: shimmer 4s linear infinite;
}

.welcome-sub {
  font-size: 14px;
  color: var(--gray-400);
  line-height: 1.6;
  max-width: 280px;
  margin: 0 auto;
}

/* Quick Commands - Horizontal Scroll */
.quick-wrap {
  position: fixed;
  bottom: calc(var(--nav-h) + var(--input-h) + 8px);
  left: 0;
  right: 0;
  padding: 0 16px;
  z-index: 40;
  background: linear-gradient(180deg, transparent 0%, rgba(3,7,18,.9) 20%, rgba(3,7,18,.98) 100%);
  padding-top: 30px;
}

.quick-row {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  scrollbar-width: none;
  padding-bottom: 8px;
  -webkit-overflow-scrolling: touch;
}

.quick-row::-webkit-scrollbar { display: none; }

.quick-btn {
  flex-shrink: 0;
  padding: 10px 18px;
  border-radius: 24px;
  font-size: 13px;
  font-weight: 700;
  border: 1px solid rgba(99,102,241,.25);
  color: var(--gray-400);
  background: linear-gradient(135deg, rgba(15,15,40,.9), rgba(20,20,50,.8));
  cursor: pointer;
  font-family: inherit;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  white-space: nowrap;
  display: flex;
  align-items: center;
  gap: 6px;
  backdrop-filter: blur(10px);
  box-shadow: 0 2px 10px rgba(0,0,0,.2);
}

.quick-btn:hover, .quick-btn:active {
  background: linear-gradient(135deg, rgba(99,102,241,.25), rgba(139,92,246,.15));
  color: var(--indigo-300);
  border-color: rgba(99,102,241,.5);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(99,102,241,.25);
}

/* Input Area - Voice First Design */
.input-area {
  position: fixed;
  bottom: var(--nav-h);
  left: 0;
  right: 0;
  background: linear-gradient(180deg, rgba(3,7,18,.95) 0%, rgba(3,7,18,1) 100%);
  border-top: 1px solid rgba(99,102,241,.15);
  padding: 12px 16px 16px;
  z-index: 45;
  backdrop-filter: blur(20px);
}

.input-row { 
  display: flex; 
  gap: 10px; 
  align-items: center;
}

.text-input {
  flex: 1;
  background: linear-gradient(135deg, rgba(15,15,40,.9), rgba(20,20,50,.8));
  border: 1px solid rgba(99,102,241,.2);
  border-radius: 28px;
  color: #e2e8f0;
  font-size: 15px;
  padding: 14px 20px;
  font-family: inherit;
  outline: none;
  resize: none;
  max-height: 100px;
  line-height: 1.5;
  transition: all 0.3s;
  box-shadow: inset 0 2px 4px rgba(0,0,0,.2);
}

.text-input:focus { 
  border-color: rgba(99,102,241,.5);
  box-shadow: 0 0 0 3px rgba(99,102,241,.1), inset 0 2px 4px rgba(0,0,0,.2);
}

.text-input::placeholder { color: var(--gray-600); }

.voice-btn {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
  border: none;
  color: #fff;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 20px rgba(99,102,241,.4), 0 0 0 4px rgba(99,102,241,.1);
  flex-shrink: 0;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  font-size: 22px;
  position: relative;
  overflow: hidden;
}

.voice-btn::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(circle, rgba(255,255,255,.3) 0%, transparent 70%);
  opacity: 0;
  transition: opacity 0.3s;
}

.voice-btn:hover, .voice-btn:active {
  transform: scale(1.05);
  box-shadow: 0 6px 30px rgba(99,102,241,.5), 0 0 0 6px rgba(99,102,241,.15);
}

.voice-btn.recording {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  box-shadow: 0 4px 30px rgba(239,68,68,.5), 0 0 0 4px rgba(239,68,68,.2);
  animation: pulse-rec 1.5s infinite;
}

.voice-btn.recording::before {
  opacity: 1;
}

@keyframes pulse-rec {
  0%, 100% { box-shadow: 0 4px 30px rgba(239,68,68,.5), 0 0 0 4px rgba(239,68,68,.2); }
  50% { box-shadow: 0 6px 40px rgba(239,68,68,.7), 0 0 0 8px rgba(239,68,68,0); }
}

.send-btn {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: linear-gradient(135deg, rgba(99,102,241,.2), rgba(139,92,246,.15));
  border: 1px solid rgba(99,102,241,.3);
  color: var(--indigo-300);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.3s;
  box-shadow: 0 2px 10px rgba(99,102,241,.1);
}

.send-btn:hover, .send-btn:active {
  background: linear-gradient(135deg, rgba(99,102,241,.3), rgba(139,92,246,.25));
  transform: scale(1.05);
  box-shadow: 0 4px 15px rgba(99,102,241,.2);
}

.send-btn:disabled { 
  opacity: 0.4; 
  cursor: default;
  transform: none;
  box-shadow: none;
}

/* Recording Overlay - Full Screen */
.rec-overlay {
  position: fixed;
  inset: 0;
  background: rgba(3,7,18,.95);
  z-index: 500;
  display: none;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(20px);
}

.rec-overlay.show { display: flex; animation: fadeIn 0.3s ease; }

.rec-visualizer {
  position: relative;
  width: 200px;
  height: 200px;
  margin-bottom: 40px;
}

.rec-circle {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: linear-gradient(135deg, #ef4444, #dc2626);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 64px;
  animation: pulse-ring 2s infinite;
  box-shadow: 0 0 60px rgba(239,68,68,.5);
}

.rec-ring {
  position: absolute;
  inset: -20px;
  border-radius: 50%;
  border: 2px solid rgba(239,68,68,.3);
  animation: ripple 2s infinite;
}

.rec-ring:nth-child(2) {
  inset: -40px;
  animation-delay: 0.5s;
}

.rec-ring:nth-child(3) {
  inset: -60px;
  animation-delay: 1s;
}

@keyframes pulse-ring {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

@keyframes ripple {
  0% { transform: scale(0.8); opacity: 1; }
  100% { transform: scale(1.3); opacity: 0; }
}

.rec-title { 
  font-size: 28px; 
  font-weight: 800; 
  color: #f1f5f9; 
  margin-bottom: 8px;
  text-shadow: 0 2px 10px rgba(0,0,0,.3);
}

.rec-sub { 
  font-size: 16px; 
  color: var(--gray-500); 
  margin-bottom: 48px;
  text-align: center;
  max-width: 280px;
  line-height: 1.5;
}

.rec-stop {
  padding: 16px 40px;
  background: linear-gradient(135deg, rgba(239,68,68,.15), rgba(239,68,68,.05));
  border: 1px solid rgba(239,68,68,.4);
  border-radius: 28px;
  color: #ef4444;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  font-family: inherit;
  transition: all 0.3s;
  backdrop-filter: blur(10px);
}

.rec-stop:hover, .rec-stop:active {
  background: linear-gradient(135deg, rgba(239,68,68,.25), rgba(239,68,68,.1));
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(239,68,68,.3);
}

/* Bottom Navigation - Fixed */
.bnav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: var(--nav-h);
  background: linear-gradient(180deg, rgba(3,7,18,.98) 0%, rgba(3,7,18,1) 100%);
  backdrop-filter: blur(20px);
  border-top: 1px solid rgba(99,102,241,.12);
  display: flex;
  align-items: center;
  z-index: 100;
  padding: 0 8px;
  padding-bottom: env(safe-area-inset-bottom, 0);
}

.ni {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  text-decoration: none;
  border: none;
  background: transparent;
  cursor: pointer;
  padding: 8px;
  border-radius: 16px;
  transition: all 0.3s;
  position: relative;
}

.ni::before {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: 16px;
  background: linear-gradient(135deg, rgba(99,102,241,.2), rgba(139,92,246,.1));
  opacity: 0;
  transition: opacity 0.3s;
}

.ni svg { 
  width: 24px; 
  height: 24px; 
  color: var(--gray-600);
  transition: all 0.3s;
}

.ni span { 
  font-size: 11px; 
  font-weight: 700; 
  color: var(--gray-600);
  transition: all 0.3s;
}

.ni:hover::before,
.ni.active::before {
  opacity: 1;
}

.ni.active svg, 
.ni.active span { 
  color: var(--indigo-400);
}

.ni.active svg {
  filter: drop-shadow(0 0 8px rgba(99,102,241,.5));
  transform: translateY(-2px);
}

/* Store Chat Tab - Hidden by default */
.store-area {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: none;
  scrollbar-width: none;
}

.store-area.active { display: block; }
.store-area::-webkit-scrollbar { display: none; }

.store-card {
  background: linear-gradient(135deg, rgba(15,15,40,.9), rgba(20,20,50,.8));
  border: 1px solid rgba(99,102,241,.15);
  border-radius: 16px;
  padding: 16px;
  margin-bottom: 12px;
  display: flex;
  gap: 14px;
  align-items: flex-start;
  cursor: pointer;
  transition: all 0.3s;
  backdrop-filter: blur(10px);
}

.store-card:hover, .store-card:active {
  border-color: rgba(99,102,241,.35);
  transform: translateY(-2px);
  box-shadow: 0 8px 30px rgba(99,102,241,.15);
}

.store-ava {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 800;
  color: #fff;
  flex-shrink: 0;
  box-shadow: 0 4px 15px rgba(99,102,241,.3);
}

.store-info { flex: 1; min-width: 0; }

.store-name { 
  font-size: 15px; 
  font-weight: 700; 
  color: #f1f5f9; 
  margin-bottom: 4px;
}

.store-preview { 
  font-size: 13px; 
  color: var(--gray-500); 
  white-space: nowrap; 
  overflow: hidden; 
  text-overflow: ellipsis; 
}

.store-time { 
  font-size: 11px; 
  color: var(--gray-600); 
  flex-shrink: 0;
  font-weight: 600;
}

.unread-dot { 
  width: 10px; 
  height: 10px; 
  border-radius: 50%; 
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  margin-top: 6px;
  box-shadow: 0 0 10px rgba(99,102,241,.5);
  animation: pulse-dot 2s infinite;
}

@keyframes pulse-dot {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.7; transform: scale(1.2); }
}

/* Animations */
@keyframes slideIn { 
  from { opacity: 0; transform: translateX(-12px) scale(0.95); } 
  to { opacity: 1; transform: translateX(0) scale(1); } 
}

/* Scrollbar styling for webkit */
::-webkit-scrollbar {
  width: 6px;
}

::-webkit-scrollbar-track {
  background: transparent;
}

::-webkit-scrollbar-thumb {
  background: rgba(99,102,241,.3);
  border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
  background: rgba(99,102,241,.5);
}

/* Safe area for notched phones */
@supports (padding-top: env(safe-area-inset-top)) {
  .hdr {
    padding-top: calc(16px + env(safe-area-inset-top));
  }
}
</style>
</head>
<body>

<!-- HEADER -->
<div class="hdr">
  <div class="hdr-top">
    <div class="brand-wrap">
      <div class="brand-icon">✦</div>
      <div class="brand">RunMyStore.ai</div>
    </div>
    <div class="store-pill"><?= htmlspecialchars($store_name) ?></div>
    <div class="hdr-icon" onclick="openNotifications()">
      <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
      </svg>
      <?php if (!empty($notifications)): ?>
      <div class="hdr-badge"><?= count($notifications) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="tabs">
    <div class="tab active" id="tab-ai" onclick="switchTab('ai')">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
      </svg>
      AI Асистент
    </div>
    <a class="tab" id="tab-store" href="store-chat.php">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
      </svg>
      Чат Обекти
      <?php if ($unread_count > 0): ?>
        <span class="tab-badge"><?= $unread_count ?></span>
      <?php endif; ?>
    </a>
  </div>
</div>

<!-- AI CHAT AREA -->
<div class="chat-area" id="chatArea">

  <?php if (!empty($notifications)): ?>
  <!-- PROACTIVE CARDS -->
  <div class="pro-wrap">
    <div class="pro-label">Важно днес</div>
    <?php foreach ($notifications as $i => $n): ?>
    <div class="pro-card <?= notif_class($n['type']) ?>" id="pc<?= $i ?>">
      <div class="pro-icon"><?= notif_icon($n['type']) ?></div>
      <div class="pro-body">
        <div class="pro-title"><?= htmlspecialchars($n['title']) ?></div>
        <div class="pro-sub"><?= htmlspecialchars($n['message']) ?></div>
        <button class="pro-action" onclick="fillAndSend(<?= htmlspecialchars(json_encode($n['title']), ENT_QUOTES) ?>)">
          Виж детайли
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
          </svg>
        </button>
      </div>
      <button class="pro-close" onclick="closeCard('pc<?= $i ?>', <?= $i ?>)">✕</button>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (empty($messages)): ?>
  <!-- WELCOME -->
  <div class="welcome">
    <div class="welcome-icon">🎙️</div>
    <div class="welcome-title">Здравей, аз съм твоят AI!</div>
    <div class="welcome-sub">
      Кажи ми какво да направя — продажба, проверка на наличности, или нов артикул. Говоря български!
    </div>
  </div>
  <?php else: ?>
  <!-- HISTORY -->
  <?php foreach ($messages as $msg): ?>
  <div class="msg-group">
    <?php if ($msg['role'] === 'assistant'): ?>
      <div class="msg-meta">
        <div class="ai-ava">✦</div>
        <span>AI Асистент</span>
      </div>
      <div class="msg ai"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
    <?php else: ?>
      <div class="msg-meta right"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
      <div style="display:flex;justify-content:flex-end">
        <div class="msg user"><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
      </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- TYPING -->
  <div class="typing-wrap" id="typing">
    <div class="typing-dots">
      <div class="dot"></div>
      <div class="dot"></div>
      <div class="dot"></div>
    </div>
  </div>

</div>

<!-- QUICK COMMANDS -->
<div class="quick-wrap" id="quickWrap">
  <div class="quick-row">
    <?php foreach ($quick_cmds as $cmd): ?>
    <button class="quick-btn" onclick="fillAndSend(<?= htmlspecialchars(json_encode($cmd['msg']), ENT_QUOTES) ?>)">
      <span style="font-size:16px"><?= $cmd['icon'] ?></span>
      <?= htmlspecialchars($cmd['label']) ?>
    </button>
    <?php endforeach; ?>
  </div>
</div>

<!-- INPUT -->
<div class="input-area">
  <div class="input-row">
    <textarea class="text-input" id="chatInput"
      placeholder="Напиши или натисни микрофона..." rows="1"
      oninput="autoResize(this)"
      onkeydown="handleKey(event)"></textarea>
    <button class="voice-btn" id="voiceBtn" onclick="toggleVoice()">
      <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
      </svg>
    </button>
    <button class="send-btn" id="btnSend" onclick="sendMessage()" disabled>
      <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7"/>
      </svg>
    </button>
  </div>
</div>

<!-- BOTTOM NAV -->
<nav class="bnav">
  <a href="chat.php" class="ni active">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
    </svg>
    <span>Чат</span>
  </a>
  <a href="warehouse.php" class="ni">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
    </svg>
    <span>Склад</span>
  </a>
  <a href="stats.php" class="ni">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
    </svg>
    <span>Статистики</span>
  </a>
  <a href="actions.php" class="ni">
    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
    </svg>
    <span>Въвеждане</span>
  </a>
</nav>

<!-- RECORDING OVERLAY -->
<div class="rec-overlay" id="recOverlay">
  <div class="rec-visualizer">
    <div class="rec-ring"></div>
    <div class="rec-ring"></div>
    <div class="rec-ring"></div>
    <div class="rec-circle">
      <svg width="64" height="64" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
      </svg>
    </div>
  </div>
  <div class="rec-title">Слушам те...</div>
  <div class="rec-sub">Говори свободно на български. Натисни бутона долу, за да спреш.</div>
  <button class="rec-stop" onclick="stopVoice()">
    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="margin-right:8px;vertical-align:middle">
      <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 10a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>
    </svg>
    Спри записа
  </button>
</div>

<script>
const chatArea  = document.getElementById('chatArea');
const chatInput = document.getElementById('chatInput');
const btnSend   = document.getElementById('btnSend');
const typing    = document.getElementById('typing');
const voiceBtn  = document.getElementById('voiceBtn');
const recOverlay= document.getElementById('recOverlay');

let voiceRec = null;
let isRecording = false;

// ── SCROLL ──
function scrollBottom() { 
  chatArea.scrollTo({ top: chatArea.scrollHeight, behavior: 'smooth' }); 
}
scrollBottom();

// ── INPUT ──
chatInput.addEventListener('input', function() {
  btnSend.disabled = !this.value.trim();
});
function autoResize(el) {
  el.style.height = '';
  el.style.height = Math.min(el.scrollHeight, 100) + 'px';
}
function handleKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) { 
    e.preventDefault(); 
    sendMessage(); 
  }
}

// ── FILL & SEND ──
function fillAndSend(text) {
  chatInput.value = text;
  btnSend.disabled = false;
  autoResize(chatInput);
  sendMessage();
}

// ── SEND ──
async function sendMessage() {
  const text = chatInput.value.trim();
  if (!text) return;

  addUserMsg(text);
  chatInput.value = '';
  chatInput.style.height = '';
  btnSend.disabled = true;

  typing.style.display = 'block';
  scrollBottom();

  try {
    const res  = await fetch('chat-send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: text })
    });
    const data = await res.json();
    typing.style.display = 'none';
    addAIMsg(data.reply || data.error || 'Грешка');
  } catch(e) {
    typing.style.display = 'none';
    addAIMsg('Грешка при свързване с AI.');
  }
}

function addUserMsg(text) {
  const g = document.createElement('div');
  g.className = 'msg-group';
  g.innerHTML = `
    <div class="msg-meta right">${new Date().toLocaleTimeString('bg-BG',{hour:'2-digit',minute:'2-digit'})}</div>
    <div style="display:flex;justify-content:flex-end">
      <div class="msg user">${escHtml(text)}</div>
    </div>
  `;
  chatArea.insertBefore(g, typing);
  scrollBottom();
}

function addAIMsg(text) {
  const formatted = escHtml(text).replace(/\n/g,'<br>');
  const g = document.createElement('div');
  g.className = 'msg-group';
  g.innerHTML = `
    <div class="msg-meta">
      <div class="ai-ava">✦</div>
      <span>AI Асистент</span>
    </div>
    <div class="msg ai">${formatted}</div>
  `;
  chatArea.insertBefore(g, typing);
  scrollBottom();
}

function escHtml(s) {
  return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── VOICE ──
function toggleVoice() {
  if (isRecording) { stopVoice(); return; }
  if (!('webkitSpeechRecognition' in window || 'SpeechRecognition' in window)) {
    showToast('Гласовото въвеждане не се поддържа от браузъра');
    return;
  }
  isRecording = true;
  voiceBtn.classList.add('recording');
  recOverlay.classList.add('show');

  const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  voiceRec = new SR();
  voiceRec.lang = 'bg-BG';
  voiceRec.interimResults = false;
  voiceRec.maxAlternatives = 1;
  voiceRec.continuous = false;

  voiceRec.onresult = e => {
    const text = e.results[0][0].transcript;
    stopVoice();
    chatInput.value = text;
    btnSend.disabled = false;
    autoResize(chatInput);
    setTimeout(() => sendMessage(), 300);
  };
  
  voiceRec.onerror = (e) => {
    console.error('Voice error:', e.error);
    stopVoice();
    if (e.error === 'not-allowed') {
      showToast('Моля, разреши достъп до микрофона');
    } else if (e.error === 'no-speech') {
      showToast('Не чух нищо. Опитай пак.');
    } else {
      showToast('Грешка при разпознаване');
    }
  };
  
  voiceRec.onend = () => { 
    if (isRecording) stopVoice(); 
  };
  
  try {
    voiceRec.start();
  } catch(e) {
    stopVoice();
    showToast('Не може да се стартира микрофона');
  }
}

function stopVoice() {
  isRecording = false;
  voiceBtn.classList.remove('recording');
  recOverlay.classList.remove('show');
  if (voiceRec) { 
    try {
      voiceRec.stop(); 
    } catch(e) {}
    voiceRec = null; 
  }
}

// ── PROACTIVE CARDS ──
function closeCard(id, idx) {
  const el = document.getElementById(id);
  if (!el) return;
  el.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
  el.style.opacity = '0';
  el.style.transform = 'translateX(-20px) scale(0.95)';
  el.style.maxHeight = el.offsetHeight + 'px';
  setTimeout(() => { 
    el.style.maxHeight = '0'; 
    el.style.marginBottom = '0'; 
    el.style.padding = '0'; 
  }, 100);
  setTimeout(() => el.remove(), 500);
  
  fetch('chat-send.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'mark_notif_read', idx: idx })
  }).catch(()=>{});
}

// ── NOTIFICATIONS ──
function openNotifications() {
  fillAndSend('Покажи всички нотификации');
}

// ── TOAST ──
function showToast(msg) {
  let t = document.getElementById('__toast');
  if (!t) {
    t = document.createElement('div');
    t.id = '__toast';
    t.style.cssText = 'position:fixed;top:100px;left:50%;transform:translateX(-50%) translateY(-20px);background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:14px 28px;border-radius:16px;font-size:14px;font-weight:700;z-index:600;opacity:0;transition:all 0.4s cubic-bezier(0.4, 0, 0.2, 1);pointer-events:none;white-space:nowrap;font-family:Montserrat,sans-serif;box-shadow:0 8px 30px rgba(99,102,241,.4)';
    document.body.appendChild(t);
  }
  t.textContent = msg;
  requestAnimationFrame(() => {
    t.style.opacity = '1';
    t.style.transform = 'translateX(-50%) translateY(0)';
  });
  setTimeout(() => {
    t.style.opacity = '0';
    t.style.transform = 'translateX(-50%) translateY(-20px)';
  }, 3000);
}

// ── TAB SWITCHING ──
function switchTab(tab) {
  if (tab === 'store') {
    window.location.href = 'store-chat.php';
  }
}

// ── INIT ──
document.addEventListener('DOMContentLoaded', () => {
  // Focus input on load for desktop
  if (window.innerWidth > 768) {
    chatInput.focus();
  }
});
</script>
</body>
</html>
