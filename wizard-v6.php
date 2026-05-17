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
:root[data-theme="dark"]{--indigo-300:#a5b4fc;--text-primary:#f1f5f9;--text-secondary:rgba(255,255,255,.6);--success:#86efac;--border-subtle:rgba(99,102,241,.15);--bg-card:rgba(15,15,40,.75);--border-glow:rgba(99,102,241,.4)}
:root:not([data-theme]),:root[data-theme="light"]{--indigo-300:#4f46e5;--text-primary:#0f172a;--text-secondary:rgba(15,23,42,.70);--success:#22c55e;--border-subtle:rgba(99,102,241,.18);--bg-card:rgba(255,255,255,.95);--border-glow:rgba(99,102,241,.4)}

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

.ai-inline-rows{display:flex;flex-direction:column;gap:6px;margin:8px 0 10px}
.ai-inline-row{position:relative;display:flex;align-items:center;gap:10px;padding:11px 14px;min-height:44px;border-radius:12px;background:linear-gradient(180deg,rgba(139,92,246,0.10),rgba(99,102,241,0.04));border:1px solid rgba(139,92,246,0.32);color:#e2e8f0;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:transform .12s ease,background .2s ease,border-color .2s ease;box-shadow:0 0 14px rgba(139,92,246,0.10),inset 0 1px 0 rgba(255,255,255,0.04)}
.ai-inline-row .air-ic{font-size:16px}
.ai-inline-row .air-lbl{flex:1}
.ai-inline-row .air-price{font-size:11px;color:var(--indigo-300);font-weight:700}

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
  </style>
</head>
<body>

  <!-- AURORA (3 blobs) -->
  <div class="aurora">
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
    <main class="wz-main">

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
    e.innerHTML=(type==='success'?'✓ ':type==='error'?'✕ ':'ℹ ')+esc(msg);
    c.appendChild(e);
    requestAnimationFrame(()=>e.classList.add('show'));
    setTimeout(()=>{e.classList.remove('show');setTimeout(()=>e.remove(),300)},3000);
  }

  function _wizAIInlineRows() {
    if (!S.wizData._photoDataUrl) return '';
    var p = WIZ_AI_INLINE_PRICES;
    return '<div class="ai-inline-rows q-magic">' +
        '<button type="button" class="ai-inline-row" id="aiInlBg" onclick="wizAIInlineBgRemove()"><span class="air-ic">🖼</span><span class="air-lbl">Махни фон</span><span class="air-price">€' + p.bg.toFixed(2) + '</span></button>' +
        '<button type="button" class="ai-inline-row" id="aiInlSeo" onclick="wizAIInlineSeoDesc()"><span class="air-ic">📝</span><span class="air-lbl">SEO описание</span><span class="air-price">€' + p.desc.toFixed(2) + '</span></button>' +
        '<button type="button" class="ai-inline-row" id="aiInlMagic" onclick="wizAIInlineMagic()"><span class="air-ic">✨</span><span class="air-lbl">AI магия</span><span class="air-price">€' + p.magic.toFixed(2) + '</span></button>' +
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

  // STUBS (deferred — multi-mode drawer + AI inline funcs ще се добавят в следваща sub-step).
  // В 2e default flow е single-mode (S.wizType !== 'variant' → _photoMode='single' винаги),
  // тези не се извикват в normal usage.
  function wizPhotoMultiPick(){showToast('Multi-photo flow: следваща sub-step','info')}
  function wizAIInlineBgRemove(){showToast('AI махни фон: следваща sub-step','info')}
  function wizAIInlineSeoDesc(){showToast('SEO описание: следваща sub-step','info')}
  function wizAIInlineMagic(){showToast('AI магия: следваща sub-step','info')}

  // ═══ renderWizSection1Photo — 1:1 photoBlock construction от products.php 12391-12457 ═══
  function renderWizSection1Photo(){
    var _photoMode=S.wizData._photoMode;
    if(!_photoMode){try{_photoMode=localStorage.getItem('_rms_photoMode')||'single'}catch(e){_photoMode='single'}S.wizData._photoMode=_photoMode}
    if(S.wizType!=='variant')_photoMode='single';
    var _hasPhoto=!!S.wizData._photoDataUrl;
    var _photoModeToggle='';
    if(S.wizType==='variant'){
        _photoModeToggle=
            '<div class="photo-mode-toggle" style="display:flex;gap:6px;margin-bottom:12px;padding:4px;border-radius:12px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06)">'+
                '<button type="button" class="pmt-opt'+(_photoMode==='single'?' active':'')+'" onclick="wizSetPhotoMode(\'single\')" style="flex:1;padding:10px;border-radius:9px;background:'+(_photoMode==='single'?'linear-gradient(180deg,rgba(99,102,241,0.18),rgba(67,56,202,0.08))':'transparent')+';border:1px solid '+(_photoMode==='single'?'rgba(139,92,246,0.5)':'transparent')+';color:'+(_photoMode==='single'?'#c4b5fd':'rgba(255,255,255,0.55)')+';font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>Само главна снимка</button>'+
                '<button type="button" class="pmt-opt'+(_photoMode==='multi'?' active':'')+'" onclick="wizSetPhotoMode(\'multi\')" style="flex:1;padding:10px;border-radius:9px;background:'+(_photoMode==='multi'?'linear-gradient(180deg,rgba(217,70,239,0.18),rgba(168,85,247,0.08))':'transparent')+';border:1px solid '+(_photoMode==='multi'?'rgba(217,70,239,0.5)':'transparent')+';color:'+(_photoMode==='multi'?'#f0abfc':'rgba(255,255,255,0.55)')+';font-size:11px;font-weight:600;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:6px"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>Снимки на вариации</button>'+
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
            var mainBadge=isMain?'<span style="position:absolute;top:6px;left:6px;padding:2px 7px;border-radius:7px;background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#0f1224;font-size:9px;font-weight:800;letter-spacing:0.04em;box-shadow:0 2px 8px rgba(251,191,36,0.5);z-index:2">★ ГЛАВНА</span>':'';
            var cellBorder=isMain?'border:2px solid #fbbf24;box-shadow:0 0 14px rgba(251,191,36,0.35)':'';
            var mainBtn=isMain
                ? '<div style="margin-top:6px;font-size:10px;color:#fbbf24;text-align:center;font-weight:600">★ Главна снимка</div>'
                : '<button type="button" onclick="wizSetMainPhoto('+i+')" style="margin-top:6px;width:100%;padding:7px;border-radius:8px;background:rgba(251,191,36,0.08);border:1px solid rgba(251,191,36,0.3);color:#fcd34d;font-size:10px;font-weight:600;cursor:pointer;font-family:inherit">★ Направи главна</button>';
            _gridH+=
                '<div class="photo-multi-cell" style="position:relative;'+cellBorder+'">'+
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
    if(host)host.innerHTML=renderWizSection1Photo();
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
