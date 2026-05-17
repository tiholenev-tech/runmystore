<?php
/**
 * wizard-v6.php — Добави артикул v6 (ФАЗА 1 skeleton)
 * S148 CC — 17.05.2026
 *
 * ФАЗА 1: 4 акордеона + sacred glass bi-chromatic CSS (1:1 от mockup).
 * БЕЗ JS функционалност. Празни секции с TODO маркери за следващи фази.
 *
 * Sacred references (read-only, не пипай):
 *   products.php             — wizard logic (line 8321 renderWizPagePart2)
 *   services/voice-tier2.php — Whisper STT
 *   services/price-ai.php    — BG price parser
 *   ai-color-detect.php      — Gemini color detection
 *   js/capacitor-printer.js  — DTM-5811 printer
 */

session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

require_once 'config/database.php';
require_once 'config/config.php';
require_once 'config/helpers.php';

$user_id    = $_SESSION['user_id'];
$tenant_id  = $_SESSION['tenant_id'];
$store_id   = $_SESSION['store_id'] ?? null;
$user_role  = $_SESSION['role'] ?? 'seller';
$csrf_token = csrfToken();
?>
<!DOCTYPE html>
<html lang="bg">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
  <title>Добави артикул · RunMyStore.AI</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
/* ─────────────────────────────────────────────────────────────────────
   wizard-v6 CSS — 1:1 копие от mockups/wizard_v6_INTERACTIVE.html
   reset (m.11-13) + theme vars (m.15-17) + body (m.19-20) +
   aurora (m.29-37) + wz-header (m.42-44) + SACRED GLASS (m.107-133)
   ───────────────────────────────────────────────────────────────────── */
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html,body{min-height:100%}body{font-family:'Montserrat',sans-serif;overflow-x:hidden}
button,input,a,select,textarea{font-family:inherit;color:inherit;font-size:inherit}button{background:none;border:none;cursor:pointer}

