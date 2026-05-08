<?php
/**
 * ai-studio.php — Standalone AI Studio main page (S84.STUDIO.REWIRE)
 *
 * Mockup source: /root/ai-studio-main-v2.html (approved by Tihol on 2026-04-26).
 *
 * Frontend reads through ai-studio-backend.php helpers — no direct
 * tenants.ai_credits_* legacy columns.
 *   - Credits bar: get_credit_balance($tenant_id, 'bg'|'desc'|'magic')
 *   - Bulk + per-category counts: count_products_needing_ai($tenant_id, ?$category)
 *   - History: last 8 from ai_spend_log
 *   - Anti-abuse banner: check_anti_abuse($tenant_id)
 */

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';
require_once __DIR__ . '/ai-image-credits.php';
require_once __DIR__ . '/ai-studio-backend.php';

if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$tenant_id = (int)$_SESSION['tenant_id'];
$user_id   = (int)$_SESSION['user_id'];

// ───────────────────────────────────────────────────────────────────────
// Tenant + plan
// ───────────────────────────────────────────────────────────────────────
$tenant = DB::run(
    "SELECT id, plan, plan_effective, trial_ends_at,
            included_bg_per_month, included_desc_per_month, included_magic_per_month
     FROM tenants WHERE id = ?",
    [$tenant_id]
)->fetch(PDO::FETCH_ASSOC) ?: [];
$plan_eff   = function_exists('effectivePlan') ? effectivePlan($tenant) : ($tenant['plan'] ?? 'free');
$plan_label = strtoupper($plan_eff);
if ($plan_eff === 'god') $plan_label = 'PRO';
$is_locked = ($plan_eff === 'free');

// ───────────────────────────────────────────────────────────────────────
// Credits — real balances via backend helper
// ───────────────────────────────────────────────────────────────────────
$bal_bg    = get_credit_balance($tenant_id, 'bg');
$bal_desc  = get_credit_balance($tenant_id, 'desc');
$bal_magic = get_credit_balance($tenant_id, 'magic');

$bg_remaining    = (int)$bal_bg['total'];
$desc_remaining  = (int)$bal_desc['total'];
$tryon_remaining = (int)$bal_magic['total'];

$bg_total    = (int)($tenant['included_bg_per_month']    ?? 0) + (int)$bal_bg['purchased'];
$desc_total  = (int)($tenant['included_desc_per_month']  ?? 0) + (int)$bal_desc['purchased'];
$tryon_total = (int)($tenant['included_magic_per_month'] ?? 0) + (int)$bal_magic['purchased'];

if ($plan_eff === 'god') {
    $bg_remaining = $desc_remaining = $tryon_remaining = 999;
    $bg_total     = $desc_total     = $tryon_total     = 999;
}

// ───────────────────────────────────────────────────────────────────────
// Bulk + per-category counts via count_products_needing_ai()
// ───────────────────────────────────────────────────────────────────────
$needs_total     = count_products_needing_ai($tenant_id);
$bulk_bg_count   = (int)$needs_total['bg'];
$bulk_desc_count = (int)$needs_total['desc'];

$PRICE_BG    = defined('AI_BG_PRICE')    ? AI_BG_PRICE    : 0.05;
$PRICE_DESC  = defined('AI_DESC_PRICE')  ? AI_DESC_PRICE  : 0.02;
$PRICE_MAGIC = defined('AI_MAGIC_PRICE') ? AI_MAGIC_PRICE : 0.50;

$bulk_bg_cost   = round($bulk_bg_count   * $PRICE_BG,   2);
$bulk_desc_cost = round($bulk_desc_count * $PRICE_DESC, 2);

$AI_CATEGORIES = [
    ['key' => 'clothes',  'label' => 'Дрехи',          'emoji' => '👕'],
    ['key' => 'lingerie', 'label' => 'Бельо и бански', 'emoji' => '👙'],
    ['key' => 'jewelry',  'label' => 'Бижута',         'emoji' => '💎'],
    ['key' => 'acc',      'label' => 'Аксесоари',      'emoji' => '👜'],
    ['key' => 'other',    'label' => 'Друго / Предмет','emoji' => '📦'],
];

