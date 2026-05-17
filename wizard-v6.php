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
<html lang="bg" data-theme="dark">
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
      <button class="icon-btn" aria-label="Тема"><!-- TODO Фаза 2: theme toggle --></button>
    </header>

    <!-- ═══ MAIN: 4 акордеона ═══ -->
    <main class="wz-main">

      <!-- Секция 1 — Снимка + Основно (qm = magic purple) -->
      <section data-section="photo" class="glass qm">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <h2>Снимка + Основно</h2>
        <div class="ph">TODO ФАЗА 2 — photo upload, име/цена/количество, AI markup</div>
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

  <!-- ═══ FOOTER: Undo / Print / CSV / Запази ═══ -->
  <footer class="wz-foot">
    <button aria-label="Undo"><!-- TODO Фаза 2 -->Undo</button>
    <button aria-label="Print"><!-- TODO Фаза 4 -->Print</button>
    <button aria-label="CSV"><!-- TODO Фаза 4 -->CSV</button>
    <button class="save-btn" aria-label="Запази"><!-- TODO Фаза 2 -->Запази</button>
  </footer>

  <script src="js/capacitor-printer.js"></script>
  <script>/* TODO Фаза 2-4 — wizard JS + sacred bridge calls */</script>
</body>
</html>