:root{--hue1:255;--hue2:222;--hue3:180;--radius:22px;--radius-sm:14px;--radius-pill:999px;--radius-icon:50%;--ease:cubic-bezier(0.5,1,0.89,1);--ease-spring:cubic-bezier(0.34,1.56,0.64,1);--dur:250ms;--font-mono:'DM Mono',ui-monospace,monospace;--border:1px;--z-aurora:0;--z-shine:1;--z-glow:3;--z-content:5}
:root:not([data-theme]),:root[data-theme="light"]{--bg-main:#e0e5ec;--surface:#e0e5ec;--surface-2:#d1d9e6;--text:#2d3748;--text-muted:#64748b;--text-faint:#94a3b8;--shadow-light:#ffffff;--shadow-dark:#a3b1c6;--shadow-card:8px 8px 16px var(--shadow-dark),-8px -8px 16px var(--shadow-light);--shadow-card-sm:4px 4px 8px var(--shadow-dark),-4px -4px 8px var(--shadow-light);--shadow-pressed:inset 4px 4px 8px var(--shadow-dark),inset -4px -4px 8px var(--shadow-light);--accent:oklch(0.62 0.22 285);--accent-2:oklch(0.65 0.25 305);--magic:oklch(0.65 0.25 310);--gain:oklch(0.6 0.18 145);--loss:oklch(0.6 0.22 25);--amber:oklch(0.7 0.18 60)}
:root[data-theme="dark"]{--bg-main:#08090d;--surface:hsl(220,25%,4.8%);--surface-2:hsl(220,25%,8%);--text:#f1f5f9;--text-muted:rgba(255,255,255,0.7);--text-faint:rgba(255,255,255,0.4);--shadow-card:hsl(var(--hue2) 50% 2%) 0 10px 16px -8px;--shadow-card-sm:hsl(var(--hue2) 50% 2%) 0 4px 8px -2px;--shadow-pressed:inset 0 2px 4px hsl(var(--hue2) 50% 2%);--accent:hsl(var(--hue1),80%,68%);--accent-2:hsl(var(--hue2),80%,68%);--magic:hsl(280,75%,68%);--gain:hsl(145,65%,55%);--loss:hsl(0,75%,65%);--amber:hsl(38,90%,60%)}

:root:not([data-theme]) body,[data-theme="light"] body{background:var(--bg-main);color:var(--text)}
[data-theme="dark"] body{background:radial-gradient(ellipse 800px 500px at 20% 10%,hsl(var(--hue1) 60% 35% / .22) 0%,transparent 60%),radial-gradient(ellipse 700px 500px at 85% 85%,hsl(var(--hue2) 60% 35% / .22) 0%,transparent 60%),linear-gradient(180deg,#0a0b14 0%,#050609 100%);background-attachment:fixed;color:var(--text)}

/* ───── AURORA (dark mode bg blobs) — m.29-37 ───── */
.aurora{position:fixed;inset:0;overflow:hidden;pointer-events:none;z-index:0}
.aurora-blob{position:absolute;border-radius:50%;filter:blur(80px);opacity:0.45;mix-blend-mode:plus-lighter;animation:auroraDrift 22s ease-in-out infinite}
.aurora-blob:nth-child(1){width:340px;height:340px;background:hsl(var(--hue1),80%,60%);top:-90px;left:-110px}
.aurora-blob:nth-child(2){width:300px;height:300px;background:hsl(280,75%,62%);top:30%;right:-130px;animation-delay:5s}
.aurora-blob:nth-child(3){width:320px;height:320px;background:hsl(var(--hue2),70%,55%);bottom:-120px;left:15%;animation-delay:10s}
[data-theme="light"] .aurora-blob{opacity:0.22;mix-blend-mode:multiply}
@keyframes auroraDrift{0%,100%{transform:translate(0,0) scale(1)}33%{transform:translate(40px,-30px) scale(1.08)}66%{transform:translate(-30px,40px) scale(0.95)}}

/* ───── SHELL / LAYOUT ───── */
.shell{position:relative;z-index:5;max-width:480px;margin:0 auto;padding-bottom:calc(86px + env(safe-area-inset-bottom,0))}

/* ───── ICON BUTTONS (header buttons) — m.46-53 ───── */
.icon-btn{width:38px;height:38px;border-radius:var(--radius-icon);display:grid;place-items:center;flex-shrink:0;transition:transform 150ms}
.icon-btn:active{transform:scale(0.94)}
[data-theme="light"] .icon-btn,:root:not([data-theme]) .icon-btn{background:var(--surface);box-shadow:var(--shadow-card-sm)}
[data-theme="dark"] .icon-btn{background:hsl(220 25% 8%);border:1px solid hsl(var(--hue2) 12% 18%)}
.icon-btn svg{width:16px;height:16px;stroke:var(--text);fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}

/* ───── HEADER (wz-header) — m.42-44 ───── */
.wz-header{position:sticky;top:0;z-index:50;height:56px;padding:0 12px;display:flex;align-items:center;gap:8px}
[data-theme="light"] .wz-header,:root:not([data-theme]) .wz-header{background:var(--bg-main);box-shadow:0 4px 12px rgba(163,177,198,0.15)}
[data-theme="dark"] .wz-header{background:hsl(220 25% 4.8% / 0.85);backdrop-filter:blur(16px);border-bottom:1px solid hsl(var(--hue2) 12% 14%)}
.wz-title{flex:1;font-size:15px;font-weight:800;letter-spacing:-0.01em;background:linear-gradient(135deg,var(--text),var(--accent));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}

/* ───── MAIN ───── */
.wz-main{padding:12px;position:relative;display:flex;flex-direction:column;gap:14px}

/* ───── SACRED GLASS + SHINE + GLOW — m.107-133 (1:1 от §5.4 DESIGN_SYSTEM_v4.0_BICHROMATIC) ───── */
.glass{position:relative;border-radius:var(--radius);border:var(--border) solid transparent;isolation:isolate}
.glass.sm{border-radius:var(--radius-sm)}
.glass .shine,.glass .glow{--hue:var(--hue1)}
.glass .shine-bottom,.glass .glow-bottom{--hue:var(--hue2);--conic:135deg}
[data-theme="light"] .glass,:root:not([data-theme]) .glass{background:var(--surface);box-shadow:var(--shadow-card)}
[data-theme="light"] .glass .shine,[data-theme="light"] .glass .glow,:root:not([data-theme]) .glass .shine,:root:not([data-theme]) .glass .glow{display:none}
[data-theme="dark"] .glass{background:linear-gradient(235deg,hsl(var(--hue1) 50% 10% / .8),hsl(var(--hue1) 50% 10% / 0) 33%),linear-gradient(45deg,hsl(var(--hue2) 50% 10% / .8),hsl(var(--hue2) 50% 10% / 0) 33%),linear-gradient(hsl(220 25% 4.8% / .78));backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);box-shadow:var(--shadow-card)}
[data-theme="dark"] .glass .shine{pointer-events:none;border-radius:0;border-top-right-radius:inherit;border-bottom-left-radius:inherit;border:1px solid transparent;width:75%;aspect-ratio:1;display:block;position:absolute;right:calc(var(--border) * -1);top:calc(var(--border) * -1);z-index:var(--z-shine);background:conic-gradient(from var(--conic, -45deg) at center in oklch, transparent 12%, hsl(var(--hue), 80%, 60%), transparent 50%) border-box;mask:linear-gradient(transparent),linear-gradient(black);mask-clip:padding-box,border-box;mask-composite:subtract}
[data-theme="dark"] .glass .shine.shine-bottom{right:auto;top:auto;left:calc(var(--border) * -1);bottom:calc(var(--border) * -1)}
[data-theme="dark"] .glass .glow{pointer-events:none;border-top-right-radius:calc(var(--radius) * 2.5);border-bottom-left-radius:calc(var(--radius) * 2.5);border:calc(var(--radius) * 1.25) solid transparent;inset:calc(var(--radius) * -2);width:75%;aspect-ratio:1;display:block;position:absolute;left:auto;bottom:auto;background:conic-gradient(from var(--conic, -45deg) at center in oklch, hsl(var(--hue), 80%, 60% / .5) 12%, transparent 50%);filter:blur(12px) saturate(1.25);mix-blend-mode:plus-lighter;z-index:var(--z-glow);opacity:0.6}
[data-theme="dark"] .glass .glow.glow-bottom{inset:auto;left:calc(var(--radius) * -2);bottom:calc(var(--radius) * -2)}
/* Hue overrides */
[data-theme="dark"] .glass.q1 .shine,[data-theme="dark"] .glass.q1 .glow{--hue:0}
[data-theme="dark"] .glass.q1 .shine-bottom,[data-theme="dark"] .glass.q1 .glow-bottom{--hue:15}
[data-theme="dark"] .glass.q2 .shine,[data-theme="dark"] .glass.q2 .glow,[data-theme="dark"] .glass.qm .shine,[data-theme="dark"] .glass.qm .glow{--hue:280}
[data-theme="dark"] .glass.q2 .shine-bottom,[data-theme="dark"] .glass.q2 .glow-bottom{--hue:305}
[data-theme="dark"] .glass.qm .shine-bottom,[data-theme="dark"] .glass.qm .glow-bottom{--hue:310}
[data-theme="dark"] .glass.q3 .shine,[data-theme="dark"] .glass.q3 .glow{--hue:145}
[data-theme="dark"] .glass.q3 .shine-bottom,[data-theme="dark"] .glass.q3 .glow-bottom{--hue:165}
[data-theme="dark"] .glass.q4 .shine,[data-theme="dark"] .glass.q4 .glow{--hue:180}
[data-theme="dark"] .glass.q4 .shine-bottom,[data-theme="dark"] .glass.q4 .glow-bottom{--hue:195}
[data-theme="dark"] .glass.q5 .shine,[data-theme="dark"] .glass.q5 .glow{--hue:38}
[data-theme="dark"] .glass.q5 .shine-bottom,[data-theme="dark"] .glass.q5 .glow-bottom{--hue:28}
[data-theme="dark"] .glass.qd .shine,[data-theme="dark"] .glass.qd .glow{--hue:var(--hue1)}
[data-theme="dark"] .glass.qd .shine-bottom,[data-theme="dark"] .glass.qd .glow-bottom{--hue:var(--hue2)}
.glass > *:not(.shine):not(.glow){position:relative;z-index:var(--z-content)}

/* ───── SECTION INNER PADDING + PLACEHOLDER ИЗГЛЕД ───── */
.glass h2{padding:18px 18px 8px;font-size:16px;font-weight:900;letter-spacing:-0.01em;background:linear-gradient(135deg,var(--text),var(--accent));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;line-height:1.2}
.glass .ph{padding:4px 18px 20px;font-size:11.5px;font-weight:600;color:var(--text-muted);font-family:var(--font-mono);letter-spacing:0.03em}

/* ───── FOOTER (wz-foot) ───── */
.wz-foot{position:fixed;bottom:0;left:0;right:0;z-index:60;padding:10px 12px calc(10px + env(safe-area-inset-bottom,0));display:flex;align-items:center;gap:8px;backdrop-filter:blur(16px);max-width:480px;margin:0 auto}
[data-theme="light"] .wz-foot,:root:not([data-theme]) .wz-foot{background:hsl(0 0% 88% / 0.92);box-shadow:0 -4px 16px rgba(163,177,198,0.2)}
[data-theme="dark"] .wz-foot{background:hsl(220 25% 4% / 0.85);border-top:1px solid hsl(var(--hue2) 12% 14%)}
.wz-foot button{padding:0 14px;height:42px;border-radius:var(--radius-pill);font-size:12px;font-weight:800;display:flex;align-items:center;gap:6px}
[data-theme="dark"] .wz-foot button{background:hsl(220 25% 8%);border:1px solid hsl(var(--hue2) 12% 18%);color:var(--text)}
.wz-foot .save-btn{margin-left:auto;background:linear-gradient(135deg,hsl(38 90% 60%),hsl(28 85% 55%))!important;color:white!important;border:none!important;box-shadow:0 4px 14px hsl(38 90% 50% / 0.4)}

/* ═══ S148 ФАЗА 2e — sacred photo block CSS (1:1 от products.php) ═══
   Vars (1:1 от design-kit/tokens.css 36-40, 80-84):
   --indigo-300, --text-primary, --text-secondary, --success, --border-subtle, --bg-card
   Sacred CSS (1:1 verbatim):
     .toast-c / .toast / .toast.show / .toast.success / .toast.error   ← p.php 2229-2238
     .photo-mode-toggle / .pmt-opt / .pmt-opt.active / .pmt-opt svg     ← p.php 1792-1795
     .photo-multi-grid / .photo-multi-cell / .photo-multi-thumb / .ph-* ← p.php 1798-1804
     .photo-color-input / .photo-color-swatch / .photo-color-conf*     ← p.php 1806-1811
     .photo-empty-add / .photo-empty-add:hover / svg                   ← p.php 1813-1815
     .photo-multi-info / .photo-multi-info b                            ← p.php 1817-1818
     .v4-pz / .v4-pz::before / .v4-pz-top/-ic/-title/-sub/-btns/-tips   ← p.php 2851-2895
     .ai-inline-rows / .ai-inline-row (минимум)                         ← p.php 2899-2900
*/
:root[data-theme="dark"]{--indigo-300:#a5b4fc;--indigo-400:#818cf8;--indigo-500:#6366f1;--indigo-600:#4f46e5;--text-primary:#f1f5f9;--text-secondary:rgba(255,255,255,.6);--success:#86efac;--border-subtle:rgba(99,102,241,.15);--bg-card:rgba(15,15,40,.75);--border-glow:rgba(99,102,241,.4)}
:root:not([data-theme]),:root[data-theme="light"]{--indigo-300:#4f46e5;--indigo-400:#6366f1;--indigo-500:#4f46e5;--indigo-600:#4338ca;--text-primary:#0f172a;--text-secondary:rgba(15,23,42,.70);--success:#22c55e;--border-subtle:rgba(99,102,241,.18);--bg-card:rgba(255,255,255,.95);--border-glow:rgba(99,102,241,.4)}

.toast-c{position:fixed;top:16px;left:16px;right:16px;z-index:500;display:flex;flex-direction:column;gap:6px;pointer-events:none}
.toast{padding:10px 16px;border-radius:12px;background:rgba(15,15,40,0.95);border:1px solid var(--border-glow);color:var(--text-primary);font-size:13px;font-weight:600;transform:translateY(-20px);opacity:0;transition:all 0.3s;pointer-events:auto;display:flex;align-items:center;gap:6px}
.toast.show{transform:translateY(0);opacity:1}
.toast.success{border-color:rgba(34,197,94,0.4)}
.toast.error{border-color:rgba(239,68,68,0.4)}

.photo-mode-toggle{display:flex;gap:5px;padding:3px;background:rgba(0,0,0,0.3);border-radius:10px;margin-bottom:10px;border:1px solid rgba(99,102,241,0.1)}
.pmt-opt{flex:1;padding:7px 8px;border-radius:8px;background:transparent;border:none;color:rgba(255,255,255,0.5);font-size:10.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;font-family:inherit;transition:all .18s}
.pmt-opt.active{background:linear-gradient(180deg,rgba(99,102,241,0.2),rgba(67,56,202,0.1));color:var(--indigo-300);box-shadow:inset 0 1px 0 rgba(255,255,255,0.05)}
.pmt-opt svg{width:13px;height:13px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}

.photo-multi-grid{display:flex;gap:8px;margin-bottom:8px;overflow-x:auto;overflow-y:hidden;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;padding:2px 2px 8px;scrollbar-width:none;scroll-padding-left:2px}
.photo-multi-grid::-webkit-scrollbar{display:none}
.photo-multi-cell{position:relative;display:flex;flex-direction:column;gap:6px;flex:0 0 calc(50% - 4px);min-width:0;scroll-snap-align:start;scroll-snap-stop:always}
.photo-multi-thumb{position:relative;aspect-ratio:1;border-radius:10px;overflow:hidden;background:rgba(99,102,241,0.05);border:1px solid rgba(99,102,241,0.18)}
.photo-multi-thumb .ph-img{width:100%;height:100%;object-fit:cover;display:block}
.photo-multi-thumb .ph-num{position:absolute;top:5px;left:5px;padding:2px 7px;border-radius:100px;background:rgba(0,0,0,0.7);color:#fff;font-size:10px;font-weight:800;line-height:1.4}
.photo-multi-thumb .ph-rm{position:absolute;top:5px;right:5px;width:22px;height:22px;border-radius:50%;background:rgba(239,68,68,0.85);color:#fff;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;font-family:inherit;line-height:1;padding:0}

.photo-color-input{display:flex;flex-wrap:wrap;align-items:center;gap:5px;padding:6px 9px;border-radius:8px;background:rgba(0,0,0,0.3);border:1px solid rgba(99,102,241,0.2)}
.photo-color-swatch{width:14px;height:14px;border-radius:4px;flex-shrink:0;border:0.5px solid rgba(255,255,255,0.2)}
.photo-color-input input{flex:1 1 100%;order:2;background:transparent;border:none;color:var(--text-primary);font-size:11px;font-weight:600;outline:none;font-family:inherit;padding:2px 0;min-width:0}
.photo-color-conf{font-size:8px;font-weight:800;color:#86efac;letter-spacing:0.05em;flex-shrink:0}
.photo-color-conf.warn{color:#fbbf24}
.photo-color-conf.detecting{color:var(--indigo-300)}

.photo-empty-add{aspect-ratio:1;border-radius:10px;background:rgba(99,102,241,0.05);border:1.5px dashed rgba(99,102,241,0.3);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;cursor:pointer;color:var(--indigo-300);font-size:10px;font-weight:600;font-family:inherit;transition:all .15s;padding:8px}
.photo-empty-add:hover{background:rgba(99,102,241,0.1);border-color:rgba(99,102,241,0.5)}
.photo-empty-add svg{width:22px;height:22px;stroke:currentColor;stroke-width:2;fill:none;stroke-linecap:round;stroke-linejoin:round}

.photo-multi-info{padding:7px 10px;border-radius:9px;background:rgba(139,92,246,0.06);border:1px solid rgba(139,92,246,0.2);font-size:10.5px;color:var(--indigo-300);font-weight:600;text-align:center;margin-bottom:8px;line-height:1.4}
.photo-multi-info b{color:var(--text-primary)}

.v4-pz{position:relative;overflow:hidden;border-radius:18px;margin-bottom:14px;padding:16px 14px 12px;background:radial-gradient(ellipse 80% 60% at 50% 30%, rgba(99,102,241,0.10), transparent 70%),linear-gradient(180deg, rgba(99,102,241,0.04), rgba(8,11,24,0.5));border:1.5px dashed rgba(99,102,241,0.32)}
.v4-pz::before{content:'';position:absolute;top:0;left:20%;right:20%;height:1px;background:linear-gradient(90deg,transparent,rgba(165,180,252,0.5),transparent)}
.v4-pz-top{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.v4-pz-ic{width:44px;height:44px;border-radius:13px;flex-shrink:0;background:linear-gradient(135deg,rgba(99,102,241,0.3),rgba(139,92,246,0.15));border:1px solid rgba(139,92,246,0.4);display:flex;align-items:center;justify-content:center;box-shadow:0 0 18px rgba(99,102,241,0.25),inset 0 1px 0 rgba(255,255,255,0.08)}
.v4-pz-title{font-size:14px;font-weight:700;letter-spacing:-0.01em;background:linear-gradient(135deg,#fff,#a5b4fc);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.v4-pz-sub{font-size:10px;color:rgba(226,232,240,0.55);margin-top:1px}
.v4-pz-btns{display:flex;gap:8px;margin-bottom:10px}
.v4-pz-btn{flex:1;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;gap:6px;font-family:inherit;font-size:12px;font-weight:700;cursor:pointer;transition:all .2s}
.v4-pz-btn.primary{background:linear-gradient(135deg,rgba(99,102,241,0.25),rgba(99,102,241,0.12));border:1px solid rgba(99,102,241,0.5);color:#c7d2fe;box-shadow:0 0 14px rgba(99,102,241,0.2),inset 0 1px 0 rgba(255,255,255,0.08)}
.v4-pz-btn.primary:active{transform:scale(.97)}
.v4-pz-btn.sec{background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.12);color:#cbd5e1}
.v4-pz-tips{display:flex;flex-wrap:wrap;gap:4px 10px;padding-top:10px;border-top:1px dashed rgba(99,102,241,0.15)}
.v4-pz-tip{display:inline-flex;align-items:center;gap:4px;font-size:9.5px;font-weight:500;color:rgba(255,255,255,0.55)}
.v4-pz-tip svg{width:10px;height:10px;color:#86efac;flex-shrink:0}

/* ═══ S148 ФАЗА 2e++e — AI inline rows (1:1 sacred p.php 2899-2907) ═══ */
.ai-inline-rows{display:flex;flex-direction:column;gap:6px;margin:8px 0 10px}
.ai-inline-row{position:relative;display:flex;align-items:center;gap:10px;padding:11px 14px;min-height:44px;border-radius:12px;background:linear-gradient(180deg,rgba(139,92,246,0.10),rgba(99,102,241,0.04));border:1px solid rgba(139,92,246,0.32);color:#e2e8f0;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:transform .12s ease,background .2s ease,border-color .2s ease;box-shadow:0 0 14px rgba(139,92,246,0.10),inset 0 1px 0 rgba(255,255,255,0.04)}
.ai-inline-row.busy{opacity:0.65;pointer-events:none}
.ai-inline-row.busy::after{content:'';position:absolute;right:14px;top:50%;width:14px;height:14px;border:2px solid rgba(196,181,253,0.3);border-top-color:#c4b5fd;border-radius:50%;transform:translateY(-50%);animation:airSpin .8s linear infinite}
@keyframes airSpin{to{transform:translateY(-50%) rotate(360deg)}}
.ai-inline-row .air-ic{flex-shrink:0;font-size:16px;width:22px;text-align:center}
.ai-inline-row .air-lbl{flex:1;line-height:1.2}
.ai-inline-row .air-price{flex-shrink:0;font-size:11px;font-weight:700;color:#a5b4fc;letter-spacing:.02em}

/* ═══ S148 ФАЗА 2e+ — light mode overrides (нов CSS, products.php няма такива) ═══
   Проверено: products.php дефинира [data-theme="light"] overrides за други класове
   (.rms-header, .glass, .lb-*, .chat-*, etc.) НО НЕ за .v4-pz/.toast/.photo-* —
   sacred wizard работи само в dark. Тук добавяме светъл вариант за wizard-v6.php
   (новаторски CSS, не sacred copy). Dark стиловете по-горе остават непроменени.
*/
[data-theme="light"] .v4-pz,:root:not([data-theme]) .v4-pz{background:linear-gradient(180deg,rgba(99,102,241,0.06),var(--surface));border-color:rgba(99,102,241,0.35)}
[data-theme="light"] .v4-pz-title,:root:not([data-theme]) .v4-pz-title{background:linear-gradient(135deg,var(--text),var(--accent));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
[data-theme="light"] .v4-pz-sub,:root:not([data-theme]) .v4-pz-sub{color:var(--text-muted)}
[data-theme="light"] .v4-pz-btn.primary,:root:not([data-theme]) .v4-pz-btn.primary{background:linear-gradient(135deg,rgba(99,102,241,0.18),rgba(99,102,241,0.08));color:var(--accent);box-shadow:var(--shadow-card-sm)}
[data-theme="light"] .v4-pz-btn.sec,:root:not([data-theme]) .v4-pz-btn.sec{background:var(--surface);border-color:rgba(0,0,0,0.06);color:var(--text);box-shadow:var(--shadow-card-sm)}
[data-theme="light"] .v4-pz-tips,:root:not([data-theme]) .v4-pz-tips{border-top-color:rgba(99,102,241,0.25)}
[data-theme="light"] .v4-pz-tip,:root:not([data-theme]) .v4-pz-tip{color:var(--text-muted)}
[data-theme="light"] .v4-pz-tip svg,:root:not([data-theme]) .v4-pz-tip svg{color:oklch(0.55 0.18 145)}
[data-theme="light"] .toast,:root:not([data-theme]) .toast{background:var(--surface);color:var(--text);box-shadow:var(--shadow-card-sm);border-color:rgba(0,0,0,0.08)}
[data-theme="light"] .photo-mode-toggle,:root:not([data-theme]) .photo-mode-toggle{background:var(--surface);box-shadow:var(--shadow-pressed);border-color:rgba(0,0,0,0.06)}
[data-theme="light"] .pmt-opt,:root:not([data-theme]) .pmt-opt{color:var(--text-muted)}
[data-theme="light"] .pmt-opt.active,:root:not([data-theme]) .pmt-opt.active{background:var(--surface);color:var(--accent);box-shadow:var(--shadow-card-sm)}
[data-theme="light"] .photo-multi-thumb,:root:not([data-theme]) .photo-multi-thumb{background:var(--surface);border-color:rgba(99,102,241,0.28)}
[data-theme="light"] .photo-color-input,:root:not([data-theme]) .photo-color-input{background:var(--surface);box-shadow:var(--shadow-pressed);border-color:rgba(0,0,0,0.06)}
[data-theme="light"] .photo-color-input input,:root:not([data-theme]) .photo-color-input input{color:var(--text)}
[data-theme="light"] .photo-empty-add,:root:not([data-theme]) .photo-empty-add{background:var(--surface);box-shadow:var(--shadow-pressed);border-color:rgba(99,102,241,0.4);color:var(--accent)}
[data-theme="light"] .photo-empty-add:hover,:root:not([data-theme]) .photo-empty-add:hover{background:rgba(99,102,241,0.08);border-color:rgba(99,102,241,0.6)}
[data-theme="light"] .photo-multi-info,:root:not([data-theme]) .photo-multi-info{background:rgba(139,92,246,0.08);border-color:rgba(139,92,246,0.3);color:var(--accent)}
[data-theme="light"] .photo-multi-info b,:root:not([data-theme]) .photo-multi-info b{color:var(--text)}
[data-theme="light"] .ai-inline-row,:root:not([data-theme]) .ai-inline-row{background:linear-gradient(180deg,rgba(139,92,246,0.08),rgba(99,102,241,0.04));border-color:rgba(139,92,246,0.3);color:var(--text);box-shadow:var(--shadow-card-sm)}
[data-theme="light"] .ai-inline-row .air-price,:root:not([data-theme]) .ai-inline-row .air-price{color:var(--accent)}

/* ═══ S148 ФАЗА 2f — sacred form-control + mic CSS (1:1 от products.php) ═══
   .fg / .fl / .fc / .fc:focus / .fc::placeholder    ← p.php 2159-2169
   .fg input.fc, .fg select.fc                       ← p.php 2177-2179
   .form-row                                         ← p.php 2186
   .wiz-mic / :active                                ← p.php 2201-2202
   .fg.wiz-next / .fg.wiz-next .wiz-mic / @kf        ← p.php 2203-2205
   .fg.wiz-done .fl::after                           ← p.php 2206
   .wiz-mic.recording (+ ::after, ::before) + @kf    ← p.php 2207-2211
   .fg.wiz-active / .fg.wiz-active .wiz-mic          ← p.php 2212-2213
*/
.fg{margin-bottom:10px}
.fl{display:block;font-size:12px;font-weight:700;color:var(--text-secondary);margin-bottom:3px;text-transform:uppercase;letter-spacing:0.3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fl .hint{color:rgba(107,114,128,0.7);font-weight:400;text-transform:none;letter-spacing:0}
.fl .fl-add{float:right;color:var(--indigo-300);font-weight:700;cursor:pointer;text-transform:none;letter-spacing:0;font-size:12px;padding:4px 10px;border-radius:8px;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.3)}
.fc{width:100%;padding:9px 12px;border-radius:10px;border:1px solid var(--border-subtle);background:rgba(30,35,50,0.9);color:var(--text-primary);font-size:14px;outline:none;font-family:inherit;transition:border-color 0.2s}
.fc:focus{border-color:var(--border-glow);box-shadow:0 0 12px rgba(99,102,241,0.1)}
.fc::placeholder{color:var(--text-secondary)}
.fg input.fc,.fg select.fc{min-height:42px;padding:10px 14px;border-radius:12px;font-size:14px;box-sizing:border-box}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:8px}

.wiz-mic{width:42px;min-width:42px;height:42px;border-radius:10px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#fca5a5;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s}
.wiz-mic:active{background:rgba(239,68,68,.2);transform:scale(.95)}
.fg.wiz-next{background:rgba(99,102,241,.08);border-radius:10px;padding:8px;margin-left:-8px;margin-right:-8px;border:1.5px solid rgba(99,102,241,.35)}
.fg.wiz-next .wiz-mic{background:rgba(99,102,241,.25);border-color:#6366f1;animation:wizNextPulse 1.5s infinite}
@keyframes wizNextPulse{0%,100%{box-shadow:0 0 0 0 rgba(99,102,241,.2)}50%{box-shadow:0 0 14px 4px rgba(99,102,241,.2)}}
.fg.wiz-done .fl::after{content:' \2713';color:#4ade80;font-weight:700}
.wiz-mic.recording{background:rgba(239,68,68,.3)!important;border-color:#ef4444!important;color:#fff!important;animation:micRecPulse .8s infinite!important;position:relative}
.wiz-mic.recording::after{content:'REC';position:absolute;top:-18px;left:50%;transform:translateX(-50%);font-size:8px;font-weight:800;color:#ef4444;letter-spacing:1px;white-space:nowrap;text-shadow:0 0 8px rgba(239,68,68,.6)}
.wiz-mic.recording::before{content:'';position:absolute;top:-8px;right:-2px;width:8px;height:8px;border-radius:50%;background:#ef4444;box-shadow:0 0 6px #ef4444,0 0 12px rgba(239,68,68,.5);animation:micRecDot .6s infinite}
@keyframes micRecPulse{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.4)}50%{box-shadow:0 0 16px 4px rgba(239,68,68,.3)}}
@keyframes micRecDot{0%,100%{opacity:1}50%{opacity:.3}}
.fg.wiz-active{background:rgba(99,102,241,.06);border-radius:10px;padding:8px;margin-left:-8px;margin-right:-8px;border:1.5px solid rgba(99,102,241,.25);transition:all .2s}
.fg.wiz-active .wiz-mic{border-color:rgba(99,102,241,.4);background:rgba(99,102,241,.12)}

/* ═══ S148 ФАЗА 2e++a — type toggle (Единичен / С Вариации) — sacred 1:1 от p.php 2947-2961 ═══
   Логика: state-only — wizSwitchType сетва S.wizType, БЕЗ да отваря Phase 3 (renderWizPagePart2
   sacred undisturbed). photo-mode toggle (multi) става достъпен при wizType==='variant'.
*/
.s95-type-btn{flex:1;min-height:64px;border-radius:14px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;background:rgba(255,255,255,0.03);border:1px solid rgba(99,102,241,0.22);color:rgba(255,255,255,0.62);transition:transform .12s ease,box-shadow .2s ease,border-color .2s ease,background .2s ease}
.s95-type-btn svg{opacity:0.75}
.s95-type-btn:active{transform:scale(.97)}
.s95-type-btn.active{background:linear-gradient(180deg,rgba(59,130,246,0.20),rgba(37,99,235,0.08));border-color:rgba(59,130,246,0.65);color:#dbeafe;box-shadow:0 0 18px rgba(59,130,246,0.32),inset 0 1px 0 rgba(255,255,255,0.06)}
.s95-type-btn.active svg{opacity:1;color:#bfdbfe}
.s95-type-btn.variant.active{background:linear-gradient(180deg,rgba(217,70,239,0.18),rgba(168,85,247,0.07));border-color:rgba(217,70,239,0.6);color:#fbcfe8;box-shadow:0 0 18px rgba(217,70,239,0.32),inset 0 1px 0 rgba(255,255,255,0.06)}
.s95-type-btn.variant.active svg{opacity:1;color:#f0abfc}
.s95-type-btn-lbl{font-size:12px;font-weight:700;letter-spacing:0.02em}

/* Light overrides — inactive state. Active gradients остават такива (контраст на цветни active states е достатъчен в двата режима). */
[data-theme="light"] .s95-type-btn,:root:not([data-theme]) .s95-type-btn{background:var(--surface);border-color:rgba(99,102,241,0.22);color:var(--text-muted);box-shadow:var(--shadow-card-sm)}

/* ═══ S148 ФАЗА 2e++b — camera loop overlay + AI working overlay (1:1 sacred от p.php 2032-2146) ═══
   Full-screen camera UX винаги остава dark — не добавям light overrides (одобрено от Тих).
   .cam-tip / .cam-setup / .cam-picker са sacred "kept inert" класове — копирани за 1:1 fidelity.
*/
.cam-loop-ov{position:fixed;inset:0;background:#000;z-index:9999;display:none;flex-direction:column}
.cam-loop-ov.show{display:flex}
.cam-loop-stage{flex:1;display:flex;align-items:center;justify-content:center;background:#000;overflow:hidden;position:relative}
.cam-loop-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:20px}
.cam-loop-empty-msg{color:rgba(255,255,255,0.55);font-size:13px;text-align:center;line-height:1.5;max-width:280px}
.cam-loop-preview{max-width:100%;max-height:100%;width:auto;height:auto;object-fit:contain;background:#000;display:block}
.cam-loop-stage:has(.cam-loading){background:linear-gradient(135deg,#1a1033,#0a0518)}
.cam-loading{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:24px;padding:40px 28px;text-align:center;animation:camLoadFadeIn 0.18s ease}
@keyframes camLoadFadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.cam-loader{display:flex;gap:14px}
.cam-loader div{width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#a78bfa,#6366f1);box-shadow:0 0 28px rgba(167,139,250,0.85),0 0 50px rgba(99,102,241,0.5);opacity:0.35;animation:camLoaderPulse 1.2s infinite ease-in-out}
.cam-loader div:nth-child(1){animation-delay:-0.32s}
.cam-loader div:nth-child(2){animation-delay:-0.16s}
@keyframes camLoaderPulse{0%,80%,100%{opacity:0.35;transform:scale(0.7)}40%{opacity:1;transform:scale(1.4)}}
.cam-loading-msg{font-size:18px;font-weight:800;color:#fff;letter-spacing:0.01em;text-shadow:0 2px 12px rgba(167,139,250,0.4)}
.cam-loading-sub{font-size:12.5px;color:rgba(233,213,255,0.65);max-width:280px;line-height:1.5;font-weight:500}

.cam-tip{display:flex;flex-direction:column;align-items:center;gap:18px;padding:28px 24px;max-width:340px;background:linear-gradient(135deg,rgba(124,58,237,0.18),rgba(99,102,241,0.10));border:1.5px solid rgba(139,92,246,0.4);border-radius:20px;margin:20px}
.cam-tip-icon{width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,rgba(167,139,250,0.25),rgba(99,102,241,0.15));display:flex;align-items:center;justify-content:center}
.cam-tip-icon svg{width:30px;height:30px}
.cam-tip-title{font-size:18px;font-weight:800;color:#e9d5ff;text-align:center}
.cam-tip-body{font-size:13px;color:rgba(233,213,255,0.85);text-align:center;line-height:1.6;font-weight:500}
.cam-tip-body b{color:#fff}
.cam-tip-flip{display:inline-block;padding:2px 8px;border-radius:6px;background:rgba(167,139,250,0.25);font-size:14px;border:1px solid rgba(167,139,250,0.4)}
.cam-tip-btn{padding:13px 22px;border-radius:14px;background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;border:none;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;width:100%;box-shadow:0 4px 18px rgba(124,58,237,0.4)}

.cam-drawer-tip{display:flex;align-items:flex-start;gap:10px;padding:11px 12px;margin-bottom:12px;border-radius:12px;background:linear-gradient(135deg,rgba(124,58,237,0.14),rgba(99,102,241,0.07));border:1px solid rgba(139,92,246,0.32);position:relative;overflow:hidden;animation:tipFadeIn 0.4s ease-out}
.cam-drawer-tip::before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,transparent 30%,rgba(167,139,250,0.08) 50%,transparent 70%);background-size:200% 200%;animation:tipShine 4s ease-in-out infinite;pointer-events:none}
@keyframes tipFadeIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
@keyframes tipShine{0%,100%{background-position:200% 200%}50%{background-position:0% 0%}}
.cam-drawer-tip-icon{font-size:22px;flex-shrink:0;line-height:1.2;animation:tipIconPulse 2.4s ease-in-out infinite;filter:drop-shadow(0 0 8px rgba(251,191,36,0.6))}
@keyframes tipIconPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.18)}}
.cam-drawer-tip-text{font-size:11.5px;color:rgba(233,213,255,0.82);line-height:1.55;font-weight:500;flex:1;min-width:0;position:relative}
.cam-drawer-tip-text b{color:#fff;font-weight:700}
.cam-drawer-tip-app{display:inline-block;padding:1px 6px;margin:0 1px;border-radius:5px;background:rgba(167,139,250,0.22);font-size:11px;font-weight:700;border:1px solid rgba(167,139,250,0.35);color:#fff;white-space:nowrap}
.cam-drawer-tip-or{display:inline-block;color:rgba(233,213,255,0.6);font-size:11px}
.cam-drawer-tip-flip{display:inline-block;font-size:13px;animation:tipFlipRot 2.6s linear infinite;vertical-align:middle}
@keyframes tipFlipRot{from{transform:rotate(0)}to{transform:rotate(360deg)}}

.cam-setup{display:flex;flex-direction:column;gap:16px;padding:24px 20px 20px;max-width:420px;width:100%;overflow-y:auto;max-height:90vh}
.cam-setup-header{text-align:center;margin-bottom:6px}
.cam-setup-emoji{font-size:48px;margin-bottom:8px;animation:setupBounce 2.4s ease-in-out infinite}
@keyframes setupBounce{0%,100%{transform:translateY(0) rotate(-5deg)}50%{transform:translateY(-6px) rotate(5deg)}}
.cam-setup-title{font-size:22px;font-weight:800;color:#fff;letter-spacing:-0.01em;margin-bottom:6px}
.cam-setup-sub{font-size:13.5px;color:rgba(233,213,255,0.8);line-height:1.5}
.cam-setup-steps{display:flex;flex-direction:column;gap:11px;margin:8px 0}
.cam-setup-step{display:flex;gap:14px;align-items:flex-start;padding:14px 14px;border-radius:14px;background:linear-gradient(135deg,rgba(124,58,237,0.16),rgba(99,102,241,0.08));border:1px solid rgba(139,92,246,0.32);opacity:0;transform:translateX(-12px);animation:setupStepIn 0.55s ease-out forwards}
@keyframes setupStepIn{to{opacity:1;transform:translateX(0)}}
.cam-setup-num{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#a78bfa,#6366f1);color:#fff;font-size:15px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 0 14px rgba(167,139,250,0.5);animation:setupNumPulse 2s ease-in-out infinite}
@keyframes setupNumPulse{0%,100%{box-shadow:0 0 14px rgba(167,139,250,0.5)}50%{box-shadow:0 0 22px rgba(167,139,250,0.85)}}
.cam-setup-step-body{flex:1;min-width:0}
.cam-setup-step-title{font-size:14.5px;font-weight:700;color:#fff;line-height:1.35;margin-bottom:3px}
.cam-setup-step-desc{font-size:12px;color:rgba(233,213,255,0.72);line-height:1.5}
.cam-setup-step-desc b{color:#fff}
.cam-setup-tap{display:inline-block;padding:1px 7px;margin:0 2px;border-radius:5px;background:rgba(167,139,250,0.25);font-size:13px;border:1px solid rgba(167,139,250,0.4)}
.cam-setup-app{display:inline-block;padding:1px 8px;margin:0 2px;border-radius:6px;background:rgba(167,139,250,0.25);font-size:13px;font-weight:700;border:1px solid rgba(167,139,250,0.4);color:#fff}
.cam-setup-flip{display:inline-block;font-size:16px;animation:setupFlipRot 2.4s linear infinite}
@keyframes setupFlipRot{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}
.cam-setup-finale{display:flex;align-items:center;gap:12px;padding:13px 14px;border-radius:14px;background:linear-gradient(135deg,rgba(34,197,94,0.18),rgba(22,163,74,0.08));border:1.5px solid rgba(34,197,94,0.4);opacity:0;transform:translateY(8px);animation:setupStepIn 0.55s ease-out forwards}
.cam-setup-finale-icon{font-size:32px;animation:setupFinaleSparkle 1.8s ease-in-out infinite}
@keyframes setupFinaleSparkle{0%,100%{transform:scale(1) rotate(0)}50%{transform:scale(1.15) rotate(8deg)}}
.cam-setup-finale-text{font-size:13.5px;color:#fff;font-weight:600;line-height:1.4}
.cam-setup-finale-text b{color:#86efac}
.cam-setup-done-btn{margin-top:6px;padding:15px 22px;border-radius:14px;background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;border:none;font-size:15px;font-weight:800;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 6px 22px rgba(124,58,237,0.5);opacity:0;transform:translateY(8px);animation:setupStepIn 0.55s ease-out forwards}
.cam-setup-done-btn:active{transform:scale(0.98)}
.cam-setup-done-btn svg{width:18px;height:18px}
.cam-setup-skip{margin-top:4px;background:transparent;border:none;color:rgba(255,255,255,0.4);font-size:11.5px;cursor:pointer;font-family:inherit;padding:8px;text-decoration:underline}

.cam-loop-video{width:100%;height:100%;object-fit:cover;display:block;background:#000}
.cam-picker{display:flex;flex-direction:column;gap:14px;padding:20px;width:100%;max-width:420px;align-items:stretch}
.cam-picker-title{font-size:18px;font-weight:800;color:#e9d5ff;text-align:center}
.cam-picker-sub{font-size:12px;color:rgba(233,213,255,0.65);text-align:center;line-height:1.55;padding:0 8px}
.cam-picker-list{display:flex;flex-direction:column;gap:8px;width:100%}
.cam-picker-item{display:flex;flex-direction:column;gap:8px;padding:12px 14px;border-radius:14px;background:rgba(99,102,241,0.08);border:1px solid rgba(139,92,246,0.25)}
.cam-picker-item-info{display:flex;flex-direction:column;gap:2px}
.cam-picker-item-name{font-size:13px;font-weight:700;color:#fff;word-break:break-word}
.cam-picker-item-sub{font-size:10.5px;color:rgba(233,213,255,0.55)}
.cam-picker-item-actions{display:flex;gap:6px}
.cam-picker-test,.cam-picker-save{flex:1;padding:9px 12px;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;border:none}
.cam-picker-test{background:rgba(255,255,255,0.08);color:#e9d5ff;border:1px solid rgba(255,255,255,0.12)}
.cam-picker-save{background:linear-gradient(135deg,#7c3aed,#6366f1);color:#fff;box-shadow:0 2px 10px rgba(124,58,237,0.35)}
.cam-picker-test-bar{position:absolute;bottom:14px;left:14px;right:14px;display:flex;gap:8px;z-index:2}
.cam-picker-back,.cam-picker-use{flex:1;padding:11px 14px;border-radius:12px;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;border:none}
.cam-picker-back{background:rgba(0,0,0,0.5);color:#fff;border:1px solid rgba(255,255,255,0.15)}
.cam-picker-use{background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;box-shadow:0 2px 14px rgba(22,163,74,0.4)}
.cam-change-link{position:absolute;bottom:calc(80px + env(safe-area-inset-bottom,0));left:50%;transform:translateX(-50%);background:rgba(0,0,0,0.55);color:rgba(255,255,255,0.7);border:1px solid rgba(255,255,255,0.1);padding:6px 14px;border-radius:100px;font-size:10.5px;font-weight:600;cursor:pointer;font-family:inherit;z-index:1}

.ai-working-ov{position:fixed;inset:0;background:rgba(0,0,0,0.72);z-index:10000;display:flex;align-items:center;justify-content:center;animation:aiOvFade 0.22s ease;padding:20px}
@keyframes aiOvFade{from{opacity:0}to{opacity:1}}
.ai-working-card{padding:32px 26px;border-radius:24px;background:linear-gradient(135deg,rgba(124,58,237,0.32),rgba(99,102,241,0.18));border:1.5px solid rgba(139,92,246,0.55);box-shadow:0 0 50px rgba(139,92,246,0.45),inset 0 1px 0 rgba(255,255,255,0.1);display:flex;flex-direction:column;align-items:center;gap:14px;min-width:260px;max-width:340px}
.ai-working-orb{display:flex;gap:10px;margin-bottom:6px}
.ai-working-orb div{width:16px;height:16px;border-radius:50%;background:linear-gradient(135deg,#a78bfa,#6366f1);box-shadow:0 0 16px rgba(167,139,250,0.7);animation:aiOrbPulse 1.4s infinite ease-in-out}
.ai-working-orb div:nth-child(1){animation-delay:-0.32s}
.ai-working-orb div:nth-child(2){animation-delay:-0.16s}
@keyframes aiOrbPulse{0%,80%,100%{opacity:0.4;transform:scale(0.7)}40%{opacity:1;transform:scale(1.4)}}
.ai-working-title{font-size:18px;font-weight:800;color:#e9d5ff;letter-spacing:-0.01em}
.ai-working-msg{font-size:13px;color:rgba(233,213,255,0.85);text-align:center;line-height:1.5;font-weight:600}
.ai-working-hint{font-size:10.5px;color:rgba(233,213,255,0.5);text-align:center;letter-spacing:0.02em}
.cam-loop-controls{padding:14px 14px calc(14px + env(safe-area-inset-bottom,0));background:rgba(0,0,0,0.9);display:flex;gap:8px;align-items:center;justify-content:center}
.cam-loop-btn{padding:14px 18px;border-radius:14px;font-size:13px;font-weight:700;border:none;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px;transition:all .15s}
.cam-loop-btn svg{width:16px;height:16px;stroke:currentColor;stroke-width:2.2;fill:none;stroke-linecap:round;stroke-linejoin:round}
.cam-loop-btn.shoot{width:74px;height:74px;border-radius:50%;background:#fff;color:#000;padding:0;box-shadow:0 0 0 4px rgba(255,255,255,0.25)}
.cam-loop-btn.shoot svg{width:30px;height:30px;stroke-width:2.2}
.cam-loop-btn.next{background:linear-gradient(135deg,var(--indigo-500),var(--indigo-600));color:#fff;flex:1;max-width:160px}
.cam-loop-btn.done{background:linear-gradient(135deg,#16a34a,#15803d);color:#fff;flex:1;max-width:160px}
.cam-loop-btn.retake{background:rgba(255,255,255,0.1);color:#fff;border:1px solid rgba(255,255,255,0.2);flex:1;max-width:140px}
.cam-loop-btn.cancel{background:rgba(239,68,68,0.15);color:#fca5a5;border:1px solid rgba(239,68,68,0.3);width:50px;height:50px;border-radius:14px;padding:0}
.cam-loop-counter{position:absolute;top:calc(14px + env(safe-area-inset-top,0));left:50%;transform:translateX(-50%);padding:6px 14px;border-radius:100px;background:rgba(0,0,0,0.7);color:#fff;font-size:12px;font-weight:700;z-index:1}

/* ═══ S148 ФАЗА 2f — light mode overrides за form-control + mic ═══ */
[data-theme="light"] .fl,:root:not([data-theme]) .fl{color:var(--text-muted)}
[data-theme="light"] .fc,:root:not([data-theme]) .fc{background:var(--surface);color:var(--text);border-color:rgba(0,0,0,0.08);box-shadow:var(--shadow-pressed)}
[data-theme="light"] .fc::placeholder,:root:not([data-theme]) .fc::placeholder{color:var(--text-faint)}
[data-theme="light"] .fc:focus,:root:not([data-theme]) .fc:focus{border-color:var(--accent);box-shadow:var(--shadow-pressed),0 0 0 2px rgba(99,102,241,0.15)}
[data-theme="light"] .wiz-mic,:root:not([data-theme]) .wiz-mic{background:var(--surface);box-shadow:var(--shadow-card-sm);border-color:rgba(239,68,68,0.3);color:oklch(0.58 0.22 25)}
[data-theme="light"] .wiz-mic:active,:root:not([data-theme]) .wiz-mic:active{box-shadow:var(--shadow-pressed)}
[data-theme="light"] .fg.wiz-active .wiz-mic,:root:not([data-theme]) .fg.wiz-active .wiz-mic{background:var(--surface);border-color:rgba(99,102,241,0.4);color:var(--accent)}

/* ╔═══════════════════════════════════════════════════════════════════╗
   ║ S148 ФАЗА 2e++REDESIGN — canon alignment с chat.php Light Mode    ║
   ║ References:                                                        ║
   ║   DESIGN_SYSTEM_v4.0_BICHROMATIC.md §2.2/§4.3/§5.3-5.4              ║
   ║   chat.php редове 540-605 (vars + aurora + glass)                  ║
   ║   mockups/wizard_v6_INTERACTIVE.html ред 15-218                    ║
   ║   mockups/P15_simple_FINAL.html ред 79-86 (keyframes), 188 (cascade)║
   ║ Правила: Light = soft neumorphic, NO BORDERS (border-color:        ║
   ║   transparent), depth ONLY via --shadow-card/-sm/-pressed.         ║
   ║   Mic = purple gradient + chatMicRing rings.                       ║
   ╚═══════════════════════════════════════════════════════════════════╝
*/

/* --- P15 keyframes library (1:1 от P15 ред 79-86 + 673-674) --- */
@keyframes conicSpin{to{transform:rotate(360deg)}}
@keyframes orbSpin{to{transform:rotate(360deg)}}
@keyframes fadeInUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes popUp{from{opacity:0;transform:scale(0.9)}to{opacity:1;transform:scale(1)}}
@keyframes wzPulse{0%,100%{box-shadow:0 0 0 0 hsl(0 70% 50% / 0.5)}50%{box-shadow:0 0 0 6px hsl(0 70% 50% / 0)}}
@keyframes rmsBrandShimmer{0%{background-position:0% center}100%{background-position:200% center}}
@keyframes chatMicRing{0%{transform:scale(1);opacity:0.6}100%{transform:scale(2.2);opacity:0}}
@keyframes chatSendDrift{0%,100%{transform:translateX(0)}50%{transform:translateX(2px)}}
@media (prefers-reduced-motion: reduce){*,*::before,*::after{animation:none!important;transition:none!important}}

/* --- Missing canon vars --- */
:root{--ease-spring:cubic-bezier(0.34,1.56,0.64,1)}
:root[data-theme="dark"]{--text-faint:rgba(255,255,255,0.4)}

/* --- Aurora 4th blob (canon mockup ред 35) --- */
.aurora-blob:nth-child(4){width:260px;height:260px;background:hsl(195,75%,58%);top:55%;left:-80px;animation-delay:15s;opacity:0.32}
[data-theme="light"] .aurora-blob:nth-child(4){opacity:0.18}

/* --- LIGHT MODE neumorphic canon: NO BORDERS, soft shadows ONLY --- */
[data-theme="light"], :root:not([data-theme]){--border-color:transparent}

/* Header brand shimmer (mockup ред 112-119) */
[data-theme="light"] .wz-title,:root:not([data-theme]) .wz-title{background:linear-gradient(90deg,hsl(255 80% 60%),hsl(222 80% 60%),hsl(180 70% 55%),hsl(222 80% 60%),hsl(255 80% 60%));background-size:200% auto;-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;animation:rmsBrandShimmer 4s linear infinite;filter:drop-shadow(0 0 12px hsl(255 70% 50% / 0.4));font-size:17px;font-weight:900;letter-spacing:-0.01em}
[data-theme="dark"] .wz-title{background:linear-gradient(90deg,hsl(255 80% 65%),hsl(222 80% 65%),hsl(180 70% 60%),hsl(222 80% 65%),hsl(255 80% 65%));background-size:200% auto;-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;animation:rmsBrandShimmer 4s linear infinite}

/* Section cascade entrance (P15 pattern: 0/0.05/0.10/0.15s staggered) */
section[data-section="photo"]{animation:fadeInUp 0.6s var(--ease-spring) both}
section[data-section="variations"]{animation:fadeInUp 0.5s var(--ease-spring) 0.05s both}
section[data-section="extra"]{animation:fadeInUp 0.6s var(--ease-spring) 0.10s both}
section[data-section="studio"]{animation:fadeInUp 0.7s var(--ease-spring) 0.15s both}

/* Icon buttons (header) — neumorphic light, chat.php canon */
[data-theme="light"] .icon-btn,:root:not([data-theme]) .icon-btn{background:var(--surface);box-shadow:var(--shadow-card-sm);border:none;width:40px;height:40px;border-radius:50%;display:grid;place-items:center;transition:box-shadow 250ms,transform 250ms}
[data-theme="light"] .icon-btn:active,:root:not([data-theme]) .icon-btn:active{box-shadow:var(--shadow-pressed);transform:scale(0.97)}
[data-theme="light"] .icon-btn svg,:root:not([data-theme]) .icon-btn svg{stroke:var(--text);width:18px;height:18px}

/* === .wiz-mic CANON: chat-mic purple gradient + chatMicRing dual pulse === */
.wiz-mic{width:44px!important;min-width:44px;height:44px!important;border-radius:50%!important;display:grid;place-items:center;flex-shrink:0;background:linear-gradient(135deg,hsl(280 70% 55%),hsl(305 65% 55%))!important;border:none!important;box-shadow:0 4px 14px hsl(280 70% 50% / 0.5)!important;color:#fff!important;position:relative;overflow:visible!important;transition:transform 250ms,box-shadow 250ms;cursor:pointer}
.wiz-mic::before,.wiz-mic::after{content:'';position:absolute;inset:0;border-radius:50%;border:2px solid hsl(280 70% 55%);pointer-events:none;animation:chatMicRing 2s ease-out infinite}
.wiz-mic::after{animation-delay:1s}
.wiz-mic:active{transform:scale(0.94)}
.wiz-mic svg{width:14px;height:14px;stroke:#fff!important;fill:none;stroke-width:2.2;position:relative;z-index:1;filter:drop-shadow(0 1px 1px rgba(0,0,0,0.3))}
[data-theme="light"] .wiz-mic,:root:not([data-theme]) .wiz-mic{box-shadow:0 4px 14px hsl(280 70% 50% / 0.45),var(--shadow-card-sm)!important}

/* Recording state — switch to red but keep rings */
.wiz-mic.recording{background:linear-gradient(135deg,hsl(0 80% 55%),hsl(15 80% 50%))!important;animation:none!important}
.wiz-mic.recording::before,.wiz-mic.recording::after{border-color:hsl(0 80% 55%)!important;animation:chatMicRing 0.8s ease-out infinite!important}
.wiz-mic.recording::after{animation-delay:0.4s!important}

/* === .copy-btn CANON: neumorphic raised (mockup ред 201-206) === */
.copy-btn{width:44px;height:44px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;position:relative;transition:transform 150ms,box-shadow 200ms;cursor:pointer;border:none;font-family:inherit;font-size:14px}
[data-theme="light"] .copy-btn,:root:not([data-theme]) .copy-btn{background:linear-gradient(145deg,#f0f3f9,#cdd5e1);box-shadow:var(--shadow-card-sm),inset 0 1px 0 rgba(255,255,255,0.7);color:var(--accent)}
[data-theme="dark"] .copy-btn{background:linear-gradient(145deg,hsl(220 25% 11%),hsl(220 30% 6%));box-shadow:0 4px 12px hsl(220 35% 2% / 0.7),inset 0 1px 0 hsl(255 30% 30% / 0.4);border:1px solid hsl(222 12% 22%);color:hsl(255 80% 75%)}
.copy-btn:active{transform:scale(0.94)}
[data-theme="light"] .copy-btn:active,:root:not([data-theme]) .copy-btn:active{box-shadow:var(--shadow-pressed)}
.copy-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2.2}

/* === Type buttons (.s95-type-btn) CANON: neumorphic + conic active === */
[data-theme="light"] .s95-type-btn,:root:not([data-theme]) .s95-type-btn{background:var(--surface)!important;box-shadow:var(--shadow-card-sm)!important;border:none!important;color:var(--text-muted)!important;position:relative;overflow:hidden}
[data-theme="light"] .s95-type-btn:active,:root:not([data-theme]) .s95-type-btn:active{box-shadow:var(--shadow-pressed)!important}
[data-theme="light"] .s95-type-btn.active,:root:not([data-theme]) .s95-type-btn.active{background:linear-gradient(135deg,var(--accent),var(--accent-2))!important;color:#fff!important;box-shadow:0 4px 18px hsl(255 80% 50% / 0.45),inset 0 1px 0 rgba(255,255,255,0.4)!important}
[data-theme="light"] .s95-type-btn.variant.active,:root:not([data-theme]) .s95-type-btn.variant.active{background:linear-gradient(135deg,hsl(280 70% 55%),hsl(305 65% 55%))!important;box-shadow:0 4px 18px hsl(280 70% 50% / 0.5),inset 0 1px 0 rgba(255,255,255,0.4)!important}
.s95-type-btn.active::before{content:'';position:absolute;inset:0;background:conic-gradient(from 0deg,transparent 70%,rgba(255,255,255,0.45) 85%,transparent 100%);animation:conicSpin 3.5s linear infinite;pointer-events:none;border-radius:inherit}
.s95-type-btn.active svg,.s95-type-btn.active .s95-type-btn-lbl{position:relative;z-index:1}
.s95-type-btn.active svg,.s95-type-btn.variant.active svg{color:#fff!important;opacity:1!important}

/* === .fc input CANON: light = inset pressed (mockup .inp-field ред 187-189) === */
[data-theme="light"] .fc,:root:not([data-theme]) .fc{background:var(--bg-main)!important;box-shadow:var(--shadow-pressed)!important;border:none!important;color:var(--text)!important;border-radius:14px!important;font-size:15px!important;font-weight:700!important;min-height:48px!important;padding:10px 16px!important}
[data-theme="light"] .fc::placeholder,:root:not([data-theme]) .fc::placeholder{color:var(--text-faint)!important;font-weight:500}
[data-theme="light"] .fc:focus,:root:not([data-theme]) .fc:focus{box-shadow:var(--shadow-pressed),0 0 0 2px hsl(255 70% 55% / 0.4)!important}

/* === .fl label CANON: uppercase letterspaced (mockup ред 178) === */
[data-theme="light"] .fl,:root:not([data-theme]) .fl{font-size:10.5px;font-weight:800;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted)!important}

/* === Toast CANON: neumorphic surface === */
[data-theme="light"] .toast,:root:not([data-theme]) .toast{background:var(--surface)!important;color:var(--text)!important;box-shadow:var(--shadow-card)!important;border:none!important;font-weight:700}

/* === .v4-pz photo zone CANON: light = neumorphic surface, NO BORDERS === */
[data-theme="light"] .v4-pz,:root:not([data-theme]) .v4-pz{background:var(--surface)!important;box-shadow:var(--shadow-card)!important;border:none!important;border-radius:18px}
[data-theme="light"] .v4-pz-title,:root:not([data-theme]) .v4-pz-title{background:linear-gradient(135deg,var(--text),var(--accent));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
[data-theme="light"] .v4-pz-sub,:root:not([data-theme]) .v4-pz-sub{color:var(--text-muted)!important}
[data-theme="light"] .v4-pz-ic,:root:not([data-theme]) .v4-pz-ic{background:linear-gradient(135deg,hsl(280 70% 55%),hsl(305 65% 55%))!important;border:none!important;box-shadow:0 4px 14px hsl(280 70% 50% / 0.4),inset 0 1px 0 rgba(255,255,255,0.3)!important;position:relative;overflow:hidden}
[data-theme="light"] .v4-pz-ic::before,:root:not([data-theme]) .v4-pz-ic::before{content:'';position:absolute;inset:0;background:conic-gradient(from 0deg,transparent 70%,rgba(255,255,255,0.4) 85%,transparent 100%);animation:conicSpin 4s linear infinite}
[data-theme="light"] .v4-pz-ic svg,:root:not([data-theme]) .v4-pz-ic svg{stroke:#fff!important;position:relative;z-index:1}
[data-theme="light"] .v4-pz-btn.primary,:root:not([data-theme]) .v4-pz-btn.primary{background:linear-gradient(135deg,var(--accent),var(--accent-2))!important;color:#fff!important;border:none!important;box-shadow:0 4px 12px hsl(255 80% 50% / 0.4)!important}
[data-theme="light"] .v4-pz-btn.sec,:root:not([data-theme]) .v4-pz-btn.sec{background:var(--surface)!important;color:var(--text)!important;border:none!important;box-shadow:var(--shadow-card-sm)!important}
[data-theme="light"] .v4-pz-tips,:root:not([data-theme]) .v4-pz-tips{border-top:1px dashed rgba(99,102,241,0.18)!important}
[data-theme="light"] .v4-pz-tip,:root:not([data-theme]) .v4-pz-tip{color:var(--text-muted)!important;font-weight:600}
[data-theme="light"] .v4-pz-tip svg,:root:not([data-theme]) .v4-pz-tip svg{color:hsl(145 60% 45%)!important}

/* === Photo mode toggle CANON: pill pressed container === */
.photo-mode-toggle{border-radius:999px!important}
[data-theme="light"] .photo-mode-toggle,:root:not([data-theme]) .photo-mode-toggle{background:var(--surface-2)!important;box-shadow:var(--shadow-pressed)!important;border:none!important}
.pmt-opt{border-radius:999px!important}
[data-theme="light"] .pmt-opt,:root:not([data-theme]) .pmt-opt{color:var(--text-muted)!important;background:transparent!important;border:none!important}
[data-theme="light"] .pmt-opt.active,:root:not([data-theme]) .pmt-opt.active{background:linear-gradient(135deg,var(--accent),var(--accent-2))!important;color:#fff!important;box-shadow:0 4px 14px hsl(255 80% 50% / 0.4)!important}

/* === AI inline rows light canon === */
[data-theme="light"] .ai-inline-row,:root:not([data-theme]) .ai-inline-row{background:var(--surface)!important;color:var(--text)!important;border:none!important;box-shadow:var(--shadow-card-sm)!important;font-weight:700}
[data-theme="light"] .ai-inline-row:active,:root:not([data-theme]) .ai-inline-row:active{box-shadow:var(--shadow-pressed)!important}
[data-theme="light"] .ai-inline-row .air-price,:root:not([data-theme]) .ai-inline-row .air-price{color:var(--magic,var(--accent))!important}
[data-theme="dark"] .ai-inline-row .air-price{color:hsl(280 70% 75%)}

/* === Type-toggle hint amber → richer === */
[data-theme="light"] section[data-section="photo"] > h2,:root:not([data-theme]) section[data-section="photo"] > h2{background:linear-gradient(135deg,var(--text),hsl(280 70% 55%));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
[data-theme="light"] section > h2,:root:not([data-theme]) section > h2{background:linear-gradient(135deg,var(--text),var(--accent));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}

/* === Glass cards (acc-sections) — ensure light neumorphic + dark sacred === */
[data-theme="light"] section.glass,:root:not([data-theme]) section.glass{background:var(--surface)!important;box-shadow:var(--shadow-card)!important;border:none!important}

/* ╔═══════════════════════════════════════════════════════════════════╗
   ║ S148 ФАЗА 2e++REDESIGN.2 — readability fixes                       ║
   ║ Махнати emoji; light-зелени/жълти текстове → стандартни canon      ║
   ║ цветове; cam-drawer-tip light mode contrast fix.                   ║
   ╚═══════════════════════════════════════════════════════════════════╝ */

/* Type hint (заменя inline #fbbf24 amber текст) */
.wz-type-hint{text-align:center;font-size:12px;font-weight:700;letter-spacing:0.02em;margin-bottom:10px;color:var(--accent)}
[data-theme="dark"] .wz-type-hint{color:hsl(38 80% 65%)}

/* pmt-opt base styling (inline styles стрипнати) */
.pmt-opt{padding:10px 8px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px;flex:1;letter-spacing:0.01em}
.pmt-opt svg{flex-shrink:0}

/* Photo main badge — neumorphic accent (заменя inline #fbbf24 gradient + ★ emoji) */
.ph-main-badge{position:absolute;top:6px;left:6px;padding:3px 8px;border-radius:6px;background:var(--accent);color:#fff;font-size:9px;font-weight:800;letter-spacing:0.08em;box-shadow:0 2px 8px hsl(255 80% 50% / 0.4);z-index:2;text-transform:uppercase;font-family:inherit}
.photo-multi-cell.is-main .photo-multi-thumb{box-shadow:0 0 0 2px var(--accent),0 0 14px hsl(255 80% 50% / 0.35)}
.ph-main-label{margin-top:6px;font-size:10px;color:var(--accent);text-align:center;font-weight:700;letter-spacing:0.03em;text-transform:uppercase;font-family:inherit}
.ph-main-btn{margin-top:6px;width:100%;padding:7px;border-radius:8px;border:none;font-size:10px;font-weight:700;cursor:pointer;font-family:inherit;letter-spacing:0.03em;text-transform:uppercase;color:var(--accent)}
[data-theme="light"] .ph-main-btn,:root:not([data-theme]) .ph-main-btn{background:var(--surface);box-shadow:var(--shadow-card-sm)}
[data-theme="dark"] .ph-main-btn{background:hsl(220 25% 8%);border:1px solid hsl(var(--hue2) 12% 22%)}
.ph-main-btn:active{transform:scale(0.97)}
[data-theme="light"] .ph-main-btn:active,:root:not([data-theme]) .ph-main-btn:active{box-shadow:var(--shadow-pressed)}

/* Photo color confidence — canon accent (БЕЗ зелено/жълто текст per Тих) */
.photo-color-conf{font-size:8.5px;font-weight:800;letter-spacing:0.05em;flex-shrink:0;font-family:inherit;color:var(--accent)}
.photo-color-conf.warn{color:var(--text-muted)}
.photo-color-conf.detecting{color:var(--accent)}

/* v4-pz tip ✓ icons — canon accent (БЕЗ зелено per Тих) */
.v4-pz-tip svg{color:var(--accent)!important}
[data-theme="light"] .v4-pz-tip svg,:root:not([data-theme]) .v4-pz-tip svg{color:var(--accent)!important}

/* === КЛЮЧЕВ FIX: cam-drawer-tip light mode === */
/* Drawer itself (overlay) — текст vars правилни */
[data-theme="light"] #rmsPickerDrawer > div, :root:not([data-theme]) #rmsPickerDrawer > div{background:var(--surface)!important;box-shadow:var(--shadow-card)!important;border:none!important}
[data-theme="light"] #rmsPickerDrawer > div > div:first-child, :root:not([data-theme]) #rmsPickerDrawer > div > div:first-child{color:var(--text)!important}

/* Tip card в light — neumorphic surface, dark text */
[data-theme="light"] .cam-drawer-tip,:root:not([data-theme]) .cam-drawer-tip{background:linear-gradient(135deg,oklch(0.94 0.05 285 / 0.5),oklch(0.94 0.05 310 / 0.4))!important;border:none!important;box-shadow:var(--shadow-pressed)!important}
[data-theme="light"] .cam-drawer-tip-text,:root:not([data-theme]) .cam-drawer-tip-text{color:var(--text)!important}
[data-theme="light"] .cam-drawer-tip-text b,:root:not([data-theme]) .cam-drawer-tip-text b{color:var(--text)!important;font-weight:800}
[data-theme="light"] .cam-drawer-tip-app,:root:not([data-theme]) .cam-drawer-tip-app{background:var(--surface)!important;color:var(--accent)!important;border:none!important;box-shadow:var(--shadow-card-sm)!important;font-weight:700;padding:2px 8px}
[data-theme="light"] .cam-drawer-tip-or,:root:not([data-theme]) .cam-drawer-tip-or{color:var(--text-muted)!important}
[data-theme="light"] .cam-drawer-tip-icon,:root:not([data-theme]) .cam-drawer-tip-icon{filter:none!important;color:var(--accent);animation:none!important}
[data-theme="light"] .cam-drawer-tip-icon svg,:root:not([data-theme]) .cam-drawer-tip-icon svg{width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:2}
[data-theme="dark"] .cam-drawer-tip-icon svg{width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:2;color:hsl(38 80% 60%)}

/* Drawer styling — themed */
.rms-picker-card{border-radius:18px 18px 0 0;padding:18px 14px calc(18px + env(safe-area-inset-bottom,0));width:100%;max-width:480px}
[data-theme="light"] .rms-picker-card,:root:not([data-theme]) .rms-picker-card{background:var(--surface);box-shadow:var(--shadow-card);border:none}
[data-theme="dark"] .rms-picker-card{background:hsl(220 25% 5%);border:1px solid hsl(var(--hue2) 12% 18%)}
.rms-picker-title{font-size:14px;font-weight:800;text-align:center;margin-bottom:14px;letter-spacing:0.01em;color:var(--text)}
.rms-picker-actions{display:flex;gap:8px}
.rms-picker-btn{flex:1;padding:14px 8px;border-radius:14px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;display:flex;flex-direction:column;align-items:center;gap:6px;border:none;transition:transform 150ms,box-shadow 200ms}
.rms-picker-btn.primary{background:linear-gradient(135deg,var(--indigo-500,#6366f1),var(--indigo-600,#4f46e5));color:#fff;box-shadow:0 4px 14px hsl(var(--hue1) 70% 50% / 0.4)}
.rms-picker-btn.sec{color:var(--text)}
[data-theme="light"] .rms-picker-btn.sec,:root:not([data-theme]) .rms-picker-btn.sec{background:var(--surface);box-shadow:var(--shadow-card-sm)}
[data-theme="dark"] .rms-picker-btn.sec{background:hsl(220 25% 8%);border:1px solid hsl(var(--hue2) 12% 20%)}
.rms-picker-btn:active{transform:scale(0.97)}
[data-theme="light"] .rms-picker-btn.sec:active,:root:not([data-theme]) .rms-picker-btn.sec:active{box-shadow:var(--shadow-pressed)}
.rms-picker-cancel{width:100%;margin-top:10px;padding:11px;border-radius:12px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit;color:var(--text-muted);background:transparent;border:none}
[data-theme="light"] .rms-picker-cancel:active,:root:not([data-theme]) .rms-picker-cancel:active{box-shadow:var(--shadow-pressed)}

/* ╔═══════════════════════════════════════════════════════════════════╗
   ║ S148 ФАЗА 2g — Цена (cost + retail) + AI markup + live margin    ║
   ╚═══════════════════════════════════════════════════════════════════╝
*/

/* .req-star — required field asterisk (canon) */
.req-star{color:var(--danger,var(--accent));font-weight:900;font-size:13px;margin-left:2px}
[data-theme="light"] .req-star,:root:not([data-theme]) .req-star{color:oklch(0.65 0.22 25)}

/* .fl .hint — secondary label hint (mockup ред 162) */
.fl .hint{font-weight:600;text-transform:none;letter-spacing:0;color:var(--text-faint);font-size:11px;margin-left:4px}

/* Live margin display — Montserrat + canon accent (cost field moved to next section,
   но defs остават за reuse в Phase 4 / Section 3). */
.wz-margin-display{margin-top:8px;padding:8px 12px;border-radius:10px;font-size:12px;font-weight:700;font-family:inherit;letter-spacing:0.01em;display:flex;align-items:center;gap:6px;color:var(--text)}
.wz-margin-display b{font-weight:800;font-size:14px;color:var(--accent)}
[data-theme="light"] .wz-margin-display,:root:not([data-theme]) .wz-margin-display{background:var(--surface);box-shadow:var(--shadow-pressed)}
[data-theme="dark"] .wz-margin-display{background:hsl(220 25% 6%);border:1px solid hsl(var(--hue2) 12% 18%)}
.wz-margin-display.loss b{color:var(--danger,oklch(0.65 0.22 25))}

/* AI markup row — appears under cost field when cost > 0 */
.ai-markup-row{margin-top:10px;border-radius:14px;overflow:hidden}
.ai-markup-info{display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:14px;font-family:inherit}
[data-theme="light"] .ai-markup-row,:root:not([data-theme]) .ai-markup-row{background:linear-gradient(135deg,oklch(0.94 0.05 285 / 0.6),oklch(0.94 0.05 310 / 0.5));box-shadow:var(--shadow-card-sm);border:none}
[data-theme="dark"] .ai-markup-row{background:linear-gradient(135deg,hsl(280 30% 12% / 0.6),hsl(255 30% 12% / 0.5));border:1px solid hsl(280 50% 28% / 0.4)}
.ai-markup-icon{width:34px;height:34px;border-radius:50%;display:grid;place-items:center;flex-shrink:0;background:linear-gradient(135deg,hsl(280 70% 55%),hsl(305 65% 55%));box-shadow:0 4px 12px hsl(280 70% 50% / 0.4);position:relative;overflow:hidden}
.ai-markup-icon::before{content:'';position:absolute;inset:0;background:conic-gradient(from 0deg,transparent 70%,rgba(255,255,255,0.4) 85%,transparent 100%);animation:conicSpin 4s linear infinite}
.ai-markup-icon svg{width:16px;height:16px;stroke:#fff;fill:none;stroke-width:2;position:relative;z-index:1}
.ai-markup-text{flex:1;font-size:12.5px;color:var(--text);line-height:1.4;font-weight:600}
.ai-markup-value{font-size:14px;font-weight:800;color:var(--magic,var(--accent));font-family:inherit;margin:0 2px}
.ai-markup-meta{font-size:10.5px;color:var(--text-muted);font-weight:600;margin-left:6px;font-family:inherit}
.ai-markup-apply{padding:8px 14px;border-radius:10px;font-size:12px;font-weight:800;cursor:pointer;font-family:inherit;border:none;background:linear-gradient(135deg,var(--accent),var(--accent-2));color:#fff;box-shadow:0 4px 12px hsl(255 80% 50% / 0.4);letter-spacing:0.02em;transition:transform 150ms,box-shadow 200ms;flex-shrink:0}
.ai-markup-apply:active{transform:scale(0.96);box-shadow:0 2px 6px hsl(255 80% 50% / 0.3)}
.ai-markup-loading{font-size:12px;color:var(--text-muted);font-weight:600;padding:10px;display:flex;align-items:center;gap:8px}
.ai-markup-loading::before{content:'';width:14px;height:14px;border:2px solid var(--accent);border-top-color:transparent;border-radius:50%;animation:conicSpin 0.8s linear infinite}

/* ╔═══════════════════════════════════════════════════════════════════╗
   ║ S148 ФАЗА 2h.2 — Количество — sacred-compact inline stepper        ║
   ║ User feedback 2026-05-17: existing wizard stepper "100x по-добре". ║
   ║ Pattern 1:1 от products.php 12575-12604 (compact inline +/-).      ║
   ║ Canonical Montserrat font + canon accent colors (БЕЗ зелено/жълто).║
   ╚═══════════════════════════════════════════════════════════════════╝
*/
.wz-qty-row{display:flex;align-items:center;gap:6px}
.wz-qty-stepper{display:flex;align-items:center;border-radius:14px;height:48px;overflow:hidden;flex:1;min-width:0;font-family:inherit}
[data-theme="light"] .wz-qty-stepper,:root:not([data-theme]) .wz-qty-stepper{background:var(--bg-main);box-shadow:var(--shadow-pressed);border:none}
[data-theme="dark"] .wz-qty-stepper{background:hsl(220 25% 4%);border:1px solid hsl(255 12% 22%)}

.wz-qty-btn{width:48px;height:48px;background:transparent;border:none;font-size:22px;font-weight:800;font-family:inherit;cursor:pointer;display:grid;place-items:center;flex-shrink:0;transition:transform 150ms,background 200ms;color:var(--accent);line-height:1}
.wz-qty-btn:active{transform:scale(0.92)}
[data-theme="light"] .wz-qty-btn:active,:root:not([data-theme]) .wz-qty-btn:active{background:rgba(99,102,241,0.10)}
[data-theme="dark"] .wz-qty-btn:active{background:hsl(255 25% 10%)}

.wz-qty-input{flex:1;min-width:0;height:100%;background:transparent;border:none;color:var(--text);font-size:18px;font-weight:800;text-align:center;outline:none;font-family:inherit;padding:0 4px;letter-spacing:-0.01em;-moz-appearance:textfield}
.wz-qty-input::-webkit-outer-spin-button,.wz-qty-input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.wz-qty-input::placeholder{color:var(--text-faint);font-weight:600}

/* Min qty stepper — НЯКА визуална разлика, без amber/yellow (per Тих "забранявам зелен/жълт шрифт") */
.wz-qty-stepper.is-min .wz-qty-btn{color:var(--text-muted)}

/* Hint под min qty — Montserrat, text-muted (без amber/yellow) */
.wz-min-hint{margin-top:6px;font-size:11.5px;font-weight:600;color:var(--text-muted);line-height:1.4;font-family:inherit;letter-spacing:0.01em}

/* Variant qty placeholder — accent-tinted (без green/amber) */
.wz-variant-qty-note{margin:0;padding:14px 16px;border-radius:14px;font-size:12.5px;line-height:1.5;font-weight:600;display:flex;align-items:flex-start;gap:10px;font-family:inherit}
[data-theme="light"] .wz-variant-qty-note,:root:not([data-theme]) .wz-variant-qty-note{background:linear-gradient(135deg,oklch(0.94 0.05 285 / 0.5),oklch(0.94 0.05 310 / 0.4));box-shadow:var(--shadow-pressed);color:var(--text)}
[data-theme="dark"] .wz-variant-qty-note{background:linear-gradient(135deg,hsl(280 30% 12% / 0.5),hsl(255 30% 12% / 0.4));border:1px solid hsl(255 12% 20%);color:var(--text-muted)}
.wz-variant-qty-note svg{width:18px;height:18px;flex-shrink:0;stroke:var(--accent);fill:none;stroke-width:2;margin-top:1px}
.wz-variant-qty-note b{color:var(--text);font-weight:800}

/* ╔═══════════════════════════════════════════════════════════════════╗
   ║ S148 ФАЗА 2i — sacred behaviour port (dupe check + AI hint + bulk) ║
   ║ User: "Виж абсолютно всички логики ... ПРЕНЕСЕМ ВСИЧКИТЕ"          ║
   ║ Source: products.php 7530-7592, 12231-12244                        ║
   ║ Canon override: amber/yellow в sacred banner → accent (Тих rule).  ║
   ╚═══════════════════════════════════════════════════════════════════╝
*/

/* Duplicate name banner (sacred logic, canon visuals) */
.wiz-dupe-banner{margin-top:8px;padding:10px 12px;border-radius:12px;font-family:inherit}
[data-theme="light"] .wiz-dupe-banner,:root:not([data-theme]) .wiz-dupe-banner{background:linear-gradient(135deg,oklch(0.94 0.05 285 / 0.6),oklch(0.94 0.05 310 / 0.45));box-shadow:var(--shadow-card-sm);color:var(--text)}
[data-theme="dark"] .wiz-dupe-banner{background:linear-gradient(135deg,hsl(280 30% 12% / 0.6),hsl(255 30% 12% / 0.5));border:1px solid hsl(255 12% 22%);color:var(--text)}
.wiz-dupe-banner-row{display:flex;align-items:flex-start;gap:8px;font-size:11.5px;line-height:1.45}
.wiz-dupe-banner svg.dupe-ic{width:16px;height:16px;flex-shrink:0;margin-top:2px;stroke:var(--accent);fill:none;stroke-width:2}
.wiz-dupe-banner b{color:var(--text);font-weight:800}
.wiz-dupe-pct{color:var(--accent);font-weight:700;font-family:inherit}
.wiz-dupe-actions{display:flex;gap:6px;margin-top:8px}
.wiz-dupe-actions button{flex:1;padding:8px;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer;font-family:inherit;border:none;transition:transform 150ms,box-shadow 200ms}
.wiz-dupe-actions button.primary{background:linear-gradient(135deg,var(--accent),var(--accent-2));color:#fff;box-shadow:0 3px 10px hsl(255 80% 50% / 0.35)}
.wiz-dupe-actions button.sec{color:var(--text-muted)}
[data-theme="light"] .wiz-dupe-actions button.sec,:root:not([data-theme]) .wiz-dupe-actions button.sec{background:var(--surface);box-shadow:var(--shadow-card-sm)}
[data-theme="dark"] .wiz-dupe-actions button.sec{background:hsl(220 25% 8%);border:1px solid hsl(255 12% 22%)}
.wiz-dupe-actions button:active{transform:scale(0.97)}

/* AI auto-fill hint badge (под поле когато AI попълни стойност) */
.wiz-ai-hint{margin-top:6px;padding:5px 10px;border-radius:8px;font-size:10.5px;font-weight:700;display:inline-flex;align-items:center;gap:5px;letter-spacing:0.02em;cursor:pointer;font-family:inherit}
[data-theme="light"] .wiz-ai-hint,:root:not([data-theme]) .wiz-ai-hint{background:linear-gradient(135deg,oklch(0.93 0.06 285 / 0.7),oklch(0.93 0.06 310 / 0.6));color:var(--accent);box-shadow:var(--shadow-card-sm)}
[data-theme="dark"] .wiz-ai-hint{background:linear-gradient(135deg,hsl(280 50% 18% / 0.6),hsl(255 50% 18% / 0.5));color:hsl(280 70% 75%);border:1px solid hsl(280 50% 30% / 0.4)}
.wiz-ai-hint svg{width:11px;height:11px;stroke:currentColor;fill:none;stroke-width:2}

/* "Като предния" bulk copy button — neumorphic + accent text */
.wz-copy-prev-btn{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:11px 14px;border-radius:14px;font-size:12.5px;font-weight:700;cursor:pointer;font-family:inherit;letter-spacing:0.01em;color:var(--accent);border:none;margin-bottom:12px;transition:transform 150ms,box-shadow 200ms}
.wz-copy-prev-btn[disabled]{cursor:not-allowed;color:var(--text-faint)}
[data-theme="light"] .wz-copy-prev-btn,:root:not([data-theme]) .wz-copy-prev-btn{background:var(--surface);box-shadow:var(--shadow-card-sm)}
[data-theme="light"] .wz-copy-prev-btn:active,:root:not([data-theme]) .wz-copy-prev-btn:active{box-shadow:var(--shadow-pressed)}
[data-theme="dark"] .wz-copy-prev-btn{background:linear-gradient(180deg,hsl(255 30% 12%),hsl(255 30% 8%));border:1px solid hsl(255 50% 28% / 0.4)}
.wz-copy-prev-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;flex-shrink:0}
   ║ "Намали размера, махни анимациите, при натиск само пулсираща       ║
   ║  червена точка (както е в сегашния)."                              ║
   ║ Mic: 44→38px, без chatMicRing dual rings, без micRecPulse halo,    ║
   ║ recording state = ONLY .recording::before (8×8 red dot pulse).     ║
   ║ Copy: 44→38px (същия размер като mic — балансиран ред).            ║
   ╚═══════════════════════════════════════════════════════════════════╝
*/
.wiz-mic{width:30px!important;min-width:30px;height:30px!important;border-radius:50%!important;background:linear-gradient(135deg,hsl(280 70% 55%),hsl(305 65% 55%))!important;border:none!important;box-shadow:0 2px 6px hsl(280 70% 50% / 0.4)!important;color:#fff!important;position:relative;overflow:visible!important;animation:none!important;transition:transform 150ms;cursor:pointer;display:grid;place-items:center;flex-shrink:0;padding:0}
.wiz-mic::before,.wiz-mic::after{content:none!important;animation:none!important;border:none!important;background:none!important}
.wiz-mic:active{transform:scale(0.92)}
.wiz-mic svg{width:11px!important;height:11px!important;stroke:#fff!important;fill:none;stroke-width:2.4;position:relative;z-index:1;filter:drop-shadow(0 1px 1px rgba(0,0,0,0.3))}
[data-theme="light"] .wiz-mic,:root:not([data-theme]) .wiz-mic{box-shadow:0 2px 6px hsl(280 70% 50% / 0.4),var(--shadow-card-sm)!important}

/* Recording state — red gradient + ONLY pulsing red dot (БЕЗ rings, БЕЗ REC label, БЕЗ halo) */
.wiz-mic.recording{background:linear-gradient(135deg,hsl(0 80% 55%),hsl(15 75% 50%))!important;animation:none!important;box-shadow:0 2px 6px hsl(0 80% 50% / 0.45)!important;border:none!important}
.wiz-mic.recording::before{content:''!important;position:absolute;top:-2px;right:-2px;width:7px;height:7px;border-radius:50%;background:#ef4444!important;box-shadow:0 0 5px #ef4444,0 0 10px rgba(239,68,68,.55)!important;animation:micRecDot .6s infinite!important;border:none!important}
.wiz-mic.recording::after{content:none!important;animation:none!important}

/* Active-field highlight (mic вътре в .fg.wiz-active) — purple variant без animation */
.fg.wiz-active .wiz-mic{background:linear-gradient(135deg,hsl(255 75% 60%),hsl(280 65% 55%))!important;animation:none!important}

/* Copy button — same size as mic (30×30) */
.copy-btn{width:30px!important;height:30px!important;font-size:11px;padding:0}
.copy-btn svg{width:11px!important;height:11px!important}
  </style>
</head>
<body>

  <!-- AURORA 4 blobs — canon DESIGN_SYSTEM_v4.0_BICHROMATIC §4.3 + mockup ред 30-36 -->
  <div class="aurora">
    <div class="aurora-blob"></div>
    <div class="aurora-blob"></div>
    <div class="aurora-blob"></div>
    <div class="aurora-blob"></div>
  </div>

  <div class="shell">

    <!-- ═══ HEADER ═══ -->
    <header class="wz-header">
      <button class="icon-btn" aria-label="Назад"><!-- TODO Фаза 2: back nav --></button>
      <span class="wz-title">Добави артикул</span>
      <button class="kp-pill" aria-label="Като предния"><!-- TODO Фаза 2: bulk mode --></button>
      <button class="icon-btn" id="themeToggle" onclick="toggleTheme()" aria-label="Тема">
        <svg id="themeIconMoon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <svg id="themeIconSun" viewBox="0 0 24 24" style="display:none"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/></svg>
      </button>
    </header>

    <!-- ═══ MAIN: 4 акордеона ═══ -->
    <!-- id="wizBody" е изискване за sacred _wizClearHighlights / wizMarkDone / wizHighlightNext
         (querySelectorAll('#wizBody .fg ...') ще резолват тук). -->
    <main class="wz-main" id="wizBody">

      <!-- Секция 1 — Снимка + Основно (qm = magic purple) -->
      <section data-section="photo" class="glass qm">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <h2>Снимка + Основно</h2>
        <!-- S148 ФАЗА 2e: photo upload zone (1:1 sacred от products.php 12391-12457).
             Полета име/цена/количество идват в sub-steps 2f/2g/2h. -->
        <div id="wizSection1Inner" style="padding:0 14px 14px"></div>
      </section>

      <!-- Секция 2 — Вариации (q3 = green) — STOP, питай Тих преди ФАЗА 3 -->
      <section data-section="variations" class="glass q3">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <h2>Вариации</h2>
        <div class="ph">TODO ФАЗА 3 — STOP, чакам решение от Тих (A iframe / B copy / C нов matrix)</div>
      </section>

      <!-- Секция 3 — Допълнителни (qd = default bi-chromatic) -->
      <section data-section="extra" class="glass qd">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <h2>Допълнителни</h2>
        <div class="ph">TODO ФАЗА 4 — пол, сезон, марка, описание</div>
      </section>

      <!-- Секция 4 — AI Studio (q5 = amber) -->
      <section data-section="studio" class="glass q5">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <h2>AI Studio</h2>
        <div class="ph">TODO ФАЗА 4 — snapshot history, retry, manual override</div>
      </section>

    </main>

  </div>

  <!-- S148 ФАЗА 2e — sacred toast container + hidden file inputs (1:1 от products.php 5446, 6198, 6223) -->
  <div class="toast-c" id="toasts"></div>
  <input type="file" id="photoInput" accept="image/*" capture="environment" style="display:none">
  <input type="file" id="filePickerInput" accept="image/*,*/*" style="display:none">

  <!-- ═══ FOOTER: Undo / Print / CSV / Запази ═══ -->
  <footer class="wz-foot">
    <button aria-label="Undo"><!-- TODO Фаза 2 -->Undo</button>
    <button aria-label="Print"><!-- TODO Фаза 4 -->Print</button>
    <button aria-label="CSV"><!-- TODO Фаза 4 -->CSV</button>
    <button class="save-btn" aria-label="Запази"><!-- TODO Фаза 2 -->Запази</button>
  </footer>

  <!-- S148 ФАЗА 2a deliverable — sacred JS parser (audio recorder shell + BG price parser). -->
  <script src="js/wizard-parser.js"></script>
  <script src="js/capacitor-printer.js"></script>
  <script>
  /* ═══ S148 ФАЗА 2e — sacred wizard state + photo block (1:1 от products.php) ═══

     Минимум S object (по нареждане на Тих 2026-05-17):
       var S = { wizData: {}, wizStep: 0, wizType: null, wizBulkMode: false };

     1:1 копия от products.php (без модификации):
       6329           esc(s)
       6335-6343      showToast(msg, type)
       8995           WIZ_AI_INLINE_PRICES
       8997-9005      _wizAIInlineRows()
       9410-9416      wizSetPhotoMode(mode)
       9640-9648      wizPhotoMultiRemove(idx)
       9650-9655      wizPhotoSetColor(idx, value)
       12223-12229    wizSetMainPhoto(idx)
       12391-12457    renderWizSection1Photo() = photoBlock construction (single + multi)
       12744-12781    file input change handlers (filePickerInput + photoInput)

     STUBS (camera loop + AI inline pull в deep dependencies; ще се копират в
     следваща sub-step за да не разширят 2e scope):
       wizPhotoMultiPick, wizAIInlineBgRemove, wizAIInlineSeoDesc, wizAIInlineMagic
  */

  var WIZ_AI_INLINE_PRICES = { bg: 0.05, desc: 0.02, magic: 0.50 };
  var S = { wizData: {}, wizStep: 0, wizType: null, wizBulkMode: false };

  function esc(s){if(!s)return'';const d=document.createElement('div');d.textContent=s;return d.innerHTML}

  function showToast(msg, type=''){
    const c=document.getElementById('toasts');
    if(!c)return;
    const e=document.createElement('div');
    e.className='toast '+(type||'');
    e.innerHTML=esc(msg);
    c.appendChild(e);
    requestAnimationFrame(()=>e.classList.add('show'));
    setTimeout(()=>{e.classList.remove('show');setTimeout(()=>e.remove(),300)},3000);
  }

  function _wizAIInlineRows() {
    if (!S.wizData._photoDataUrl) return '';
    var p = WIZ_AI_INLINE_PRICES;
    return '<div class="ai-inline-rows q-magic">' +
        '<button type="button" class="ai-inline-row" id="aiInlBg" onclick="wizAIInlineBgRemove()"><span class="air-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="m9 11 3-3 3 3M12 8v8"/></svg></span><span class="air-lbl">Махни фон</span><span class="air-price">' + p.bg.toFixed(2) + ' €</span></button>' +
        '<button type="button" class="ai-inline-row" id="aiInlSeo" onclick="wizAIInlineSeoDesc()"><span class="air-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="13" y2="17"/></svg></span><span class="air-lbl">SEO описание</span><span class="air-price">' + p.desc.toFixed(2) + ' €</span></button>' +
        '<button type="button" class="ai-inline-row" id="aiInlMagic" onclick="wizAIInlineMagic()"><span class="air-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3M12 18v3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M3 12h3M18 12h3M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/></svg></span><span class="air-lbl">AI магия</span><span class="air-price">' + p.magic.toFixed(2) + ' €</span></button>' +
        '</div>';
  }

  function wizSetPhotoMode(mode) {
    if (mode !== 'single' && mode !== 'multi') return;
    S.wizData._photoMode = mode;
    try { localStorage.setItem('_rms_photoMode', mode); } catch(e) {}
    if (navigator.vibrate) navigator.vibrate(8);
    renderWizard();
  }

  function wizSetMainPhoto(idx){
    if(!Array.isArray(S.wizData._photos))return;
    S.wizData._photos.forEach(function(p,i){p.is_main=(i===idx)});
    renderWizard();
    showToast('Главна снимка избрана','success');
    if(navigator.vibrate)navigator.vibrate(8);
  }

  function wizPhotoMultiRemove(idx) {
    if (!Array.isArray(S.wizData._photos)) return;
    if (idx < 0 || idx >= S.wizData._photos.length) return;
    if (!confirm('Премахни снимка №' + (idx+1) + '?')) return;
    S.wizData._photos.splice(idx, 1);
    S.wizData._aiColorsApplied = false;
    renderWizard();
  }

  function wizPhotoSetColor(idx, value) {
    if (!Array.isArray(S.wizData._photos)) return;
    if (idx < 0 || idx >= S.wizData._photos.length) return;
    S.wizData._photos[idx].ai_color = (value || '').trim();
    S.wizData._aiColorsApplied = false;
  }

  /* ═══ S148 ФАЗА 2e++b — camera loop infra (1:1 sacred от p.php) ═══
       9575-9590  _downscaleDataUrl(dataUrl, maxDim, quality)
       9593-9606  wizShowAIWorking(count)
       9608-9611  wizHideAIWorking()
     Тези са самостоятелни utilities — без deps извън дом + Image API.
  */

  function _downscaleDataUrl(dataUrl, maxDim, quality) {
    return new Promise(function(resolve) {
        var img = new Image();
        img.onload = function() {
            var w = img.width, h = img.height;
            var scale = Math.min(1, maxDim / Math.max(w, h));
            var dw = Math.round(w * scale), dh = Math.round(h * scale);
            var c = document.createElement('canvas');
            c.width = dw; c.height = dh;
            c.getContext('2d').drawImage(img, 0, 0, dw, dh);
            resolve(c.toDataURL('image/jpeg', quality));
        };
        img.onerror = function(){ resolve(dataUrl); };
        img.src = dataUrl;
    });
  }

  function wizShowAIWorking(count) {
    if (document.getElementById('rmsAIWorking')) document.getElementById('rmsAIWorking').remove();
    var ov = document.createElement('div');
    ov.id = 'rmsAIWorking';
    ov.className = 'ai-working-ov';
    ov.innerHTML =
        '<div class="ai-working-card">' +
            '<div class="ai-working-orb"><div></div><div></div><div></div></div>' +
            '<div class="ai-working-title">AI анализира</div>' +
            '<div class="ai-working-msg">Разпознавам цветовете на ' + count + ' ' + (count === 1 ? 'снимка' : 'снимки') + '...</div>' +
            '<div class="ai-working-hint">Обикновено отнема 3-8 секунди</div>' +
        '</div>';
    document.body.appendChild(ov);
  }

  function wizHideAIWorking() {
    var ov = document.getElementById('rmsAIWorking');
    if (ov) ov.remove();
  }

  /* ═══ S148 ФАЗА 2e++c — multi-photo add/remove + camera loop (1:1 sacred от p.php) ═══
       9467           var _camPending
       9418-9442      wizPhotoMultiPick — picker drawer (Снимай / Галерия + tip)
       9444-9456      wizPhotoMultiGalleryPick — multi <input type="file"> избор
       9469-9484      wizPhotoCameraLoop — full-screen camera UX init
       9486-9506      wizCamRenderEmpty — empty state UI
       9508-9512      wizCamShoot — trigger native camera
       9514-9540      wizCamLoopOnFile — receive captured photo + downscale
       9542-9545      wizCamRetake
       9547-9561      wizCamAccept — push в S.wizData._photos + continue/finish
       9563-9566      wizCamLoopFinish — close + detect colors
       9568-9573      wizCamLoopClose
       9613-9638      wizPhotoMultiAdd — batch add от galleries
     wizPhotoDetectColors / _markPhotosFailed остават stubs до 2e++d.
  */

  var _camPending = null;

  function wizPhotoMultiPick() {
    if (document.getElementById('rmsPickerDrawer')) {
        document.getElementById('rmsPickerDrawer').remove();
    }
    var dr = document.createElement('div');
    dr.id = 'rmsPickerDrawer';
    dr.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(8px);z-index:9998;display:flex;align-items:flex-end;justify-content:center';
    dr.onclick = function(e) { if (e.target === dr) dr.remove(); };
    dr.innerHTML = '<div class="rms-picker-card">' +
        '<div class="rms-picker-title">Добави снимка</div>' +
        '<div class="cam-drawer-tip">' +
            '<div class="cam-drawer-tip-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="13"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg></div>' +
            '<div class="cam-drawer-tip-text">' +
                '<b>Ако се отвори селфи камерата:</b> излез, обърни я веднъж в нормалната <span class="cam-drawer-tip-app">Camera</span> и Самсунг ще запомни задната завинаги. ' +
                '<span class="cam-drawer-tip-or">Иначе — обръщай я в Camera всеки път.</span>' +
            '</div>' +
        '</div>' +
        '<div class="rms-picker-actions">' +
            '<button type="button" class="rms-picker-btn primary" onclick="document.getElementById(\'rmsPickerDrawer\').remove();wizPhotoCameraLoop()"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>Снимай</button>' +
            '<button type="button" class="rms-picker-btn sec" onclick="document.getElementById(\'rmsPickerDrawer\').remove();wizPhotoMultiGalleryPick()"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>Галерия</button>' +
        '</div>' +
        '<button type="button" class="rms-picker-cancel" onclick="document.getElementById(\'rmsPickerDrawer\').remove()">Откажи</button>' +
    '</div>';
    document.body.appendChild(dr);
  }

  function wizPhotoMultiGalleryPick() {
    if (document.getElementById('_rmsGalPicker')) document.getElementById('_rmsGalPicker').remove();
    var inp = document.createElement('input');
    inp.type = 'file'; inp.id = '_rmsGalPicker'; inp.accept = 'image/*'; inp.multiple = true;
    inp.style.display = 'none';
    inp.onchange = async function(e) {
        var files = Array.from(e.target.files || []);
        await wizPhotoMultiAdd(files);
        inp.remove();
    };
    document.body.appendChild(inp);
    inp.click();
  }

  function wizPhotoCameraLoop() {
    if (document.getElementById('rmsCamLoop')) document.getElementById('rmsCamLoop').remove();
    _camPending = null;
    var ov = document.createElement('div');
    ov.id = 'rmsCamLoop'; ov.className = 'cam-loop-ov show';
    var photoCount = (Array.isArray(S.wizData._photos) ? S.wizData._photos.length : 0) + 1;
    ov.innerHTML =
        '<div class="cam-loop-counter" id="rmsCamCounter">Снимай цвят ' + photoCount + '</div>' +
        '<div id="rmsCamStage" class="cam-loop-stage"></div>' +
        '<input type="file" id="rmsCamInput" accept="image/*" capture="environment" style="position:absolute;width:1px;height:1px;opacity:0;pointer-events:none">' +
        '<div class="cam-loop-controls" id="rmsCamControls"></div>';
    document.body.appendChild(ov);
    document.getElementById('rmsCamInput').addEventListener('change', wizCamLoopOnFile);
    wizCamRenderEmpty();
    wizCamShoot();
  }

  function wizCamRenderEmpty() {
    var stage = document.getElementById('rmsCamStage');
    var taken = (Array.isArray(S.wizData._photos) ? S.wizData._photos.length : 0);
    var hint = taken
        ? 'Снимка ' + taken + ' добавена. Tap кръглия бутон за следващата.'
        : 'Tap кръглия бутон, за да отвориш камерата на телефона.';
    if (stage) {
        stage.innerHTML =
            '<div class="cam-loop-empty">' +
                '<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>' +
                '<div class="cam-loop-empty-msg">' + hint + '</div>' +
            '</div>';
    }
    var ctl = document.getElementById('rmsCamControls');
    if (ctl) {
        ctl.innerHTML =
            '<button type="button" class="cam-loop-btn cancel" onclick="wizCamLoopClose()"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>' +
            '<button type="button" class="cam-loop-btn shoot" onclick="wizCamShoot()"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="9"/></svg></button>' +
            (taken ? '<button type="button" class="cam-loop-btn done" onclick="wizCamLoopFinish()"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Готово</button>' : '');
    }
  }

  function wizCamShoot() {
    var inp = document.getElementById('rmsCamInput');
    if (inp) inp.click();
  }

  function wizCamLoopOnFile(e) {
    var f = e.target.files && e.target.files[0];
    e.target.value = '';
    if (!f) {
        wizCamRenderEmpty();
        return;
    }
    var fr = new FileReader();
    fr.onload = async function() {
        var dataUrl = fr.result;
        try { dataUrl = await _downscaleDataUrl(dataUrl, 1000, 0.80); } catch(err) { console.warn('downscale err:', err); }
        _camPending = dataUrl;
        var stage = document.getElementById('rmsCamStage');
        if (stage) stage.innerHTML = '<img class="cam-loop-preview" src="' + dataUrl + '" alt="">';
        var ctl = document.getElementById('rmsCamControls');
        if (ctl) {
            ctl.innerHTML =
                '<button type="button" class="cam-loop-btn retake" onclick="wizCamRetake()"><svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/></svg>Нова снимка</button>' +
                '<button type="button" class="cam-loop-btn next" onclick="wizCamAccept(true)">Следваща запис<svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></button>' +
                '<button type="button" class="cam-loop-btn done" onclick="wizCamAccept(false)"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>Готово</button>';
        }
        if (navigator.vibrate) navigator.vibrate(6);
    };
    fr.readAsDataURL(f);
  }

  function wizCamRetake() {
    _camPending = null;
    wizCamShoot();
  }

  async function wizCamAccept(continueShooting) {
    if (!_camPending) return;
    if (!Array.isArray(S.wizData._photos)) S.wizData._photos = [];
    S.wizData._photos.push({ dataUrl: _camPending, file: null, ai_color: null, ai_hex: null, ai_confidence: null });
    _camPending = null;
    S.wizData._aiColorsApplied = false;
    if (continueShooting && S.wizData._photos.length < 30) {
        var ctr = document.getElementById('rmsCamCounter');
        if (ctr) ctr.textContent = 'Снимай цвят ' + (S.wizData._photos.length + 1);
        wizCamShoot();
    } else {
        wizCamLoopClose();
        wizPhotoDetectColors();
    }
  }

  function wizCamLoopFinish() {
    wizCamLoopClose();
    wizPhotoDetectColors();
  }

  function wizCamLoopClose() {
    _camPending = null;
    var ov = document.getElementById('rmsCamLoop');
    if (ov) ov.remove();
    if (typeof renderWizard === 'function') renderWizard();
  }

  async function wizPhotoMultiAdd(files) {
    if (!Array.isArray(S.wizData._photos)) S.wizData._photos = [];
    var room = 30 - S.wizData._photos.length;
    if (room <= 0) {
        if (typeof showToast === 'function') showToast('Максимум 30 снимки', 'error');
        return;
    }
    var accepted = files.slice(0, room);
    for (var i = 0; i < accepted.length; i++) {
        var file = accepted[i];
        try {
            var dataUrl = await new Promise(function(res, rej) {
                var fr = new FileReader();
                fr.onload = function() { res(fr.result); };
                fr.onerror = rej;
                fr.readAsDataURL(file);
            });
            dataUrl = await _downscaleDataUrl(dataUrl, 1000, 0.80);
            S.wizData._photos.push({ dataUrl: dataUrl, file: null, ai_color: null, ai_hex: null, ai_confidence: null });
        } catch (err) { console.warn('[S82.COLOR.7] Read err:', err); }
    }
    S.wizData._aiColorsApplied = false;
    renderWizard();
    wizPhotoDetectColors();
  }

  /* ═══ S148 ФАЗА 2e++d — color detect (1:1 sacred от p.php 9657-9749) ═══
     Auto-triggers след wizPhotoMultiAdd и wizCamLoopFinish.
     POST към `/ai-color-detect.php?multi=1` (sacred endpoint, direct call).
     Multi-part body: image_0, image_1, ... + count. Response: results[] с
     { idx, color/color_bg/name, hex, confidence } per photo.
     На грешка → _markPhotosFailed (confidence=0 stops AI spinner per photo).
  */

  function _markPhotosFailed(indices) {
    indices.forEach(function(idx){
        if (!S.wizData._photos[idx]) return;
        S.wizData._photos[idx].ai_color = '';
        S.wizData._photos[idx].ai_hex = '#666';
        S.wizData._photos[idx].ai_confidence = 0;
    });
  }

  async function wizPhotoDetectColors() {
    if (!Array.isArray(S.wizData._photos) || !S.wizData._photos.length) return;
    var todo = [];
    var todoIndices = [];
    S.wizData._photos.forEach(function(p, i) {
        if (p.ai_confidence === null || p.ai_confidence === undefined) {
            todo.push(p);
            todoIndices.push(i);
        }
    });
    if (!todo.length) return;
    if (typeof wizShowAIWorking === 'function') wizShowAIWorking(todo.length);
    var fd = new FormData();
    var totalKB = 0;
    todo.forEach(function(p, i) {
        var arr = p.dataUrl.split(',');
        var mime = (arr[0].match(/:(.*?);/) || [])[1] || 'image/jpeg';
        var bstr = atob(arr[1]);
        var n = bstr.length;
        totalKB += n / 1024;
        var u8 = new Uint8Array(n);
        while (n--) u8[n] = bstr.charCodeAt(n);
        fd.append('image_' + i, new Blob([u8], { type: mime }), 'photo_' + i + '.jpg');
    });
    fd.append('count', String(todo.length));
    console.log('[S82.COLOR.7] AI detect: posting', todo.length, 'photos, total ~' + Math.round(totalKB) + ' KB');
    var r, j;
    try {
        r = await fetch('ai-color-detect.php?multi=1', { method: 'POST', body: fd, credentials: 'same-origin' });
    } catch (err) {
        console.error('[S82.COLOR.7] AI fetch err:', err);
        if (typeof wizHideAIWorking === 'function') wizHideAIWorking();
        if (typeof showToast === 'function') showToast('AI: мрежова грешка', 'error');
        _markPhotosFailed(todoIndices);
        renderWizard();
        return;
    }
    try { j = await r.json(); } catch(e) { j = null; }
    console.log('[S82.COLOR.7] AI response status=' + r.status, j);
    if (!r.ok) {
        if (typeof wizHideAIWorking === 'function') wizHideAIWorking();
        var reason = (j && j.reason) || ('HTTP ' + r.status + (r.status === 413 || r.status === 400 ? ' — снимките са твърде големи' : ''));
        if (typeof showToast === 'function') showToast('AI: ' + reason, 'error');
        _markPhotosFailed(todoIndices);
        renderWizard();
        return;
    }
    var results = (j && (j.results || j.colors)) || null;
    if (!Array.isArray(results) || !results.length) {
        if (typeof wizHideAIWorking === 'function') wizHideAIWorking();
        if (typeof showToast === 'function') showToast('AI не разпозна цветове', 'error');
        _markPhotosFailed(todoIndices);
        renderWizard();
        return;
    }
    var applied = 0;
    results.forEach(function(res, i) {
        var targetIdx;
        if (typeof res.idx === 'number' && res.idx >= 0 && res.idx < todoIndices.length) {
            targetIdx = todoIndices[res.idx];
        } else {
            targetIdx = todoIndices[i];
        }
        if (targetIdx === undefined || !S.wizData._photos[targetIdx]) return;
        S.wizData._photos[targetIdx].ai_color = (res.color_bg || res.name || res.color || '').toString().trim();
        S.wizData._photos[targetIdx].ai_hex = res.hex || '#666';
        S.wizData._photos[targetIdx].ai_confidence = (typeof res.confidence === 'number') ? res.confidence : 0.5;
        applied++;
    });
    todoIndices.forEach(function(idx){
        if (!S.wizData._photos[idx]) return;
        if (S.wizData._photos[idx].ai_confidence === null || S.wizData._photos[idx].ai_confidence === undefined) {
            S.wizData._photos[idx].ai_color = '';
            S.wizData._photos[idx].ai_hex = '#666';
            S.wizData._photos[idx].ai_confidence = 0;
        }
    });
    S.wizData._aiColorsApplied = false;
    if (typeof wizHideAIWorking === 'function') wizHideAIWorking();
    if (typeof showToast === 'function') showToast('AI разпозна ' + applied + '/' + todoIndices.length + ' цвята', applied ? 'success' : 'error');
    renderWizard();
  }

  /* ═══ S148 ФАЗА 2e++e — AI inline buttons (1:1 sacred от p.php) ═══
       9007       _wizAIInlineToBlob(src)            — helper: fetch → blob
       9009-9032  wizAIInlineBgRemove                 — POST /ai-image-processor.php
       9034-9064  wizAIInlineSeoDesc                  — POST products.php?ajax=ai_description
       9066-9092  wizAIInlineMagic                    — POST /ai-studio-action.php (type=tryon)
     Всичките sacred endpoints директни (прецедент от ai-color-detect.php).
     S.wizData.composition / wComposition input / S.wizData.axes се DOM-guard-ват
     (Phase 4 ще ги донесе; сега silent no-op).
  */

  async function _wizAIInlineToBlob(src) { var r = await fetch(src); return await r.blob(); }

  async function wizAIInlineBgRemove() {
    if (!S.wizData._photoDataUrl) { showToast('Първо добави снимка', 'error'); return; }
    var btn = document.getElementById('aiInlBg');
    if (btn) btn.classList.add('busy');
    try {
        var blob = await _wizAIInlineToBlob(S.wizData._photoDataUrl);
        var fd = new FormData();
        fd.append('image', new File([blob], 'wiz.' + (blob.type === 'image/png' ? 'png' : 'jpg'), { type: blob.type || 'image/jpeg' }));
        var r = await fetch('/ai-image-processor.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        var j = await r.json();
        if (j && j.ok && j.url) {
            S.wizData._photoBgRemoved = j.url;
            S.wizData._photoDataUrl = j.url;
            showToast('Готово', 'success');
            renderWizard();
        } else {
            showToast((j && j.reason) || 'Грешка, опитай пак', 'error');
        }
    } catch (e) {
        showToast('Мрежова грешка', 'error');
    } finally {
        if (btn) btn.classList.remove('busy');
    }
  }

  async function wizAIInlineSeoDesc() {
    var name = S.wizData.name || '';
    if (!name) { showToast('Първо въведи име на артикула', 'error'); return; }
    var btn = document.getElementById('aiInlSeo');
    if (btn) btn.classList.add('busy');
    try {
        var cats = (typeof CFG !== 'undefined' && CFG.categories) ? CFG.categories.find(function(c){return c.id == S.wizData.category_id;}) : null;
        var sups = (typeof CFG !== 'undefined' && CFG.suppliers) ? CFG.suppliers.find(function(s){return s.id == S.wizData.supplier_id;}) : null;
        var axes = '';
        if (S.wizData.axes) S.wizData.axes.forEach(function(a){ if (a.values && a.values.length) axes += a.name + ': ' + a.values.join(', ') + '. '; });
        var r = await fetch('products.php?ajax=ai_description', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin',
            body: JSON.stringify({ name: name, category: cats ? cats.name : '', supplier: sups ? sups.name : '', axes: axes, composition: S.wizData.composition || '' })
        });
        var j = await r.json();
        if (j && j.description) {
            var existing = (S.wizData.composition || '').trim();
            S.wizData.composition = existing ? (existing + '\n\n' + j.description) : j.description;
            S.wizData.description = j.description;
            var compEl = document.getElementById('wComposition');
            if (compEl) compEl.value = S.wizData.composition;
            showToast('Описание готово', 'success');
        } else {
            showToast('Грешка при генериране', 'error');
        }
    } catch (e) {
        showToast('Мрежова грешка', 'error');
    } finally {
        if (btn) btn.classList.remove('busy');
    }
  }

  async function wizAIInlineMagic() {
    if (!S.wizData._photoDataUrl) { showToast('Първо добави снимка', 'error'); return; }
    var btn = document.getElementById('aiInlMagic');
    if (btn) btn.classList.add('busy');
    try {
        var blob = await _wizAIInlineToBlob(S.wizData._photoDataUrl);
        var fd = new FormData();
        fd.append('type', 'tryon');
        fd.append('image', new File([blob], 'wiz.' + (blob.type === 'image/png' ? 'png' : 'jpg'), { type: blob.type || 'image/jpeg' }));
        if (S.wizEditId) fd.append('product_id', String(S.wizEditId));
        var cats = (typeof CFG !== 'undefined' && CFG.categories) ? CFG.categories.find(function(c){return c.id == S.wizData.category_id;}) : null;
        if (cats && cats.name) fd.append('category', cats.name);
        var r = await fetch('/ai-studio-action.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        var j = await r.json();
        if (j && j.ok && j.url) {
            S.wizData._photoDataUrl = j.url;
            showToast('AI магия готова', 'success');
            renderWizard();
        } else {
            showToast((j && j.reason) || 'Грешка, опитай пак', 'error');
        }
    } catch (e) {
        showToast('Мрежова грешка', 'error');
    } finally {
        if (btn) btn.classList.remove('busy');
    }
  }

  /* ═══ S148 ФАЗА 2f — поле Име + микрофон (1:1 sacred от products.php) ═══

     1:1 копия:
       14303      var _wizMicRec
       14306      var WIZ_PRICE_FIELDS
       14308-14317 wizMic(field) — dispatch към Whisper (price) или Web Speech (other)
       14318-14340 _wizMicWebSpeech(field, lang)
       14395-14399 _wizMicInterim(field, text)
       14400-14416 _wizMicApply(field, text) — name/code/price/qty/etc. branches
       7539-7544   wizClearAIMark(key) — guards вече handle нашия минимален S
       12246-12262 wizCopyFieldFromPrev(field) — read от localStorage
       15046-15050 wizMarkDone(field)
       15051-15054 _wizClearHighlights()
       15055-15078 wizHighlightNext()

     STUB (поведения извън 2f scope):
       wizDupeCheckName — изисква /products.php?ajax=name_dupe_check + api()
       wizMaybeAdvancePhotoStep — изисква wizGo/wizStep machinery

     CFG: минимум stub за wizHighlightNext (skipWholesale) и wizMic (lang).
     _wizMicWhisper — идва от js/wizard-parser.js (FAZA 2a) → bridge?action=mic_whisper.
  */

  var _wizMicRec = null;
  var WIZ_PRICE_FIELDS = ['retail_price','cost_price','wholesale_price'];
  if (typeof window.CFG === 'undefined') {
    window.CFG = { lang: 'bg', skipWholesale: false, suppliers: [], categories: [] };
  }

  function wizMic(field){
    var lang=(window.CFG&&CFG.lang)||'bg';
    if(lang!=="bg" && WIZ_PRICE_FIELDS.indexOf(field)>=0 && window.MediaRecorder && navigator.mediaDevices && navigator.mediaDevices.getUserMedia){
        _wizMicWhisper(field,lang);
        return;
    }
    _wizMicWebSpeech(field,lang);
  }

  function _wizMicWebSpeech(field,lang){
    var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
    if(!SR){showToast('Гласът не се поддържа','error');return}
    if(_wizMicRec){try{_wizMicRec.abort()}catch(e){}_wizMicRec=null}
    _wizClearHighlights();
    var fieldMap={name:'wName',code:'wCode',retail_price:'wPrice',wholesale_price:'wWprice',cost_price:'wCostPrice',barcode:'wBarcode',supplier:'wSupDD',category:'wCatDD',origin:'wOrigin',composition:'wComposition',subcategory:'wSubcat',quantity:'wSingleQty',min_quantity:'wMinQty'};
    var targetEl=document.getElementById(fieldMap[field]);
    var targetFg=targetEl?targetEl.closest('.fg'):null;
    if(targetFg)targetFg.classList.add('wiz-active');
    var micBtn=targetFg?targetFg.querySelector('.wiz-mic'):null;
    if(micBtn)micBtn.classList.add('recording');
    var srLangMap={bg:'bg-BG',ro:'ro-RO',el:'el-GR',sr:'sr-RS',hr:'hr-HR',en:'en-US',mk:'mk-MK',sq:'sq-AL',tr:'tr-TR',sl:'sl-SI',de:'de-DE'};
    _wizMicRec=new SR();_wizMicRec.lang=srLangMap[lang||'bg']||'bg-BG';_wizMicRec.continuous=false;_wizMicRec.interimResults=true;
    _wizMicRec.onresult=function(e){
        var final='',interim='';
        for(var i=0;i<e.results.length;i++){if(e.results[i].isFinal)final+=e.results[i][0].transcript;else interim+=e.results[i][0].transcript}
        if(interim)_wizMicInterim(field,interim);
        if(final){if(micBtn)micBtn.classList.remove('recording');_wizMicApply(field,final.trim())}
    };
    _wizMicRec.onend=function(){if(micBtn)micBtn.classList.remove('recording')};
    _wizMicRec.onerror=function(){if(micBtn)micBtn.classList.remove('recording');var msg=(['retail_price','cost_price','wholesale_price'].indexOf(field)>=0)?'Кажи в евро и центове 🎤':'Грешка с микрофона';showToast(msg,'warn')};
    _wizMicRec.start();
  }

  function _wizMicInterim(field,text){
    var map={name:'wName',code:'wCode',retail_price:'wPrice',wholesale_price:'wWprice',cost_price:'wCostPrice',barcode:'wBarcode',origin:'wOrigin',composition:'wComposition'};
    var el=document.getElementById(map[field]);
    if(el){el.value=text;el.style.color='#64748b'}
  }

  function _wizMicApply(field,text){
    if(field==='name'){var el=document.getElementById('wName');el.value=text;el.style.color='';S.wizData.name=text;wizMarkDone('name');wizHighlightNext()}
    else if(field==='code'){var el=document.getElementById('wCode');if(el){el.value=text;el.style.color='';S.wizData.code=text;showToast('Записано','success');wizMarkDone('code');wizHighlightNext()}}
    else if(field==='retail_price'){var el=document.getElementById('wPrice');if(el){var n=_wizPriceParse(text);if(n!==null){el.value=n;S.wizData.retail_price=n;el.style.color='';showToast('Цена: '+el.value,'success');if(navigator.vibrate)navigator.vibrate(15);wizMarkDone('retail_price');wizHighlightNext()}else{_wizPriceCloudFallback('retail_price',text,'wPrice','retail_price','Цена')}}}
    else if(field==='wholesale_price'){var el=document.getElementById('wWprice');if(el){var n=_wizPriceParse(text);if(n!==null){el.value=n;S.wizData.wholesale_price=n;el.style.color='';showToast('Едро: '+el.value,'success');if(navigator.vibrate)navigator.vibrate(15);wizMarkDone('wholesale_price');wizHighlightNext()}else{_wizPriceCloudFallback('wholesale_price',text,'wWprice','wholesale_price','Едро')}}}
    else if(field==='cost_price'){var el=document.getElementById('wCostPrice');if(el){var n=_wizPriceParse(text);if(n!==null){el.value=n;S.wizData.cost_price=n;el.style.color='';showToast('Доставна: '+el.value,'success');if(navigator.vibrate)navigator.vibrate(15);wizMarkDone('cost_price');wizHighlightNext()}else{_wizPriceCloudFallback('cost_price',text,'wCostPrice','cost_price','Доставна')}}}
    else if(field==='barcode'){var el=document.getElementById('wBarcode');if(el){el.value=text.replace(/\s/g,'');el.style.color='';S.wizData.barcode=el.value;showToast('Баркод: '+el.value,'success');wizMarkDone('barcode');wizHighlightNext()}}
    else if(field==='quantity'){var el=document.getElementById('wSingleQty');if(el){var n=_bgPrice(text);var v=(n!==null&&n>=0)?Math.max(0,Math.round(n)):(parseInt(text.replace(/[^\d]/g,''),10)||0);el.value=v;S.wizData.quantity=v;showToast('Брой: '+v,'success');wizMarkDone&&wizMarkDone('quantity');wizHighlightNext()}}
    else if(field==='min_quantity'){var el=document.getElementById('wMinQty');if(el){var n=_bgPrice(text);var v=(n!==null&&n>=0)?Math.max(0,Math.round(n)):(parseInt(text.replace(/[^\d]/g,''),10)||0);el.value=v;el.dataset.userEdited='true';S.wizData.min_quantity=v;showToast('Мин: '+v,'success');wizMarkDone&&wizMarkDone('min_quantity');wizHighlightNext()}}
    // supplier/category/subcategory/origin/composition/location branches изискват CFG.suppliers / wizLoadSubcats / wizAddInline — 2f focus е само name. Тези branches остават undefined-safe.
  }

  function _wizClearHighlights(){
    document.querySelectorAll('#wizBody .fg').forEach(function(f){f.classList.remove('wiz-next','wiz-active')});
    document.querySelectorAll('#wizBody .wiz-mic.recording').forEach(function(m){m.classList.remove('recording')});
  }

  function wizMarkDone(field){
    var map={name:'wName',code:'wCode',retail_price:'wPrice',wholesale_price:'wWprice',barcode:'wBarcode',supplier:'wSupDD',category:'wCatDD',origin:'wOrigin',composition:'wComposition',subcategory:'wSubcat'};
    var el=document.getElementById(map[field]);
    if(el){var fg=el.closest('.fg');if(fg){fg.classList.remove('wiz-next');fg.classList.add('wiz-done')}}
  }

  function wizHighlightNext(){
    _wizClearHighlights();
    var fields=[
        {id:'wName',key:'name',check:function(){return !!S.wizData.name}},
        {id:'wCode',key:'code',check:function(){return !!S.wizData.code}},
        {id:'wPrice',key:'retail_price',check:function(){return S.wizData.retail_price>0}},
        {id:'wWprice',key:'wholesale_price',check:function(){return S.wizData.wholesale_price>0||CFG.skipWholesale}},
        {id:'wBarcode',key:'barcode',check:function(){return !!S.wizData.barcode}},
        {id:'wSupDD',key:'supplier',check:function(){return S.wizData.supplier_id>0}},
        {id:'wCatDD',key:'category',check:function(){return S.wizData.category_id>0}},
        {id:'wOrigin',key:'origin',check:function(){return !!S.wizData.origin_country||S.wizData.is_domestic}},
        {id:'wComposition',key:'composition',check:function(){return !!S.wizData.composition}},
        {id:'wSubcat',key:'subcategory',check:function(){return true}}
    ];
    for(var i=0;i<fields.length;i++){
        var el=document.getElementById(fields[i].id);
        if(!el)continue;
        if(!fields[i].check()){
            var fg=el.closest('.fg');
            if(fg){fg.classList.add('wiz-next')}
            return;
        }
    }
  }

  // wizClearAIMark — 1:1 от p.php 7539-7544. Guards handle minimal S.
  function wizClearAIMark(key){
    if(!S.wizData||!S.wizData._aiFilled||!S.wizData._aiFilled[key])return;
    delete S.wizData._aiFilled[key];
    var el=document.querySelector('.wiz-ai-hint[data-aikey="'+key+'"]');
    if(el)el.style.display='none';
  }

  // wizCopyFieldFromPrev — 1:1 от p.php 12246-12262. Чете localStorage; safe на missing DOMs.
  function wizCopyFieldFromPrev(field){
    var prev=null;try{prev=JSON.parse(localStorage.getItem('_rms_lastWizProductFields'))}catch(e){}
    if(!prev){showToast('Няма предишен артикул','error');return}
    var v=prev[field];
    if(v===undefined||v===null||v===''){showToast('Това поле е празно в последния','info');return}
    S.wizData[field]=v;
    var fieldToInput={retail_price:'wPrice',cost_price:'wCostPrice',markup_pct:'wMarkupPct',min_quantity:'wMinQty',composition:'wComposition',origin_country:'wOrigin',color:'wColor',size:'wSize'};
    var inpId=fieldToInput[field];
    if(inpId){var el=document.getElementById(inpId);if(el)el.value=v;}
    // Текстовите полета (name, code, barcode) — refresh DOM:
    var directMap={name:'wName',code:'wCode',barcode:'wBarcode'};
    if(directMap[field]){var de=document.getElementById(directMap[field]);if(de)de.value=v}
    showToast('Копирано от последния','success');
    if(navigator.vibrate)navigator.vibrate(5);
  }

  /* ═══ S148 ФАЗА 2i — sacred wizDupeCheckName + helpers (1:1 от p.php 7549-7592) ═══
     Live duplicate detection дебаунс 350ms, AJAX → products.php?ajax=name_dupe_check.
     При ≥85% similarity → banner с 2 опции: "Да, отвори същото" / "Не, продължи".
     Sacred endpoint exists (line 606). Visual: canon accent (БЕЗ yellow/amber per Тих).
  */
  var _wizDupeTimer = null;
  function _escDupe(s){
    return String(s).replace(/[&<>"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }
  function wizDupeCheckName(name){
    name = (name || '').trim();
    var banner = document.getElementById('wDupeBanner');
    if (!banner) return;
    if (_wizDupeTimer) { clearTimeout(_wizDupeTimer); _wizDupeTimer = null; }
    if (name.length < 3) { banner.style.display = 'none'; banner.innerHTML = ''; return; }
    if (S._wizDupeDismissed && S._wizDupeDismissed === name.toLowerCase()) { banner.style.display = 'none'; return; }
    _wizDupeTimer = setTimeout(function(){
      var url = 'products.php?ajax=name_dupe_check&q=' + encodeURIComponent(name);
      if (S.wizEditId) url += '&exclude_id=' + S.wizEditId;
      fetch(url, { credentials: 'same-origin' })
        .then(function(r){ return r.ok ? r.json() : Promise.reject(r.status); })
        .then(function(matches){
          if (!Array.isArray(matches) || !matches.length) { banner.style.display = 'none'; banner.innerHTML = ''; return; }
          var top = matches[0];
          if (!top || top.score < 0.85) { banner.style.display = 'none'; banner.innerHTML = ''; return; }
          var cur = (document.getElementById('wName') || {}).value || '';
          if (cur.trim().toLowerCase() !== name.toLowerCase()) return;
          var currency = (window.CFG && CFG.currency) || '€';
          var priceTxt = (top.price > 0) ? (' (' + parseFloat(top.price).toFixed(2) + ' ' + currency + ')') : '';
          var pct = Math.round(top.score * 100);
          banner.style.display = 'block';
          banner.innerHTML =
            '<div class="wiz-dupe-banner">'+
              '<div class="wiz-dupe-banner-row">'+
                '<svg class="dupe-ic" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>'+
                '<div style="flex:1;min-width:0">Близко до съществуващ артикул: <b>' + _escDupe(top.name) + '</b>' + _escDupe(priceTxt) + ' · <span class="wiz-dupe-pct">' + pct + '% близко</span>. Същото ли е?</div>'+
              '</div>'+
              '<div class="wiz-dupe-actions">'+
                '<button type="button" class="primary" onclick="wizDupeOpenExisting(' + top.id + ')">Да, отвори същото</button>'+
                '<button type="button" class="sec" onclick="wizDupeDismiss()">Не, продължи</button>'+
              '</div>'+
            '</div>';
        })
        .catch(function(){ /* silent: endpoint unavailable → no banner */ });
    }, 350);
  }
  function wizDupeDismiss(){
    var nm = (document.getElementById('wName') || {}).value || '';
    S._wizDupeDismissed = nm.trim().toLowerCase();
    var banner = document.getElementById('wDupeBanner');
    if (banner) { banner.style.display = 'none'; banner.innerHTML = ''; }
  }
  function wizDupeOpenExisting(id){
    if (!id) return;
    // wizard-v6 няма продуктов detail page; пренасочваме към products.php
    if (typeof closeWizard === 'function') closeWizard();
    window.location.href = '/products.php#product=' + id;
  }

  /* ═══ S148 ФАЗА 2i — AI hint badges (1:1 sacred p.php 7530-7544) ═══
     Показват "AI попълни — натисни за промяна" под полета които AI photo
     analysis е автопопълнил. _aiFilled state се чисти при user oninput.
  */
  function wizAIHint(key){
    if (!S.wizData || !S.wizData._aiFilled || !S.wizData._aiFilled[key]) return '';
    return '<div class="wiz-ai-hint" data-aikey="' + key + '">'+
      '<svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L9.5 9.5 2 12l7.5 2.5L12 22l2.5-7.5L22 12l-7.5-2.5L12 2z"/></svg>'+
      'AI попълни — натисни за промяна'+
    '</div>';
  }
  function wizMarkAIFilled(){
    if (!S.wizData) return;
    if (!S.wizData._aiFilled) S.wizData._aiFilled = {};
    for (var i = 0; i < arguments.length; i++) S.wizData._aiFilled[arguments[i]] = true;
  }

  /* ═══ S148 ФАЗА 2i — wizCopyPrevProductFull 1:1 от p.php 12231-12244 ═══
     "Като предния" bulk copy — всички полета от localStorage._rms_lastWizProductFields
     с изключение на name/barcode/code/photos (variant: + color/size).
  */
  function wizCopyPrevProductFull(){
    var prev = null;
    try { prev = JSON.parse(localStorage.getItem('_rms_lastWizProductFields')); } catch(e) {}
    if (!prev || typeof prev !== 'object') { showToast('Няма предишен артикул', 'error'); return; }
    var skip = ['name', 'barcode', 'code', '_photoDataUrl', '_photos'];
    if (S.wizType === 'variant') { skip.push('color'); skip.push('size'); }
    Object.keys(prev).forEach(function(k){
      if (skip.indexOf(k) !== -1) return;
      S.wizData[k] = prev[k];
    });
    renderWizard();
    showToast('Копиран целия профил', 'success');
    if (navigator.vibrate) navigator.vibrate([8, 30, 8]);
  }

  function wizMaybeAdvancePhotoStep(){ /* deferred: wizGo + wizStep state machine — Phase 4 */ }
  /* ═══ S148 ФАЗА 2g — _wizPriceCloudFallback 1:1 sacred (но routed през bridge) ═══
     Sacred reference: p.php 14499-14523. Промяна спрямо source:
     `fetch('/services/price-ai.php', ...)` → `fetch('/services/wizard-bridge.php?action=price_parse', ...)`.
     Sacred endpoint (price-ai.php) непроменен.
  */
  async function _wizPriceCloudFallback(field, text, inputId, dataKey, label) {
    var el = document.getElementById(inputId);
    if (!el) return;
    el.value = '…';
    el.style.color = 'var(--text-faint)';
    showToast('AI парсва "'+text+'"…', 'info');
    var lang = (window.CFG && CFG.lang) || 'bg';
    try {
      var r = await fetch('/services/wizard-bridge.php?action=price_parse', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ text: text, lang: lang })
      });
      if (!r.ok) { throw new Error('HTTP '+r.status); }
      var j = await r.json();
      if (j && j.ok && j.data && j.data.price !== null && j.data.price !== undefined && !isNaN(j.data.price)) {
        el.value = j.data.price;
        S.wizData[dataKey] = j.data.price;
        el.style.color = '';
        showToast(label + ' (AI): ' + el.value, 'success');
        if (navigator.vibrate) navigator.vibrate(15);
        if (typeof wizMarkDone === 'function') wizMarkDone(field);
        if (typeof wizHighlightNext === 'function') wizHighlightNext();
      } else {
        el.value = '';
        el.style.color = '';
        showToast('Не разбрах "' + text + '" — кажи отново', 'error');
      }
    } catch(e) {
      el.value = '';
      el.style.color = '';
      showToast('AI грешка — кажи отново', 'error');
    }
  }

  /* ═══ S148 ФАЗА 2g — Margin formula (1:1 sacred от p.php 9100-9105) ═══ */
  function _wizMarginPct(cost, retail) {
    cost = parseFloat(cost) || 0;
    retail = parseFloat(retail) || 0;
    if (cost <= 0 || retail <= 0) return null;
    return ((retail - cost) / retail) * 100;
  }

  /* wizUpdateMarkup — live margin display под retail. Прости версия (sacred wizUpdateMarkup
     p.php 12179-12204 използва wMarkupPct editable field; тук имаме само display).
  */
  function wizUpdateMarkup(){
    var disp = document.getElementById('wMarginDisplay');
    if (!disp) return;
    var cost = parseFloat(S.wizData.cost_price) || 0;
    var retail = parseFloat(S.wizData.retail_price) || 0;
    if (cost <= 0 || retail <= 0) { disp.style.display = 'none'; return; }
    var pct = _wizMarginPct(cost, retail);
    if (pct === null) { disp.style.display = 'none'; return; }
    var cls = pct > 30 ? 'gain' : (pct > 15 ? 'warn' : 'loss');
    disp.className = 'wz-margin-display ' + cls;
    disp.innerHTML = 'Печалба: <b>' + pct.toFixed(1) + '%</b>';
    disp.style.display = '';
  }

  /* wizMaybeFetchAIMarkup — debounced AI markup fetch при cost change */
  var _wizMarkupFetchTO = null;
  function wizMaybeFetchAIMarkup(){
    clearTimeout(_wizMarkupFetchTO);
    var cost = parseFloat(S.wizData.cost_price) || 0;
    var row = document.getElementById('wAIMarkupRow');
    if (cost <= 0) { if (row) row.style.display = 'none'; return; }
    _wizMarkupFetchTO = setTimeout(function(){ wizFetchAIMarkup(cost); }, 600);
  }

  /* wizFetchAIMarkup — POST bridge?action=ai_markup → suggested retail */
  async function wizFetchAIMarkup(cost){
    var row = document.getElementById('wAIMarkupRow');
    if (!row) return;
    row.style.display = '';
    row.innerHTML = '<span class="ai-markup-loading">AI изчислява...</span>';
    try {
      var r = await fetch('/services/wizard-bridge.php?action=ai_markup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ cost_price: cost, category_id: S.wizData.category_id || null })
      });
      if (!r.ok) throw new Error('HTTP '+r.status);
      var j = await r.json();
      if (j && j.ok && j.data && (j.data.retail_price || j.data.suggested_retail)) {
        var suggested = parseFloat(j.data.retail_price || j.data.suggested_retail);
        var markupPct = j.data.markup_pct || (((suggested / cost) - 1) * 100);
        row.innerHTML =
          '<div class="ai-markup-info">'+
            '<div class="ai-markup-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v3M12 18v3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M3 12h3M18 12h3M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1"/></svg></div>'+
            '<div class="ai-markup-text">AI предлага: <span class="ai-markup-value">'+suggested.toFixed(2)+' €</span><span class="ai-markup-meta">markup '+Math.round(markupPct)+'%</span></div>'+
            '<button type="button" class="ai-markup-apply" onclick="wizApplyAIMarkup('+suggested+')">Приеми</button>'+
          '</div>';
      } else {
        row.style.display = 'none';
      }
    } catch(e) {
      row.style.display = 'none';
      console.warn('[2g] AI markup error:', e);
    }
  }

  function wizApplyAIMarkup(value){
    var el = document.getElementById('wPrice');
    if (!el) return;
    el.value = value;
    S.wizData.retail_price = parseFloat(value);
    wizUpdateMarkup();
    showToast('Цена приета: '+value+' €', 'success');
    if (navigator.vibrate) navigator.vibrate(15);
    if (typeof wizMarkDone === 'function') wizMarkDone('retail_price');
    if (typeof wizHighlightNext === 'function') wizHighlightNext();
  }

  /* ═══ S148 ФАЗА 2h — Количество (1:1 sacred от p.php 12283-12313) ═══
     Stepper helpers; voice route → _wizMicApply quantity/min_quantity branches
     (вече налични от 2f) → _bgPrice local parse.
     Variant case: показва info note — matrix UI изисква Phase 3 OK от Тих.
  */
  function s95QtyAdjust(inputId, delta){
    var inp = document.getElementById(inputId);
    if (!inp) return;
    var cur = parseInt(inp.value) || 0;
    var next = Math.max(1, cur + delta);
    inp.value = next;
    S.wizData.quantity = next;
    s95AutoMinQty();
  }

  function s95AutoMinQty(){
    var q = parseInt(document.getElementById('wSingleQty')?.value) || 0;
    var mInp = document.getElementById('wMinQty');
    if (!mInp) return;
    if (mInp.dataset.userEdited === 'true') return;
    if (q <= 0) { mInp.value = ''; S.wizData.min_quantity = 0; return; }
    var m = Math.max(1, Math.round(q / 2.5));
    mInp.value = m;
    S.wizData.min_quantity = m;
  }

  function s95MinAdjust(delta){
    var inp = document.getElementById('wMinQty');
    if (!inp) return;
    var cur = parseInt(inp.value) || 0;
    var next = Math.max(0, cur + delta);
    inp.value = next;
    inp.dataset.userEdited = 'true';
    S.wizData.min_quantity = next;
  }

  function renderWizSection1Qty(){
    // Variant: matrix UI изисква Phase 3 (renderWizPagePart2) STOP. Показваме info note.
    if (S.wizType === 'variant') {
      return '<div class="wz-variant-qty-note">'+
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="13"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>'+
        '<div><b>Количество per вариация</b> — попълва се в "Вариации" секцията (matrix UI).</div>'+
      '</div>';
    }
    // Single (или type не избран): sacred compact inline stepper (1:1 от p.php 12575-12604).
    if (S.wizType !== 'single') return '';
    var _qVal = (S.wizData.quantity === undefined || S.wizData.quantity === null) ? '' : S.wizData.quantity;
    var _mqVal = (S.wizData.min_quantity === undefined || S.wizData.min_quantity === null || S.wizData.min_quantity === '') ? '' : S.wizData.min_quantity;
    var micSvg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg>';
    var copySvg = '<svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><polyline points="1 20 1 14 7 14"/><path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/></svg>';
    return '<div class="fg" style="margin:0 0 10px">'+
        '<label class="fl">Брой<span class="req-star">*</span></label>'+
        '<div class="wz-qty-row">'+
            '<div class="wz-qty-stepper">'+
                '<button type="button" class="wz-qty-btn" onclick="s95QtyAdjust(\'wSingleQty\',-1)" aria-label="Намали">−</button>'+
                '<input type="number" inputmode="numeric" min="0" id="wSingleQty" class="wz-qty-input" value="'+esc(String(_qVal))+'" placeholder="0" oninput="S.wizData.quantity=parseInt(this.value)||0;s95AutoMinQty()">'+
                '<button type="button" class="wz-qty-btn" onclick="s95QtyAdjust(\'wSingleQty\',1)" aria-label="Увеличи">+</button>'+
            '</div>'+
            '<button type="button" class="wiz-mic" onclick="wizMic(\'quantity\')" aria-label="Гласово въвеждане">'+micSvg+'</button>'+
            '<button type="button" class="copy-btn" onclick="wizCopyFieldFromPrev(\'quantity\')" title="Копирай от последния" aria-label="Копирай от последния">'+copySvg+'</button>'+
        '</div>'+
    '</div>'+
    '<div class="fg" style="margin:0 0 10px">'+
        '<label class="fl">Минимално количество <span class="hint">(авто от брой)</span></label>'+
        '<div class="wz-qty-row">'+
            '<div class="wz-qty-stepper is-min">'+
                '<button type="button" class="wz-qty-btn" onclick="s95MinAdjust(-1)" aria-label="Намали">−</button>'+
                '<input type="number" inputmode="numeric" min="0" id="wMinQty" class="wz-qty-input" value="'+esc(String(_mqVal))+'" placeholder="auto" oninput="S.wizData.min_quantity=parseInt(this.value)||0;this.dataset.userEdited=\'true\'">'+
                '<button type="button" class="wz-qty-btn" onclick="s95MinAdjust(1)" aria-label="Увеличи">+</button>'+
            '</div>'+
            '<button type="button" class="wiz-mic" onclick="wizMic(\'min_quantity\')" aria-label="Гласово въвеждане">'+micSvg+'</button>'+
            '<button type="button" class="copy-btn" onclick="wizCopyFieldFromPrev(\'min_quantity\')" title="Копирай от последния" aria-label="Копирай от последния">'+copySvg+'</button>'+
        '</div>'+
        '<div class="wz-min-hint">Под този брой системата ще препоръча да поръчаш</div>'+
    '</div>';
  }

  /* ═══ S148 ФАЗА 2e++a — type toggle (state-only за Phase 3 scaffold) ═══
     wizSwitchType сетва S.wizType ('single'|'variant'); НЕ отваря
     renderWizPagePart2 variations (sacred Phase 3 STOP остава).
     Markup 1:1 от p.php 12468-12488 (без copyPrevBtn — deferred за Phase 4 save flow).
  */
  function wizSwitchType(type){
    if(type!=='single'&&type!=='variant')return;
    S.wizType=type;
    if(navigator.vibrate)navigator.vibrate(10);
    renderWizard();
  }

  function renderWizSection1Type(){
    var typeChosen=(S.wizType==='single'||S.wizType==='variant');
    var sActive=(S.wizType==='single');
    var vActive=(S.wizType==='variant');
    // "Като предния" bulk copy bar (sacred p.php 12483-12488 — поставен ABOVE type toggle).
    var hasLast = false;
    try { hasLast = !!localStorage.getItem('_rms_lastWizProductFields'); } catch(e) {}
    var copyPrevBtn = hasLast
      ? '<button type="button" class="wz-copy-prev-btn" onclick="wizCopyPrevProductFull()"><svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><polyline points="1 20 1 14 7 14"/><path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/></svg>Копирай предния артикул</button>'
      : '<button type="button" class="wz-copy-prev-btn" disabled onclick="showToast(\'Налично след първия записан артикул\',\'info\')"><svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><polyline points="1 20 1 14 7 14"/><path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/></svg>Копирай предния артикул</button>';
    var typeBtnSingle='<button type="button" onclick="wizSwitchType(\'single\')" class="s95-type-btn'+(sActive?' active':'')+'">'+
        '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/></svg>'+
        '<span class="s95-type-btn-lbl">Единичен</span>'+
    '</button>';
    var typeBtnVariant='<button type="button" onclick="wizSwitchType(\'variant\')" class="s95-type-btn variant'+(vActive?' active':'')+'">'+
        '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="9" height="9" rx="2"/><rect x="13" y="2" width="9" height="9" rx="2"/><rect x="2" y="13" width="9" height="9" rx="2"/><rect x="13" y="13" width="9" height="9" rx="2"/></svg>'+
        '<span class="s95-type-btn-lbl">С Вариации</span>'+
    '</button>';
    var typeHint=typeChosen
        ? ''
        : '<div class="wz-type-hint">Избери тип артикул</div>';
    return copyPrevBtn+typeHint+'<div style="display:flex;gap:8px;align-items:stretch;margin-bottom:12px">'+typeBtnSingle+typeBtnVariant+'</div>';
  }

  /* ═══ S148 ФАЗА 2g — renderWizSection1Cost + Retail (1:1 sacred от p.php) ═══
     Source: p.php 8174 (retail), 8176 / 9269 (cost). Sacred markup adapted to
     wizard-v6 design canon: .wiz-mic + .copy-btn (mockup canonical buttons).
     wMarkupPct field (sacred has editable) тук не се рендерира — само live margin display.
  */
  function renderWizSection1Cost(){
    return '<div class="fg" style="margin:0 0 10px">'+
        '<label class="fl">Доставна цена <span class="hint">(на доставчик)</span></label>'+
        '<div style="display:flex;gap:6px;align-items:center">'+
            '<input type="number" step="0.01" inputmode="decimal" class="fc" id="wCostPrice" oninput="S.wizData.cost_price=parseFloat(this.value)||0;wizClearAIMark(\'cost_price\');wizUpdateMarkup();wizMaybeFetchAIMarkup()" value="'+(S.wizData.cost_price||'')+'" placeholder="0.00" style="flex:1">'+
            '<button type="button" class="wiz-mic" onclick="wizMic(\'cost_price\')" aria-label="Гласово въвеждане"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg></button>'+
            '<button type="button" class="copy-btn" onclick="wizCopyFieldFromPrev(\'cost_price\')" title="Копирай от последния" aria-label="Копирай от последния"><svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><polyline points="1 20 1 14 7 14"/><path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/></svg></button>'+
        '</div>'+
        '<div id="wAIMarkupRow" class="ai-markup-row" style="display:none"></div>'+
    '</div>';
  }

  function renderWizSection1Retail(){
    return '<div class="fg" style="margin:0 0 10px">'+
        '<label class="fl">Цена дребно<span class="req-star">*</span></label>'+
        '<div style="display:flex;gap:6px;align-items:center">'+
            '<input type="number" step="0.01" inputmode="decimal" class="fc" id="wPrice" oninput="S.wizData.retail_price=parseFloat(this.value)||0;wizClearAIMark(\'retail_price\');wizUpdateMarkup()" value="'+(S.wizData.retail_price||'')+'" placeholder="Кажи: 1 евро и 35 цента" style="flex:1">'+
            '<button type="button" class="wiz-mic" onclick="wizMic(\'retail_price\')" aria-label="Гласово въвеждане"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg></button>'+
            '<button type="button" class="copy-btn" onclick="wizCopyFieldFromPrev(\'retail_price\')" title="Копирай от последния" aria-label="Копирай от последния"><svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><polyline points="1 20 1 14 7 14"/><path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/></svg></button>'+
        '</div>'+
        wizAIHint('retail_price')+
        '<div id="wMarginDisplay" class="wz-margin-display" style="display:none"></div>'+
    '</div>';
  }

  // ═══ renderWizSection1Name — 1:1 nameH от products.php 12491-12499 ═══
  function renderWizSection1Name(){
    return '<div class="fg" style="margin:0 0 10px">'+
        '<label class="fl">Име<span class="req-star">*</span></label>'+
        '<div style="display:flex;gap:6px;align-items:center">'+
            '<input type="text" class="fc" id="wName" oninput="S.wizData.name=this.value.trim();wizClearAIMark(\'name\');wizDupeCheckName(this.value);wizMaybeAdvancePhotoStep()" value="'+esc(S.wizData.name||'')+'" placeholder="напр. Дънки Mustang син деним" style="flex:1">'+
            '<button type="button" class="wiz-mic" onclick="wizMic(\'name\')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M12 1a3 3 0 00-3 3v8a3 3 0 006 0V4a3 3 0 00-3-3z"/><path d="M19 10v2a7 7 0 01-14 0v-2"/><line x1="12" y1="19" x2="12" y2="23"/></svg></button>'+
            '<button type="button" class="copy-btn" onclick="wizCopyFieldFromPrev(\'name\')" title="Копирай от последния" aria-label="Копирай от последния"><svg viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/><polyline points="1 20 1 14 7 14"/><path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/></svg></button>'+
        '</div>'+
        wizAIHint('name')+
        '<div id="wDupeBanner" style="display:none"></div>'+
    '</div>';
  }

  // ═══ renderWizSection1Photo — 1:1 photoBlock construction от products.php 12391-12457 ═══
  function renderWizSection1Photo(){
    var _photoMode=S.wizData._photoMode;
    if(!_photoMode){try{_photoMode=localStorage.getItem('_rms_photoMode')||'single'}catch(e){_photoMode='single'}S.wizData._photoMode=_photoMode}
    if(S.wizType!=='variant')_photoMode='single';
    var _hasPhoto=!!S.wizData._photoDataUrl;
    var _photoModeToggle='';
    if(S.wizType==='variant'){
        _photoModeToggle=
            '<div class="photo-mode-toggle">'+
                '<button type="button" class="pmt-opt'+(_photoMode==='single'?' active':'')+'" onclick="wizSetPhotoMode(\'single\')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>Само главна снимка</button>'+
                '<button type="button" class="pmt-opt'+(_photoMode==='multi'?' active':'')+'" onclick="wizSetPhotoMode(\'multi\')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Снимки на вариации</button>'+
            '</div>';
    }
    var photoBlock='';
    if(_photoMode==='multi'){
        var _photos=Array.isArray(S.wizData._photos)?S.wizData._photos:[];
        var _gridH='<div class="photo-multi-grid">';
        _photos.forEach(function(p,i){
            var conf=(p.ai_confidence===null||p.ai_confidence===undefined)?null:p.ai_confidence;
            var confLabel='';var confCls='photo-color-conf';
            if(conf===null){confLabel='AI...';confCls+=' detecting'}
            else if(conf>=0.75){confLabel=Math.round(conf*100)+'%'}
            else if(conf>=0.5){confLabel=Math.round(conf*100)+'%';confCls+=' warn'}
            else{confLabel='?';confCls+=' warn'}
            var swHex=p.ai_hex||'#666';
            var nm=(p.ai_color||'').replace(/"/g,'&quot;');
            var isMain=!!p.is_main;
            var mainBadge=isMain?'<span class="ph-main-badge">ГЛАВНА</span>':'';
            var cellBorder=isMain?'class="photo-multi-cell is-main"':'class="photo-multi-cell"';
            var mainBtn=isMain
                ? '<div class="ph-main-label">Главна снимка</div>'
                : '<button type="button" class="ph-main-btn" onclick="wizSetMainPhoto('+i+')">Направи главна</button>';
            _gridH+=
                '<div '+cellBorder+'>'+
                    '<div class="photo-multi-thumb" style="position:relative">'+
                        '<img class="ph-img" src="'+p.dataUrl+'" alt="">'+
                        '<span class="ph-num">'+(i+1)+'</span>'+
                        mainBadge+
                        '<button type="button" class="ph-rm" onclick="wizPhotoMultiRemove('+i+')">×</button>'+
                    '</div>'+
                    '<div class="photo-color-input">'+
                        '<span class="photo-color-swatch" style="background:'+swHex+'"></span>'+
                        '<input type="text" value="'+nm+'" placeholder="цвят..." oninput="wizPhotoSetColor('+i+',this.value)">'+
                        '<span class="'+confCls+'">'+confLabel+'</span>'+
                    '</div>'+
                    mainBtn+
                '</div>';
        });
        _gridH+=
            '<div class="photo-multi-cell">'+
                '<div class="photo-empty-add" onclick="wizPhotoMultiPick()">'+
                    '<svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>'+
                    '<span>Добави</span>'+
                '</div>'+
            '</div>';
        _gridH+='</div>';
        var _info='<div class="photo-multi-info">Снимки по цвят: <b>'+_photos.length+'</b> · AI разпознава цветовете автоматично</div>';
        photoBlock='<div class="v4-pz">'+_photoModeToggle+_info+_gridH+'</div>';
    }else{
        var _photoContent=_hasPhoto
            ? '<img src="'+S.wizData._photoDataUrl+'" onclick="document.getElementById(\'filePickerInput\').click()" style="width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:14px;cursor:pointer;margin-bottom:10px">'
            : '<div class="v4-pz-top"><div class="v4-pz-ic"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#c4b5fd" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></div><div style="flex:1;min-width:0"><div class="v4-pz-title">Снимай артикула</div><div class="v4-pz-sub">AI анализира снимката</div></div></div>';
        var _photoBtns='<div class="v4-pz-btns"><button type="button" onclick="document.getElementById(\'photoInput\').click()" class="v4-pz-btn primary"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>Снимай</button><button type="button" onclick="document.getElementById(\'filePickerInput\').click()" class="v4-pz-btn sec"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>Галерия</button></div>';
        var _photoTips='<div class="v4-pz-tips"><span class="v4-pz-tip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Равна светла повърхност</span><span class="v4-pz-tip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Без други предмети</span><span class="v4-pz-tip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>Добро осветление</span></div>';
        photoBlock='<div class="v4-pz">'+_photoModeToggle+_photoContent+_wizAIInlineRows()+_photoBtns+_photoTips+'</div>';
    }
    return photoBlock;
  }

  function renderWizard(){
    var host=document.getElementById('wizSection1Inner');
    if(!host)return;
    host.innerHTML=
      renderWizSection1Type()+
      renderWizSection1Photo()+
      renderWizSection1Name()+
      renderWizSection1Retail()+
      renderWizSection1Qty();
    // S148 ФАЗА 2f: после ре-рендера highlight-ваме следващото незавършено поле.
    wizHighlightNext();
    // S148 ФАЗА 2h.2: cost field премахнат от Section 1 (Тих 2026-05-17 — "доставна цена в следващите").
    // Cost + AI markup + margin display ще се местят в Section 3 "Допълнителни" (Phase 4).
    // Функциите wizUpdateMarkup / wizFetchAIMarkup / _wizPriceCloudFallback / renderWizSection1Cost
    // остават дефинирани за reuse в Phase 4.
  }

  // ═══ Sacred file change handlers 1:1 от products.php 12744-12781 ═══
  document.getElementById('filePickerInput').addEventListener('change',async function(){
    if(this.getAttribute('data-studio')==='1'){
        this.removeAttribute('data-studio');
        // studioUploadPhoto не съществува в wizard-v6 контекст — silent no-op
        this.value='';
        return;
    }
    document.getElementById('photoInput').files = this.files;
    document.getElementById('photoInput').dispatchEvent(new Event('change'));
    this.value='';
  });
  document.getElementById('photoInput').addEventListener('change',async function(){
    if(!this.files?.[0])return;
    if(this.getAttribute('data-studio')==='1'){
        this.removeAttribute('data-studio');
        // studioUploadPhoto silent no-op в wizard-v6
        this.value='';
        return;
    }
    const preview=document.getElementById('wizPhotoPreview');
    const result=document.getElementById('wizScanResult');
    if(preview)preview.innerHTML='<div style="font-size:12px;color:var(--text-secondary);margin-top:8px">Зареждам...</div>';
    const reader=new FileReader();
    reader.onload=e=>{
        S.wizData._photoDataUrl=e.target.result;
        S.wizData._hasPhoto=true;
        if(document.getElementById('wizPhotoPreview'))document.getElementById('wizPhotoPreview').innerHTML='<img src="'+e.target.result+'" style="max-width:100%;max-height:150px;border-radius:10px;border:1px solid var(--border-subtle);margin-top:8px">';
        if(result)result.innerHTML='<div style="font-size:12px;color:var(--success);margin-top:6px">Снимката е заредена</div>';
        showToast('Снимка добавена','success');
        renderWizard();
    };
    reader.readAsDataURL(this.files[0]);
    this.value='';
  });

  // Initial render — single-mode, no photo, "Снимай артикула" placeholder card.
  renderWizard();

  /* Theme toggle (ФАЗА 1.5) — light по default, persist в localStorage rms_theme */
  (function(){
    const saved = localStorage.getItem('rms_theme');
    if (saved === 'dark') document.documentElement.setAttribute('data-theme','dark');

    window.toggleTheme = function(){
      const cur = document.documentElement.getAttribute('data-theme');
      if (cur === 'dark') {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('rms_theme','light');
      } else {
        document.documentElement.setAttribute('data-theme','dark');
        localStorage.setItem('rms_theme','dark');
      }
      updateThemeIcon();
    };

    function updateThemeIcon(){
      const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
      const moon = document.getElementById('themeIconMoon');
      const sun  = document.getElementById('themeIconSun');
      if (moon) moon.style.display = isDark ? 'none'  : 'block';
      if (sun)  sun.style.display  = isDark ? 'block' : 'none';
    }

    updateThemeIcon();
  })();
  /* TODO Фаза 2-4 — wizard JS + sacred bridge calls */
  </script>
</body>
</html>