// Subtype breakdown — group products needing magic by ai_subtype so the
// sub-line shows real counts ("8 тениски · 3 рокли") instead of a static label.
$subtypes_by_cat = [];
try {
    $subtype_rows = DB::run(
        "SELECT ai_category, ai_subtype, COUNT(*) AS c
         FROM products
         WHERE tenant_id = ? AND is_active = 1 AND parent_id IS NULL
           AND (ai_magic_image IS NULL OR ai_magic_image = '')
           AND ai_category IS NOT NULL AND ai_subtype IS NOT NULL AND ai_subtype <> ''
         GROUP BY ai_category, ai_subtype",
        [$tenant_id]
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($subtype_rows as $r) {
        $subtypes_by_cat[$r['ai_category']][] = ['sub' => $r['ai_subtype'], 'c' => (int)$r['c']];
    }
} catch (Throwable $e) { $subtypes_by_cat = []; }

$DEFAULT_SUBS = [
    'clothes'  => 'Облечи на модел или студийна',
    'lingerie' => 'Облечи на модел · студийна',
    'jewelry'  => 'Студийна снимка близък план',
    'acc'      => 'Студийна снимка с настройка',
    'other'    => 'Свободно описание — каквото и да е',
];

foreach ($AI_CATEGORIES as &$cat) {
    $counts = count_products_needing_ai($tenant_id, $cat['key']);
    $cat['count'] = (int)$counts['magic'];
    $rows = $subtypes_by_cat[$cat['key']] ?? [];
    if ($rows) {
        usort($rows, fn($a, $b) => $b['c'] - $a['c']);
        $parts = [];
        foreach (array_slice($rows, 0, 3) as $r) {
            $parts[] = $r['c'] . ' ' . $r['sub'];
        }
        $cat['sub'] = $parts ? implode(' · ', $parts) : $DEFAULT_SUBS[$cat['key']];
    } else {
        $cat['sub'] = $DEFAULT_SUBS[$cat['key']];
    }
}
unset($cat);
$category_total = array_sum(array_column($AI_CATEGORIES, 'count'));

// ───────────────────────────────────────────────────────────────────────
// History — last 8 from ai_spend_log
// ───────────────────────────────────────────────────────────────────────
$history = [];
try {
    $history = DB::run(
        "SELECT id, feature, category, status, created_at
         FROM ai_spend_log
         WHERE tenant_id = ?
         ORDER BY created_at DESC LIMIT 8",
        [$tenant_id]
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) { $history = []; }

$OP_BADGE_MAP = [
    'bg_remove'    => ['emoji' => '🖼', 'tag' => 'clothes'],
    'color_detect' => ['emoji' => '🎨', 'tag' => 'other'],
    'tryon'        => ['emoji' => '✨', 'tag' => 'lingerie'],
    'magic'        => ['emoji' => '💎', 'tag' => 'jewelry'],
    'studio'       => ['emoji' => '💎', 'tag' => 'jewelry'],
    'description'  => ['emoji' => '📝', 'tag' => 'acc'],
];
$VALID_TAGS = ['clothes' => 1, 'lingerie' => 1, 'jewelry' => 1, 'acc' => 1, 'other' => 1];

// ───────────────────────────────────────────────────────────────────────
// Anti-abuse — soft warning + hard block banner
// ───────────────────────────────────────────────────────────────────────
$abuse = check_anti_abuse($tenant_id);

require_once __DIR__ . '/partials/shell-init.php';
?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<title>AI Studio · RunMyStore.ai</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/shell.css">
<style>
:root {
    --bg-main: #030712;
    --bg-card: rgba(15, 15, 40, 0.75);
    --border-subtle: rgba(99, 102, 241, 0.15);
    --indigo-600: #4f46e5; --indigo-500: #6366f1; --indigo-400: #818cf8; --indigo-300: #a5b4fc;
    --text-primary: #f1f5f9; --text-secondary: #6b7280;
    --danger: #ef4444; --warning: #f59e0b; --success: #22c55e; --purple: #8b5cf6;
    --hue1: 255; --hue2: 222;
    --border: 1px;
    --border-color: hsl(var(--hue2), 12%, 20%);
    --radius: 22px; --radius-sm: 14px; --radius-lg: 28px;
    --ease: cubic-bezier(0.5, 1, 0.89, 1);
}
* { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
html, body { background: var(--bg-main); color: var(--text-primary); font-family: 'Montserrat', system-ui, sans-serif; min-height: 100vh; overflow-x: hidden; }
input, textarea, button, select { font-family: 'Montserrat', system-ui, sans-serif; }

body {
    background:
        radial-gradient(ellipse 800px 500px at 20% 10%, hsl(var(--hue1) 60% 35% / 0.22) 0%, transparent 60%),
        radial-gradient(ellipse 700px 500px at 85% 85%, hsl(var(--hue2) 60% 35% / 0.22) 0%, transparent 60%),
        linear-gradient(180deg, #0a0b14 0%, #050609 100%);
    background-attachment: fixed;
    padding-bottom: 100px;
}
body::before {
    content: ''; position: fixed; inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.5'/%3E%3C/svg%3E");
    opacity: 0.03; pointer-events: none; z-index: 1; mix-blend-mode: overlay;
}
.app { position: relative; z-index: 2; max-width: 480px; margin: 0 auto; padding: 14px 12px 20px; }

/* ═══ GLASS — production warehouse / wizard ═══ */
.glass {
    position: relative; border-radius: var(--radius);
    border: var(--border) solid var(--border-color);
    background:
        linear-gradient(235deg, hsl(var(--hue1) 50% 10% / 0.8), hsl(var(--hue1) 50% 10% / 0) 33%),
        linear-gradient(45deg, hsl(var(--hue2) 50% 10% / 0.8), hsl(var(--hue2) 50% 10% / 0) 33%),
        linear-gradient(hsl(220deg 25% 4.8% / 0.78));
    backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
    box-shadow: hsl(var(--hue2) 50% 2%) 0 10px 16px -8px, hsl(var(--hue2) 50% 4%) 0 20px 36px -14px;
    isolation: isolate;
}
.glass .shine, .glass .glow { --hue: var(--hue1); }
.glass .shine-bottom, .glass .glow-bottom { --hue: var(--hue2); --conic: 135deg; }
.glass .shine, .glass .shine::before, .glass .shine::after {
    pointer-events: none; border-radius: 0;
    border-top-right-radius: inherit; border-bottom-left-radius: inherit;
    border: 1px solid transparent; width: 75%; aspect-ratio: 1; display: block; position: absolute;
    right: calc(var(--border) * -1); top: calc(var(--border) * -1); left: auto; z-index: 1; --start: 12%;
    background: conic-gradient(from var(--conic, -45deg) at center in oklch, transparent var(--start, 0%), hsl(var(--hue), var(--sat, 80%), var(--lit, 60%)), transparent var(--end, 50%)) border-box;
    mask: linear-gradient(transparent), linear-gradient(black);
    mask-repeat: no-repeat; mask-clip: padding-box, border-box; mask-composite: subtract;
}
.glass .shine::before, .glass .shine::after { content: ""; width: auto; inset: -2px; mask: none; }
.glass .shine::after { z-index: 2; --start: 17%; --end: 33%; background: conic-gradient(from var(--conic, -45deg) at center in oklch, transparent var(--start, 0%), hsl(var(--hue), var(--sat, 80%), var(--lit, 85%)), transparent var(--end, 50%)); }
.glass .shine-bottom { top: auto; bottom: calc(var(--border) * -1); left: calc(var(--border) * -1); right: auto; }
.glass .glow {
    pointer-events: none;
    border-top-right-radius: calc(var(--radius) * 2.5); border-bottom-left-radius: calc(var(--radius) * 2.5);
    border: calc(var(--radius) * 1.25) solid transparent;
    inset: calc(var(--radius) * -2);
    width: 75%; aspect-ratio: 1; display: block; position: absolute; left: auto; bottom: auto;
    mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='240' height='240'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.75' numOctaves='3' seed='5'/%3E%3CfeColorMatrix values='0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    mask-mode: luminance; mask-size: 29%;
    opacity: 1; filter: blur(12px) saturate(1.25) brightness(0.5);
    mix-blend-mode: plus-lighter; z-index: 3;
}
.glass .glow.glow-bottom { inset: calc(var(--radius) * -2); top: auto; right: auto; }
.glass .glow::before, .glass .glow::after {
    content: ""; position: absolute; inset: 0; border: inherit; border-radius: inherit;
    background: conic-gradient(from var(--conic, -45deg) at center in oklch, transparent var(--start, 0%), hsl(var(--hue), var(--sat, 95%), var(--lit, 60%)), transparent var(--end, 50%)) border-box;
    mask: linear-gradient(transparent), linear-gradient(black);
    mask-repeat: no-repeat; mask-clip: padding-box, border-box; mask-composite: subtract;
    filter: saturate(2) brightness(1);
}
.glass .glow::after { --lit: 70%; --sat: 100%; --start: 15%; --end: 35%; border-width: calc(var(--radius) * 1.75); border-radius: calc(var(--radius) * 2.75); inset: calc(var(--radius) * -0.25); z-index: 4; opacity: 0.75; }
.glass .glow-bright { --lit: 80%; --sat: 100%; --start: 13%; --end: 37%; border-width: 5px; border-radius: calc(var(--radius) + 2px); inset: -7px; left: auto; filter: blur(2px) brightness(0.66); }
.glass .glow-bright::after { content: none; }
.glass .glow-bright.glow-bottom { inset: -7px; right: auto; top: auto; }
.glass.sm { --radius: var(--radius-sm); }

/* ═══ HERO BANNER ═══ */
.hero-banner { display: flex; align-items: center; gap: 13px; padding: 14px 16px; margin-bottom: 14px; }
.hero-banner-ico { width: 48px; height: 48px; border-radius:var(--radius); background: linear-gradient(135deg, var(--indigo-500), var(--purple)); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 0 22px rgba(99,102,241,0.45), inset 0 1px 0 rgba(255,255,255,0.25); position: relative; z-index: 5; }
.hero-banner-ico svg { width: 24px; height: 24px; fill: white; filter: drop-shadow(0 0 8px rgba(255,255,255,0.6)); }
.hero-banner-text { flex: 1; min-width: 0; position: relative; z-index: 5; }
.hero-banner-text h2 { font-size: 16px; font-weight: 800; color: var(--text-primary); line-height: 1.2; }
.hero-banner-text p { font-size: 11px; color: var(--text-secondary); margin-top: 2px; line-height: 1.4; }

/* ═══ CREDITS BAR ═══ */
.credits-bar { display: flex; align-items: center; gap: 10px; padding: 11px 14px; margin-bottom: 12px; border-radius:var(--radius); background: rgba(34,197,94,0.04); border: 1px solid rgba(34,197,94,0.18); cursor: pointer; user-select: none; width: 100%; text-align: left; transition: all 0.2s var(--ease); appearance: none; -webkit-appearance: none; font-family: inherit; }
.credits-bar:hover { background: rgba(34,197,94,0.07); border-color: rgba(34,197,94,0.32); box-shadow: 0 0 14px rgba(34,197,94,0.15); }
.credits-bar .cr-plan { padding: 3px 8px; border-radius:var(--radius-pill); background: linear-gradient(135deg, var(--indigo-500), var(--indigo-600)); color: #fff; font-size: 9px; font-weight: 800; letter-spacing: 0.08em; flex-shrink: 0; box-shadow: 0 0 10px rgba(99,102,241,0.35); }
.credits-bar .cr-content { flex: 1; min-width: 0; }
.credits-bar .cr-line { display: flex; gap: 9px; align-items: baseline; flex-wrap: wrap; }
.credits-bar .cr-item { font-size: 11px; color: var(--text-secondary); font-weight: 600; }
.credits-bar .cr-item b { color: #22c55e; font-size: 14px; font-weight: 800; }
.credits-bar .cr-item.desc b { color: hsl(141 79% 73%); }
.credits-bar .cr-item.tryon b { color: #c4b5fd; }
.credits-bar .cr-sep { width: 1px; height: 12px; background: rgba(255,255,255,0.12); align-self: center; }
.credits-bar .cr-sub { font-size: 9px; color: rgba(255,255,255,0.4); margin-top: 3px; letter-spacing: 0.02em; }
.credits-bar .cr-arrow { width: 26px; height: 26px; border-radius: 50%; background: linear-gradient(135deg, rgba(99,102,241,0.35), rgba(67,56,202,0.25)); border: 1px solid rgba(99,102,241,0.4); display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 0 8px rgba(99,102,241,0.25); }
.credits-bar .cr-arrow svg { width: 12px; height: 12px; stroke: var(--indigo-300); stroke-width: 2.5; fill: none; stroke-linecap: round; stroke-linejoin: round; }

/* ═══ STUDIO CARDS ═══ */
.studio-card { padding: 16px; margin-bottom: 12px; }
.section-head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; position: relative; z-index: 5; }
.section-head-icon { width: 36px; height: 36px; border-radius:var(--radius); background: linear-gradient(135deg, var(--indigo-600), var(--indigo-500)); border: 1px solid var(--indigo-400); display: flex; align-items: center; justify-content: center; box-shadow: 0 0 18px rgba(99,102,241,0.35); flex-shrink: 0; }
.section-head-icon svg { width: 18px; height: 18px; stroke: white; stroke-width: 2; fill: none; stroke-linecap: round; stroke-linejoin: round; }
.section-head-icon.green { background: linear-gradient(135deg, #16a34a, #22c55e); border-color: #4ade80; box-shadow: 0 0 18px rgba(34,197,94,0.35); }
.section-head-icon.amber { background: linear-gradient(135deg, #b45309, #d97706); border-color: hsl(43 96% 56%); box-shadow: 0 0 18px rgba(180,83,9,0.35); }
.section-head-text { flex: 1; min-width: 0; }
.section-head-title { font-size: 14px; font-weight: 800; color: var(--text-primary); margin-bottom: 1px; }
.section-head-sub { font-size: 11px; color: rgba(255,255,255,0.4); line-height: 1.3; }
.section-head-count { padding: 4px 10px; border-radius:var(--radius-pill); font-size: 10px; font-weight: 800; flex-shrink: 0; background: rgba(99,102,241,0.12); color: var(--indigo-300); border: 0.5px solid rgba(99,102,241,0.28); }

/* ═══ STUDIO OPTIONS ═══ */
.studio-opt { padding: 11px 12px; border-radius:var(--radius); margin-bottom: 6px; transition: all 0.2s var(--ease); cursor: pointer; font-family: inherit; width: 100%; text-align: left; appearance: none; border: 1px solid; background: none; position: relative; z-index: 5; }
.studio-opt-row { display: flex; align-items: center; gap: 10px; }
.studio-opt-row .so-ico { width: 36px; height: 36px; border-radius:var(--radius-sm); display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: rgba(255,255,255,0.05); font-size: 18px; }
.studio-opt-row .so-ico svg { width: 18px; height: 18px; stroke-width: 1.7; fill: none; stroke-linecap: round; stroke-linejoin: round; }
.studio-opt-row .so-text { flex: 1; min-width: 0; }
.studio-opt-row .so-t { font-size: 13px; font-weight: 700; color: var(--text-primary); line-height: 1.2; }
.studio-opt-row .so-s { font-size: 10px; color: var(--text-secondary); margin-top: 2px; line-height: 1.3; }
.studio-opt-row .so-action { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; flex-shrink: 0; }
.studio-opt-row .so-price { font-size: 11px; font-weight: 800; padding: 4px 9px; border-radius:var(--radius-pill); }
.studio-opt-row .so-arrow { width: 22px; height: 22px; border-radius: 50%; background: rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: center; }
.studio-opt-row .so-arrow svg { width: 10px; height: 10px; stroke: currentColor; stroke-width: 2.5; fill: none; stroke-linecap: round; stroke-linejoin: round; }

/* Color variants */
.studio-opt.bg { background: rgba(34,197,94,0.04); border-color: rgba(34,197,94,0.2); }
.studio-opt.bg:hover { background: rgba(34,197,94,0.08); border-color: rgba(34,197,94,0.4); transform: translateY(-1px); }
.studio-opt.bg .so-ico svg { stroke: #22c55e; }
.studio-opt.bg .so-price { background: rgba(34,197,94,0.15); color: hsl(141 79% 73%); }
.studio-opt.bg .so-arrow { color: hsl(141 79% 73%); }

.studio-opt.desc { background: rgba(20,184,166,0.04); border-color: rgba(20,184,166,0.22); }
.studio-opt.desc:hover { background: rgba(20,184,166,0.08); border-color: rgba(20,184,166,0.42); transform: translateY(-1px); }
.studio-opt.desc .so-ico svg { stroke: #2dd4bf; }
.studio-opt.desc .so-price { background: rgba(20,184,166,0.15); color: #5eead4; }
.studio-opt.desc .so-arrow { color: #5eead4; }

.studio-opt.clothes { background: rgba(99,102,241,0.04); border-color: rgba(99,102,241,0.22); }
.studio-opt.clothes:hover { background: rgba(99,102,241,0.08); border-color: rgba(99,102,241,0.42); transform: translateY(-1px); }
.studio-opt.clothes .so-ico svg { stroke: #a5b4fc; }
.studio-opt.clothes .so-price { background: rgba(99,102,241,0.18); color: #c4b5fd; }
.studio-opt.clothes .so-arrow { color: #a5b4fc; }

.studio-opt.lingerie { background: rgba(236,72,153,0.04); border-color: rgba(236,72,153,0.22); }
.studio-opt.lingerie:hover { background: rgba(236,72,153,0.08); border-color: rgba(236,72,153,0.45); transform: translateY(-1px); }
.studio-opt.lingerie .so-ico svg { stroke: #f9a8d4; }
.studio-opt.lingerie .so-price { background: rgba(236,72,153,0.18); color: #fbcfe8; }
.studio-opt.lingerie .so-arrow { color: #f9a8d4; }

.studio-opt.jewelry { background: rgba(234,179,8,0.04); border-color: rgba(234,179,8,0.22); }
.studio-opt.jewelry:hover { background: rgba(234,179,8,0.08); border-color: rgba(234,179,8,0.45); transform: translateY(-1px); }
.studio-opt.jewelry .so-ico svg { stroke: hsl(43 96% 56%); }
.studio-opt.jewelry .so-price { background: rgba(234,179,8,0.18); color: hsl(45 96% 64%); }
.studio-opt.jewelry .so-arrow { color: hsl(43 96% 56%); }

.studio-opt.acc { background: rgba(20,184,166,0.04); border-color: rgba(20,184,166,0.22); }
.studio-opt.acc:hover { background: rgba(20,184,166,0.08); border-color: rgba(20,184,166,0.45); transform: translateY(-1px); }
.studio-opt.acc .so-ico svg { stroke: #5eead4; }
.studio-opt.acc .so-price { background: rgba(20,184,166,0.18); color: #99f6e4; }
.studio-opt.acc .so-arrow { color: #5eead4; }

.studio-opt.other { background: rgba(139,92,246,0.04); border-color: rgba(139,92,246,0.22); }
.studio-opt.other:hover { background: rgba(139,92,246,0.08); border-color: rgba(139,92,246,0.45); transform: translateY(-1px); }
.studio-opt.other .so-ico svg { stroke: #c4b5fd; }
.studio-opt.other .so-price { background: rgba(139,92,246,0.18); color: #ddd6fe; }
.studio-opt.other .so-arrow { color: #c4b5fd; }

.studio-opt-row .so-count { font-size: 11px; font-weight: 800; padding: 4px 9px; border-radius:var(--radius-pill); flex-shrink: 0; }
.studio-opt-row .so-count b { font-size: 13px; }

/* ═══ HISTORY ═══ */
.history-strip { padding: 14px; margin-bottom: 12px; }
.history-strip > * { position: relative; z-index: 5; }
.hist-row { display: flex; gap: 7px; overflow-x: auto; scrollbar-width: none; padding-right: 2px; margin-top: 12px; }
.hist-row::-webkit-scrollbar { display: none; }
.hist-thumb { width: 60px; height: 60px; border-radius:var(--radius); flex-shrink: 0; position: relative; background: linear-gradient(170deg, hsl(220 30% 25%) 0%, hsl(220 30% 12%) 100%); border: 1px solid rgba(255,255,255,0.06); overflow: hidden; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 26px; }
.hist-thumb.real { background: linear-gradient(170deg, #fafafa 0%, #e0e0e0 100%); }
.hist-thumb-tag { position: absolute; bottom: 3px; right: 3px; width: 14px; height: 14px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 8px; color: white; font-weight: 900; box-shadow: 0 0 5px rgba(255,255,255,0.5), inset 0 1px 0 rgba(255,255,255,0.3); }
.hist-tag-clothes  { background: linear-gradient(135deg, #6366f1, #4f46e5); }
.hist-tag-lingerie { background: linear-gradient(135deg, #ec4899, #db2777); }
.hist-tag-jewelry  { background: linear-gradient(135deg, #f59e0b, #d97706); }
.hist-tag-acc      { background: linear-gradient(135deg, #14b8a6, #0d9488); }
.hist-tag-other    { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
.hist-empty { padding: 18px 12px; text-align: center; color: rgba(255,255,255,0.4); font-size: 12px; font-style: italic; }

/* ═══ SETTINGS rows ═══ */
.settings-row { padding: 10px 13px; margin-bottom: 6px; display: flex; align-items: center; gap: 10px; cursor: pointer; font-family: inherit; text-align: left; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius:var(--radius); width: 100%; transition: all 0.18s var(--ease); }
.settings-row:hover { background: rgba(99,102,241,0.06); border-color: rgba(99,102,241,0.3); }
.settings-row-ico { width: 32px; height: 32px; border-radius:var(--radius-sm); background: rgba(99,102,241,0.1); border: 0.5px solid rgba(99,102,241,0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.settings-row-ico svg { width: 14px; height: 14px; stroke: var(--indigo-300); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.settings-row-text { flex: 1; min-width: 0; }
.settings-row-title { font-size: 12px; font-weight: 700; color: var(--text-primary); line-height: 1.2; }
.settings-row-value { font-size: 10px; color: var(--indigo-300); font-weight: 600; margin-top: 2px; }
.settings-row-arrow svg { width: 11px; height: 11px; stroke: var(--text-secondary); fill: none; stroke-width: 2.5; stroke-linecap: round; }

/* ═══ FAB ═══ */
.fab-wrap { position: fixed; bottom: calc(72px + env(safe-area-inset-bottom, 0)); left: 50%; transform: translateX(-50%); z-index: 100; pointer-events: none; }
.fab { position: relative; width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, var(--indigo-500), var(--indigo-600)); border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 28px rgba(99,102,241,0.5), 0 8px 22px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.2); pointer-events: auto; }
.fab::before, .fab::after { content: ''; position: absolute; inset: -4px; border-radius: 50%; border: 2px solid rgba(99,102,241,0.4); animation: fab-ring 2.5s ease-out infinite; }
.fab::after { animation-delay: 1.25s; }
@keyframes fab-ring { 0% { transform: scale(0.9); opacity: 1; } 100% { transform: scale(1.5); opacity: 0; } }
.fab svg { width: 22px; height: 22px; stroke: white; stroke-width: 2; fill: none; stroke-linecap: round; stroke-linejoin: round; }

/* Anti-abuse banner */
.abuse-banner { display: flex; align-items: center; gap: 10px; padding: 11px 14px; margin-bottom: 12px; border-radius:var(--radius); }
.abuse-banner.soft { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.3); }
.abuse-banner.hard { background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.4); }
.abuse-banner-ico { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.abuse-banner.soft .abuse-banner-ico { background: rgba(245,158,11,0.2); color: hsl(43 96% 56%); }
.abuse-banner.hard .abuse-banner-ico { background: rgba(239,68,68,0.2); color: hsl(0 93% 82%); }
.abuse-banner-text { flex: 1; min-width: 0; }
.abuse-banner-title { font-size: 12px; font-weight: 800; line-height: 1.2; }
.abuse-banner.soft .abuse-banner-title { color: hsl(43 96% 56%); }
.abuse-banner.hard .abuse-banner-title { color: hsl(0 93% 82%); }
.abuse-banner-sub { font-size: 10px; color: rgba(255,255,255,0.55); margin-top: 2px; line-height: 1.3; }

/* Lock screen for FREE plan */
.studio-lock-card { padding: 24px 20px; text-align: center; margin-bottom: 12px; }
.studio-lock-card > * { position: relative; z-index: 5; }
.studio-lock-ico { width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, var(--indigo-600), var(--indigo-500)); display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 0 26px rgba(99,102,241,0.5); margin-bottom: 10px; }
.studio-lock-ico svg { width: 26px; height: 26px; stroke: #fff; stroke-width: 2; fill: none; }
.studio-lock-title { font-size: 18px; font-weight: 800; margin-bottom: 6px; }
.studio-lock-sub { font-size: 12px; color: var(--text-secondary); line-height: 1.5; max-width: 320px; margin: 0 auto 14px; }
.studio-lock-cta { padding: 13px 26px; border-radius:var(--radius-pill); background: linear-gradient(135deg, var(--indigo-500), var(--indigo-600)); color: #fff; border: none; font-size: 13px; font-weight: 800; cursor: pointer; font-family: inherit; box-shadow: 0 6px 22px rgba(124,58,237,0.4); }


/* ── S106: BICHROMATIC theme support (auto-injected) ── */
[data-theme="light"] body{background:var(--bg);color:var(--text)}
[data-theme="light"] .glass{background:var(--surface,rgba(255,255,255,.6));border-color:var(--border-color,rgba(0,0,0,.06))}
[data-theme="light"] h1,[data-theme="light"] h2,[data-theme="light"] h3{color:var(--text)}
[data-theme="dark"] body{background:var(--bg);color:var(--text)}
[data-theme="dark"] .glass{background:var(--surface,rgba(20,22,30,.55))}

@media (prefers-reduced-motion: reduce){
  *{transition:none!important;animation:none!important}
}

/* glass content stays above shine/glow spans */
.glass > *:not(.shine):not(.glow){position:relative;z-index:5}
</style>
</head>
<body class="rms-shell">

<?php include __DIR__ . '/partials/header.php'; ?>

<div class="app">

    <?php if ($is_locked): ?>
    <!-- ═══ FREE plan lock card ═══ -->
    <div class="glass studio-card studio-lock-card">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <div class="studio-lock-ico">
            <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <div class="studio-lock-title">AI Studio е в START план</div>
        <div class="studio-lock-sub">Активирай START — 50 безплатни AI снимки/месец, или 4 месеца триал PRO без карта (300 снимки/мес).</div>
        <a href="/billing.php" class="studio-lock-cta">Виж планове · 4 месеца безплатно</a>
    </div>
    <?php else: ?>

    <?php if (!empty($abuse['blocked'])): ?>
    <div class="abuse-banner hard">
        <div class="abuse-banner-ico">⚠</div>
        <div class="abuse-banner-text">
            <div class="abuse-banner-title">Дневният лимит за retry-и е достигнат</div>
            <div class="abuse-banner-sub">Quality Guarantee retries за днес: <?= (int)$abuse['retries_today'] ?>. Опитай утре или се свържи с поддръжка.</div>
        </div>
    </div>
    <?php elseif (!empty($abuse['soft_warning'])): ?>
    <div class="abuse-banner soft">
        <div class="abuse-banner-ico">!</div>
        <div class="abuse-banner-text">
            <div class="abuse-banner-title">Висок процент retry-и (<?= (int)round($abuse['retry_rate'] * 100) ?>%)</div>
            <div class="abuse-banner-sub">Ако качеството не отговаря — провери стандартните настройки или ни пиши за съвет.</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══ HERO BANNER ═══ -->
    <div class="glass studio-card hero-banner">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>
        <span class="glow glow-bright"></span><span class="glow glow-bright glow-bottom"></span>
        <div class="hero-banner-ico">
            <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </div>
        <div class="hero-banner-text">
            <h2>Преобрази каталога си</h2>
            <p>Бял фон · описания · модели · студийни снимки</p>
        </div>
    </div>

    <!-- ═══ CREDITS BAR ═══ -->
    <button class="credits-bar" type="button" onclick="aiStudioBuyCredits()">
        <span class="cr-plan"><?= htmlspecialchars($plan_label) ?></span>
        <div class="cr-content">
            <div class="cr-line">
                <span class="cr-item"><b><?= $bg_remaining ?></b> бял фон</span>
                <span class="cr-sep"></span>
                <span class="cr-item desc"><b><?= $desc_remaining ?></b> описания</span>
                <span class="cr-sep"></span>
                <span class="cr-item tryon"><b><?= $tryon_remaining ?></b> AI магия</span>
            </div>
            <div class="cr-sub">
                <?php if ($plan_eff === 'god'): ?>
                    Неограничени · god mode
                <?php else: ?>
                    от <?= $bg_total ?> / <?= $desc_total ?> / <?= $tryon_total ?> включени · купи още
                <?php endif; ?>
            </div>
        </div>
        <div class="cr-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div>
    </button>

    <!-- ═══ BULK ACTIONS ═══ -->
    <div class="glass studio-card">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>

        <div class="section-head">
            <div class="section-head-icon green">
                <svg viewBox="0 0 24 24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
            </div>
            <div class="section-head-text">
                <div class="section-head-title">Бързи действия</div>
                <div class="section-head-sub">Без настройка · автоматично за всички</div>
            </div>
            <div class="section-head-count"><?= ($bulk_bg_count > 0 ? 1 : 0) + ($bulk_desc_count > 0 ? 1 : 0) ?></div>
        </div>

        <?php if ($bulk_bg_count > 0): ?>
        <button class="studio-opt bg" type="button" onclick="aiStudioBulkBg()">
            <div class="studio-opt-row">
                <div class="so-ico"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg></div>
                <div class="so-text">
                    <div class="so-t"><?= $bulk_bg_count ?> продукта без бял фон</div>
                    <div class="so-s">birefnet · автоматично · ~<?= max(1, (int)round($bulk_bg_count * 0.25)) ?> мин</div>
                </div>
                <div class="so-action">
                    <span class="so-price">€<?= number_format($bulk_bg_cost, 2) ?></span>
                    <span class="so-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></span>
                </div>
            </div>
        </button>
        <?php endif; ?>

        <?php if ($bulk_desc_count > 0): ?>
        <button class="studio-opt desc" type="button" onclick="aiStudioBulkDesc()">
            <div class="studio-opt-row">
                <div class="so-ico"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
                <div class="so-text">
                    <div class="so-t"><?= $bulk_desc_count ?> продукта без описание</div>
                    <div class="so-s">AI · BG/EN/RO · ~<?= max(1, (int)round($bulk_desc_count * 0.05)) ?> мин</div>
                </div>
                <div class="so-action">
                    <span class="so-price">€<?= number_format($bulk_desc_cost, 2) ?></span>
                    <span class="so-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></span>
                </div>
            </div>
        </button>
        <?php endif; ?>

        <?php if ($bulk_bg_count === 0 && $bulk_desc_count === 0): ?>
        <div style="padding:18px 12px;text-align:center;color:rgba(255,255,255,0.4);font-size:12px;font-style:italic">
            Всички продукти имат бял фон и описание. ✓
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══ AI MAGIC PER CATEGORY ═══ -->
    <div class="glass studio-card">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>

        <div class="section-head">
            <div class="section-head-icon">
                <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div class="section-head-text">
                <div class="section-head-title">AI магия по категории</div>
                <div class="section-head-sub">Облечи на модел · студийна снимка</div>
            </div>
            <div class="section-head-count"><?= $category_total ?></div>
        </div>

        <?php
        $hue_pills = [
            'clothes'  => ['bg' => 'rgba(99,102,241,0.15)',  'fg' => '#c4b5fd'],
            'lingerie' => ['bg' => 'rgba(236,72,153,0.18)',  'fg' => '#fbcfe8'],
            'jewelry'  => ['bg' => 'rgba(234,179,8,0.18)',   'fg' => 'hsl(45 96% 64%)'],
            'acc'      => ['bg' => 'rgba(20,184,166,0.18)',  'fg' => '#99f6e4'],
            'other'    => ['bg' => 'rgba(139,92,246,0.18)',  'fg' => '#ddd6fe'],
        ];
        foreach ($AI_CATEGORIES as $cat):
            $pill = $hue_pills[$cat['key']];
        ?>
        <button class="studio-opt <?= htmlspecialchars($cat['key']) ?>" type="button" onclick="aiStudioOpenCategory('<?= htmlspecialchars($cat['key']) ?>')">
            <div class="studio-opt-row">
                <div class="so-ico" style="font-size:20px"><?= $cat['emoji'] ?></div>
                <div class="so-text">
                    <div class="so-t"><?= htmlspecialchars($cat['label']) ?></div>
                    <div class="so-s"><?= htmlspecialchars($cat['sub']) ?></div>
                </div>
                <div class="so-action">
                    <span class="so-count" style="background:<?= $pill['bg'] ?>;color:<?= $pill['fg'] ?>"><b><?= $cat['count'] ?></b> чакат</span>
                    <span class="so-arrow"><svg viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></span>
                </div>
            </div>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- ═══ HISTORY ═══ -->
    <div class="glass studio-card history-strip">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>

        <div class="section-head">
            <div class="section-head-icon amber">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="section-head-text">
                <div class="section-head-title">Последно генерирано</div>
                <div class="section-head-sub">Последните 8 от историята</div>
            </div>
            <?php if (count($history) > 0): ?>
            <a href="/ai-history.php" class="section-head-count" style="background:rgba(234,179,8,0.12);color:hsl(45 96% 64%);border-color:rgba(234,179,8,0.28);text-decoration:none">виж всички →</a>
            <?php endif; ?>
        </div>

        <?php if (empty($history)): ?>
        <div class="hist-empty">Все още нямаш история. Tap някоя категория или „Бързи действия", за да започнеш.</div>
        <?php else: ?>
        <div class="hist-row">
            <?php foreach ($history as $h):
                $feat   = (string)($h['feature']  ?? '');
                $hcat   = (string)($h['category'] ?? '');
                $status = (string)($h['status']   ?? '');
                $badge  = $OP_BADGE_MAP[$feat] ?? ['emoji' => '✨', 'tag' => 'other'];
                if ($hcat !== '' && isset($VALID_TAGS[$hcat])) $badge['tag'] = $hcat;
                if ($status === 'refunded_loss') $badge['emoji'] = '↩';
                $title = trim($feat . ($hcat ? " · $hcat" : '') . ' · ' . substr((string)($h['created_at'] ?? ''), 0, 10));
            ?>
            <div class="hist-thumb real" title="<?= htmlspecialchars($title) ?>">
                <?= $badge['emoji'] ?>
                <div class="hist-thumb-tag hist-tag-<?= htmlspecialchars($badge['tag']) ?>">✨</div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══ SETTINGS ═══ -->
    <div class="glass studio-card">
        <span class="shine"></span><span class="shine shine-bottom"></span>
        <span class="glow"></span><span class="glow glow-bottom"></span>

        <div class="section-head">
            <div class="section-head-icon" style="background:linear-gradient(135deg,#6b7280,#4b5563);border-color:#9ca3af;box-shadow:0 0 18px rgba(107,114,128,0.35)">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
            </div>
            <div class="section-head-text">
                <div class="section-head-title">Стандартни настройки</div>
                <div class="section-head-sub">При „Стандартно" режим</div>
            </div>
        </div>

        <a class="settings-row" href="/settings.php#ai-defaults">
            <div class="settings-row-ico"><svg viewBox="0 0 24 24"><circle cx="12" cy="6" r="3"/><path d="M5 21v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/></svg></div>
            <div class="settings-row-text">
                <div class="settings-row-title">Стандартен модел</div>
                <div class="settings-row-value">Жена · 25-30 г · европейка</div>
            </div>
            <span class="settings-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
        </a>

        <a class="settings-row" href="/settings.php#ai-defaults">
            <div class="settings-row-ico"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/></svg></div>
            <div class="settings-row-text">
                <div class="settings-row-title">Стандартен фон</div>
                <div class="settings-row-value">Бял студиен</div>
            </div>
            <span class="settings-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
        </a>

        <a class="settings-row" href="/settings.php#ai-defaults">
            <div class="settings-row-ico"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
            <div class="settings-row-text">
                <div class="settings-row-title">Quality Guarantee</div>
                <div class="settings-row-value">2 безплатни retry-а · refund при block</div>
            </div>
            <span class="settings-row-arrow"><svg viewBox="0 0 24 24" fill="none" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg></span>
        </a>
    </div>

    <?php endif; /* end of !is_locked block */ ?>

</div>

<!-- FAB -->
<div class="fab-wrap">
    <button class="fab" aria-label="Попитай AI" onclick="window.location.href='chat.php'"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg></button>
</div>

<?php include __DIR__ . '/partials/bottom-nav.php'; ?>

<script>
// Haptic feedback on every tap
document.querySelectorAll('button, .settings-row, .credits-bar, .studio-opt').forEach(el => {
    el.addEventListener('click', () => { if (navigator.vibrate) navigator.vibrate(8); });
});

// Stubs — STUDIO.12-17 will wire these up to real backend calls.
function aiStudioBuyCredits() {
    alert('Купи кредити — модалът идва в STUDIO.17 (3 пакета €5/€15/€40).');
}
function aiStudioBulkBg() {
    if (confirm('Ще махна фона на всички продукти. Продължи? (заявката се изпраща в опашка)')) {
        alert('Bulk bg removal — backend идва в STUDIO.16.');
    }
}
function aiStudioBulkDesc() {
    if (confirm('Ще генерирам описания за всички продукти. Продължи?')) {
        alert('Bulk descriptions — backend идва в STUDIO.16.');
    }
}
function aiStudioOpenCategory(cat) {
    alert('Категорията "' + cat + '" — queue list overlay идва в STUDIO.14.\n(Tap на продукт ще отваря per-product модала от STUDIO.12.)');
}
</script>

<?php include __DIR__ . '/partials/shell-scripts.php'; ?>
</body>
</html>
