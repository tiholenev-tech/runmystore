<?php
// S95.VOICE_LAB: standalone testing sandbox за Whisper STT настройки.
// Reuses /services/voice-tier2.php без модификация. Zero edits в products.php.
declare(strict_types=1);
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: /login.php'); exit; }
$user_role = $_SESSION['role'] ?? 'seller';
if ($user_role !== 'owner') { header('Location: /products.php?err=owner_only'); exit; }
$user_name = htmlspecialchars((string)($_SESSION['name'] ?? ''), ENT_QUOTES);
?><!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>VOICE LAB — RunMyStore</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/design-kit/tokens.css">
<link rel="stylesheet" href="/design-kit/components.css">
<link rel="stylesheet" href="/css/theme.css">
<style>
  * { box-sizing: border-box; }
  body { font-family: Montserrat, system-ui, sans-serif; color: #e8e8f4; margin: 0; padding: 0; min-height: 100vh; }
  .app { max-width: 480px; margin: 0 auto; padding: 0 12px 32px; position: relative; z-index: 2; }
  .vl-header { position: sticky; top: 0; z-index: 50; display: flex; align-items: center; gap: 8px; padding: max(8px, calc(env(safe-area-inset-top, 0px) + 8px)) 4px 10px; backdrop-filter: blur(10px); background: rgba(10,11,20,0.55); }
  .vl-back, .vl-reset { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); color: #cbd1ff; border-radius: 12px; height: 36px; min-width: 36px; padding: 0 10px; cursor: pointer; font: inherit; font-weight: 800; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 4px; text-decoration: none; }
  .vl-back svg, .vl-reset svg { width: 18px; height: 18px; stroke-width: 2.4; }
  .vl-title { flex: 1; text-align: center; font-weight: 900; letter-spacing: 0.08em; font-size: 13px; color: #f1f5f9; }
  .vl-card { margin-top: 12px; padding: 14px 14px 16px; }
  .vl-card > * { position: relative; z-index: 5; }
  .vl-card h2 { font-size: 12px; font-weight: 900; letter-spacing: 0.06em; margin: 0 0 12px; color: #f1f5f9; text-transform: uppercase; }
  .vl-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: 8px 0; min-height: 32px; }
  .vl-row label { font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.78); flex: 0 0 auto; min-width: 110px; }
  .vl-row .vl-val { font-size: 11px; font-weight: 800; color: #cbd1ff; min-width: 78px; text-align: right; font-variant-numeric: tabular-nums; }
  .vl-row select, .vl-row textarea, .vl-row input[type=text] { background: rgba(0,0,0,0.30); border: 1px solid rgba(255,255,255,0.12); color: #e8e8f4; border-radius: 10px; padding: 10px 12px; font: inherit; font-size: 13px; font-weight: 700; flex: 1; min-height: 44px; outline: none; }
  .vl-row textarea { min-height: 60px; resize: vertical; flex-basis: 100%; }
  .vl-row input[type=range] { flex: 1; height: 48px; -webkit-appearance: none; appearance: none; background: transparent; cursor: pointer; }
  .vl-row input[type=range]::-webkit-slider-runnable-track { height: 6px; border-radius: 6px; background: linear-gradient(90deg, hsl(var(--hue1, 255), 70%, 55%, 0.85), hsl(var(--hue2, 222), 70%, 45%, 0.85)); }
  .vl-row input[type=range]::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 22px; height: 22px; border-radius: 50%; background: #f1f5f9; border: 2px solid hsl(var(--hue1, 255), 90%, 65%); margin-top: -8px; box-shadow: 0 0 12px hsl(var(--hue1, 255), 80%, 60%, 0.65); cursor: pointer; }
  .vl-row input[type=range]::-moz-range-track { height: 6px; border-radius: 6px; background: linear-gradient(90deg, hsl(var(--hue1, 255), 70%, 55%, 0.85), hsl(var(--hue2, 222), 70%, 45%, 0.85)); }
  .vl-row input[type=range]::-moz-range-thumb { width: 22px; height: 22px; border-radius: 50%; background: #f1f5f9; border: 2px solid hsl(var(--hue1, 255), 90%, 65%); box-shadow: 0 0 12px hsl(var(--hue1, 255), 80%, 60%, 0.65); cursor: pointer; }
  .vl-radio { display: flex; gap: 6px; flex: 1; flex-wrap: wrap; }
  .vl-radio label { display: inline-flex; align-items: center; gap: 4px; padding: 8px 10px; border-radius: 10px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.10); font-size: 11px; font-weight: 700; cursor: pointer; min-height: 36px; min-width: 0; }
  .vl-radio input { accent-color: hsl(var(--hue1, 255), 80%, 60%); }
  .vl-rec { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 18px 14px 22px; }
  .vl-rec-btn { width: 220px; max-width: 90%; height: 78px; border-radius: 100px; border: 2px solid hsl(var(--hue1, 255), 75%, 60%, 0.85); background: linear-gradient(135deg, hsl(var(--hue1, 255), 70%, 38%), hsl(var(--hue2, 222), 70%, 32%)); color: #f1f5f9; font: inherit; font-weight: 900; font-size: 16px; letter-spacing: 0.08em; cursor: pointer; box-shadow: 0 0 24px hsl(var(--hue1, 255), 75%, 55%, 0.55), inset 0 1px 0 hsl(var(--hue1, 255), 80%, 70%, 0.30); transition: transform 0.08s ease, box-shadow 0.2s ease; text-shadow: 0 0 10px hsl(var(--hue1, 255), 90%, 70%, 0.6); }
  .vl-rec-btn:active { transform: translateY(1px); }
  .vl-rec-btn.recording { background: linear-gradient(135deg, hsl(0, 75%, 45%), hsl(15, 75%, 38%)); border-color: hsl(0, 80%, 60%); box-shadow: 0 0 32px hsl(0, 80%, 55%, 0.7), inset 0 1px 0 hsl(0, 90%, 75%, 0.30); animation: vl-pulse 0.9s ease-in-out infinite; }
  @keyframes vl-pulse { 0%, 100% { box-shadow: 0 0 32px hsl(0, 80%, 55%, 0.65); } 50% { box-shadow: 0 0 44px hsl(0, 90%, 65%, 0.95); } }
  .vl-rec-hint { font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.65); text-align: center; }
  .vl-wave { width: 100%; height: 72px; border-radius: 10px; background: rgba(0,0,0,0.45); border: 1px solid rgba(255,255,255,0.08); display: block; }
  .vl-stat { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 14px; font-size: 11px; font-weight: 700; color: rgba(255,255,255,0.78); margin-top: 8px; }
  .vl-stat b { color: #f1f5f9; font-weight: 900; font-variant-numeric: tabular-nums; }
  .vl-result { font-size: 12px; line-height: 1.65; }
  .vl-result .k { color: rgba(255,255,255,0.55); font-weight: 700; display: inline-block; min-width: 64px; }
  .vl-result .v { color: #f1f5f9; font-weight: 800; }
  .vl-result .v.ok { color: #86efac; }
  .vl-result .v.warn { color: #fde68a; }
  .vl-result .v.bad { color: #fca5a5; }
  .vl-bug { display: flex; flex-direction: column; gap: 6px; }
  .vl-bug div { display: flex; gap: 8px; align-items: center; font-size: 11px; font-weight: 700; padding: 6px 8px; border-radius: 8px; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.06); }
  .vl-bug div.ok { color: #86efac; }
  .vl-bug div.warn { color: #fde68a; }
  .vl-bug div.bad { color: #fca5a5; background: rgba(239,68,68,0.10); border-color: rgba(239,68,68,0.25); }
  .vl-bug .ico { width: 14px; text-align: center; }
  .vl-history { width: 100%; border-collapse: collapse; font-size: 10px; font-weight: 700; }
  .vl-history th, .vl-history td { padding: 6px 4px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.06); white-space: nowrap; }
  .vl-history th { color: rgba(255,255,255,0.55); font-weight: 800; font-size: 9px; text-transform: uppercase; letter-spacing: 0.06em; }
  .vl-history td { color: #e8e8f4; }
  .vl-history td.tx { white-space: normal; max-width: 110px; overflow: hidden; text-overflow: ellipsis; }
  .vl-history-actions { display: flex; gap: 8px; margin-top: 10px; }
  .vl-btn { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); color: #cbd1ff; border-radius: 100px; padding: 8px 14px; font: inherit; font-weight: 800; font-size: 11px; cursor: pointer; min-height: 36px; }
  .vl-btn.danger { color: #fca5a5; border-color: rgba(239,68,68,0.25); background: rgba(239,68,68,0.08); }
  .vl-empty { font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.45); text-align: center; padding: 14px 0; }
</style>
</head>
<body>
<div class="app">
  <header class="vl-header">
    <a class="vl-back" href="/products.php" title="Назад">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
    <div class="vl-title">VOICE LAB</div>
    <button class="vl-reset" type="button" id="vlReset" title="Reset настройки">RESET</button>
  </header>

  <!-- SETTINGS -->
  <section class="glass q-magic vl-card">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <h2>⚙️ НАСТРОЙКИ</h2>

    <div class="vl-row">
      <label>Locale</label>
      <select id="cfgLocale">
        <option value="bg">bg — Български</option>
        <option value="ro">ro — Română</option>
        <option value="el">el — Ελληνικά</option>
        <option value="sr">sr — Srpski</option>
        <option value="hr">hr — Hrvatski</option>
      </select>
    </div>

    <div class="vl-row">
      <label>Field profile</label>
      <select id="cfgProfile">
        <option value="retail_price">retail_price</option>
        <option value="cost_price">cost_price</option>
        <option value="wholesale_price">wholesale_price</option>
        <option value="quantity">quantity</option>
        <option value="min_quantity">min_quantity</option>
        <option value="barcode">barcode</option>
        <option value="name">name</option>
      </select>
    </div>

    <div class="vl-row">
      <label>VAD silence</label>
      <input type="range" id="cfgSilence" min="200" max="3000" step="50" value="1500">
      <span class="vl-val" id="cfgSilenceV">1500 ms</span>
    </div>
    <div class="vl-row">
      <label>VAD threshold</label>
      <input type="range" id="cfgThresh" min="0.001" max="0.080" step="0.001" value="0.015">
      <span class="vl-val" id="cfgThreshV">0.015 RMS</span>
    </div>
    <div class="vl-row">
      <label>Pre-buffer</label>
      <input type="range" id="cfgPrebuf" min="0" max="800" step="50" value="200">
      <span class="vl-val" id="cfgPrebufV">200 ms</span>
    </div>
    <div class="vl-row">
      <label>Timeout cap</label>
      <input type="range" id="cfgTimeout" min="2000" max="15000" step="500" value="8000">
      <span class="vl-val" id="cfgTimeoutV">8000 ms</span>
    </div>
    <div class="vl-row">
      <label>Confidence</label>
      <input type="range" id="cfgConf" min="0.30" max="0.95" step="0.01" value="0.70">
      <span class="vl-val" id="cfgConfV">0.70</span>
    </div>

    <div class="vl-row">
      <label>Hints</label>
      <textarea id="cfgHints" placeholder="дума1, дума2, контекст за Whisper (optional)"></textarea>
    </div>

    <div class="vl-row">
      <label>Backend</label>
      <div class="vl-radio">
        <label><input type="radio" name="cfgBackend" value="whisper" checked> Whisper</label>
        <label><input type="radio" name="cfgBackend" value="webspeech"> Web Speech</label>
        <label><input type="radio" name="cfgBackend" value="ab"> Both A/B</label>
      </div>
    </div>
  </section>

  <!-- RECORD -->
  <section class="glass q-default vl-card vl-rec">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <button class="vl-rec-btn" id="vlRec" type="button">🎤 ЗАПИСВАЙ</button>
    <div class="vl-rec-hint" id="vlRecHint">tap за старт · auto-stop при тишина</div>
  </section>

  <!-- LIVE DEBUG -->
  <section class="glass q-default vl-card">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <h2>📊 LIVE DEBUG</h2>
    <canvas class="vl-wave" id="vlWave" width="440" height="72"></canvas>
    <div class="vl-stat">
      <div>RMS now: <b id="dbgRms">0.000</b></div>
      <div>peak: <b id="dbgPeak">0.000</b></div>
      <div>Duration: <b id="dbgDur">0</b> ms</div>
      <div>POST latency: <b id="dbgLat">—</b></div>
      <div style="grid-column: 1 / -1;">Engine: <b id="dbgEng">—</b></div>
    </div>
  </section>

  <!-- RESULT -->
  <section class="glass q-default vl-card" id="vlResultCard">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <h2>📝 РЕЗУЛТАТ</h2>
    <div class="vl-result">
      <div><span class="k">Raw:</span> <span class="v" id="resRaw">—</span></div>
      <div><span class="k">Norm:</span> <span class="v" id="resNorm">—</span></div>
      <div><span class="k">Parsed:</span> <span class="v" id="resParsed">—</span></div>
      <div><span class="k">Conf:</span> <span class="v" id="resConf">—</span></div>
    </div>
  </section>

  <!-- BUG DETECTORS -->
  <section class="glass q-amber vl-card">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <h2>🐛 BUG DETECTORS</h2>
    <div class="vl-bug" id="vlBugs">
      <div class="ok"><span class="ico">·</span><span>Изпрати запис за анализ</span></div>
    </div>
  </section>

  <!-- HISTORY -->
  <section class="glass q-default vl-card">
    <span class="shine"></span><span class="shine shine-bottom"></span>
    <span class="glow"></span><span class="glow glow-bottom"></span>
    <h2>📚 ИСТОРИЯ (last 20)</h2>
    <div id="vlHistWrap"><div class="vl-empty">няма опити още</div></div>
    <div class="vl-history-actions">
      <button class="vl-btn" id="vlExport" type="button">Export JSON</button>
      <button class="vl-btn danger" id="vlClear" type="button">Clear</button>
    </div>
  </section>
</div>

<script>
'use strict';

// ─── Settings persistence ─────────────────────────────────────────────────
const LS_SETTINGS = 'voice_lab_settings';
const LS_HISTORY  = 'voice_lab_history';
const DEFAULTS = {
  locale: 'bg', profile: 'retail_price',
  silence_ms: 1500, threshold: 0.015, prebuf_ms: 200,
  timeout_ms: 8000, conf_cutoff: 0.70, hints: '', backend: 'whisper'
};
const $ = (id) => document.getElementById(id);

function loadCfg() {
  let saved = {};
  try { saved = JSON.parse(localStorage.getItem(LS_SETTINGS) || '{}') || {}; } catch (e) {}
  return Object.assign({}, DEFAULTS, saved);
}
function saveCfg() { try { localStorage.setItem(LS_SETTINGS, JSON.stringify(getCfg())); } catch (e) {} }
function getCfg() {
  return {
    locale: $('cfgLocale').value, profile: $('cfgProfile').value,
    silence_ms: +$('cfgSilence').value, threshold: +$('cfgThresh').value,
    prebuf_ms: +$('cfgPrebuf').value, timeout_ms: +$('cfgTimeout').value,
    conf_cutoff: +$('cfgConf').value, hints: $('cfgHints').value,
    backend: document.querySelector('input[name=cfgBackend]:checked').value
  };
}
function applyCfg(c) {
  $('cfgLocale').value = c.locale; $('cfgProfile').value = c.profile;
  $('cfgSilence').value = c.silence_ms; $('cfgThresh').value = c.threshold;
  $('cfgPrebuf').value = c.prebuf_ms; $('cfgTimeout').value = c.timeout_ms;
  $('cfgConf').value = c.conf_cutoff; $('cfgHints').value = c.hints;
  const r = document.querySelector('input[name=cfgBackend][value="' + c.backend + '"]');
  if (r) r.checked = true;
  refreshLabels();
}
function refreshLabels() {
  $('cfgSilenceV').textContent = $('cfgSilence').value + ' ms';
  $('cfgThreshV').textContent  = (+$('cfgThresh').value).toFixed(3) + ' RMS';
  $('cfgPrebufV').textContent  = $('cfgPrebuf').value + ' ms';
  $('cfgTimeoutV').textContent = $('cfgTimeout').value + ' ms';
  $('cfgConfV').textContent    = (+$('cfgConf').value).toFixed(2);
}
applyCfg(loadCfg());
['cfgLocale','cfgProfile','cfgSilence','cfgThresh','cfgPrebuf','cfgTimeout','cfgConf','cfgHints'].forEach(id => {
  $(id).addEventListener('input', () => { refreshLabels(); saveCfg(); });
  $(id).addEventListener('change', saveCfg);
});
document.querySelectorAll('input[name=cfgBackend]').forEach(r => r.addEventListener('change', saveCfg));
$('vlReset').addEventListener('click', () => {
  if (!confirm('Reset всички настройки към default?')) return;
  applyCfg(DEFAULTS); saveCfg();
});

// ─── Bulgarian price/quantity parser (copy от products.php — read-only) ───
const _BG_WORD_NUMS = {
  'четиринадесет':'14','четиринайсет':'14','четиринайсе':'14','четиридесет':'40','четирийсет':'40','четирсе':'40','четирсет':'40',
  'четиристотин':'400','четири':'4',
  'седемнадесет':'17','седемнайсет':'17','седемнайсе':'17','седемстотин':'700','седемдесет':'70','седемсе':'70','седемсет':'70','седем':'7',
  'осемнадесет':'18','осемнайсет':'18','осемнайсе':'18','осемстотин':'800','осемдесет':'80','осемсе':'80','осемсет':'80','осем':'8',
  'деветнадесет':'19','деветнайсет':'19','деветнайсе':'19','деветстотин':'900','деветдесет':'90','деветсе':'90','деветсет':'90','девет':'9',
  'дванадесет':'12','дванайсет':'12','дванайсе':'12','двадесет':'20','двайсет':'20','двайсе':'20','двайз':'20','двайс':'20',
  'тринадесет':'13','тринайсет':'13','тринайсе':'13','тридесет':'30','трийсет':'30','трийсе':'30','триисет':'30','триисе':'30','триста':'300','три':'3',
  'единадесет':'11','единайсет':'11','единайсе':'11','първа':'1','първи':'1','първо':'1','един':'1','една':'1','едно':'1',
  'петнадесет':'15','петнайсет':'15','петнайсе':'15','петстотин':'500','петдесет':'50','педесе':'50','педесет':'50','пет':'5',
  'шестнадесет':'16','шестнайсет':'16','шестнайсе':'16','шестстотин':'600','шестдесет':'60','шейсе':'60','шейсет':'60','шест':'6',
  'двеста':'200','втора':'2','втори':'2','второ':'2','две':'2','два':'2',
  'хиляда':'1000','хиляди':'1000','сто':'100','десет':'10','нула':'0','половин':'0.5','половинка':'0.5'
};
const _BG_WORD_KEYS = Object.keys(_BG_WORD_NUMS).sort((a,b) => b.length - a.length);

const _CYR_B = "(?<![\u0400-\u04FF0-9])";
const _CYR_A = "(?![\u0400-\u04FF0-9])";
function _wizPriceParse(text) {
  if (text == null) return null;
  let raw = String(text).toLowerCase().trim();
  if (!raw) return null;
  raw = raw.replace(/[.,!?;:\u201E\u201C\u201D]/g, ' ').replace(/\s+/g, ' ').trim();
  if (/^и\s/i.test(raw)) raw = '1 ' + raw.substring(2);
  const hasStotinki = new RegExp(_CYR_B + '(стотинки?|стот|цент[аи]?|cents?|копейк|пени)' + _CYR_A, 'i').test(raw);
  let pre = raw.replace(new RegExp(_CYR_B + 'запетая' + _CYR_A, 'gi'), ',').replace(new RegExp(_CYR_B + 'точка' + _CYR_A, 'gi'), '.');
  for (const k of _BG_WORD_KEYS) pre = pre.replace(new RegExp(_CYR_B + k + _CYR_A, 'gi'), ' ' + _BG_WORD_NUMS[k] + ' ');
  pre = pre.replace(/\s+/g, ' ').trim();
  const _FILLER = '(лева?|лв|евро|€|eur|euro|usd|gbp|ron|lei|лей|стотинки?|стот|цент[аи]?|cents?|пени|пенс|сантим[аи]?|копейк[аи]?|около|примерно|горе|долу|май|по|и|на|за|от)';
  const cleaned = pre.replace(new RegExp(_CYR_B + _FILLER + _CYR_A, 'gi'), ' ').replace(/[$£]/g, ' ').replace(/\s+/g, ' ').trim();
  const nums = cleaned.match(/\d+(?:[.,]\d+)?/g);
  if (!nums || !nums.length) return null;
  const first = nums[0].replace(',', '.');
  if (first.indexOf('.') >= 0) { const f = parseFloat(first); if (!isNaN(f)) return f; }
  const n0 = parseFloat(first); if (isNaN(n0)) return null;
  if (nums.length === 1) return n0;
  const n1 = parseFloat(nums[1].replace(',', '.')); if (isNaN(n1)) return n0;
  if (nums.length >= 3) {
    const n2 = parseFloat(nums[2].replace(',', '.'));
    if (!isNaN(n2) && n1 >= 10 && n1 <= 90 && n1 % 10 === 0 && n2 >= 1 && n2 <= 9) return parseFloat(n0 + '.' + String(n1 + n2).padStart(2, '0'));
  }
  if (!hasStotinki && n0 >= 10 && n0 < 100 && n0 % 10 === 0 && n1 >= 1 && n1 < 10) return n0 + n1;
  if (n0 >= 100 && !hasStotinki) return n0 + n1;
  if (n1 < 100) return parseFloat(n0 + '.' + String(Math.round(n1)).padStart(2, '0'));
  return n0 + n1 / Math.pow(10, String(Math.round(n1)).length);
}

function applyProfile(profile, transcript) {
  const t = (transcript || '').trim();
  if (!t) return null;
  if (profile === 'name') return t;
  if (profile === 'barcode') { const d = t.replace(/\D+/g, ''); return d || null; }
  if (profile === 'quantity' || profile === 'min_quantity') {
    const n = _wizPriceParse(t);
    if (n !== null && n >= 0) return Math.max(0, Math.round(n));
    const fallback = parseInt(t.replace(/[^\d]/g, ''), 10);
    return isNaN(fallback) ? null : fallback;
  }
  return _wizPriceParse(t);
}

// ─── Audio capture pipeline ───────────────────────────────────────────────
let audioCtx = null, mediaStream = null, srcNode = null, procNode = null;
let preBuf = null, preBufPos = 0, preBufFilled = false;
let isRecording = false, activeChunks = [], activeFrames = 0;
let silenceFrames = 0, peakRms = 0;
let recStartTs = 0, timeoutTimer = null, abortCtrl = null;
let waveCtx = null;

async function ensureAudio() {
  if (audioCtx && mediaStream && procNode) return;
  mediaStream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true } });
  audioCtx = new (window.AudioContext || window.webkitAudioContext)();
  if (audioCtx.state === 'suspended') await audioCtx.resume();
  srcNode = audioCtx.createMediaStreamSource(mediaStream);
  procNode = audioCtx.createScriptProcessor(2048, 1, 1);
  procNode.onaudioprocess = onAudioFrame;
  srcNode.connect(procNode);
  procNode.connect(audioCtx.destination);
  // pre-buffer ring (sized to current cfg)
  const cfg = getCfg();
  const sz = Math.max(0, Math.floor(audioCtx.sampleRate * cfg.prebuf_ms / 1000));
  preBuf = sz > 0 ? new Float32Array(sz) : null; preBufPos = 0; preBufFilled = false;
}

function onAudioFrame(e) {
  const input = e.inputBuffer.getChannelData(0);
  // RMS
  let sum = 0; for (let i = 0; i < input.length; i++) sum += input[i] * input[i];
  const rms = Math.sqrt(sum / input.length);
  if (rms > peakRms) peakRms = rms;
  $('dbgRms').textContent = rms.toFixed(3);
  $('dbgPeak').textContent = peakRms.toFixed(3);
  drawWave(input);

  if (isRecording) {
    activeChunks.push(new Float32Array(input)); activeFrames += input.length;
    const cfg = getCfg();
    const frameMs = (input.length / audioCtx.sampleRate) * 1000;
    $('dbgDur').textContent = Math.round((activeFrames / audioCtx.sampleRate) * 1000);
    if (rms < cfg.threshold) silenceFrames += frameMs; else silenceFrames = 0;
    // need at least 300ms speech before VAD can stop us
    const recMs = (activeFrames / audioCtx.sampleRate) * 1000;
    if (recMs > 300 && silenceFrames >= cfg.silence_ms) stopRecording('vad');
  } else if (preBuf) {
    for (let i = 0; i < input.length; i++) {
      preBuf[preBufPos] = input[i]; preBufPos++;
      if (preBufPos >= preBuf.length) { preBufPos = 0; preBufFilled = true; }
    }
  }
}

function drawWave(samples) {
  if (!waveCtx) {
    const cv = $('vlWave'); const dpr = window.devicePixelRatio || 1;
    cv.width = cv.clientWidth * dpr; cv.height = cv.clientHeight * dpr;
    waveCtx = cv.getContext('2d'); waveCtx.scale(dpr, dpr);
  }
  const cv = $('vlWave'); const w = cv.clientWidth, h = cv.clientHeight;
  waveCtx.clearRect(0, 0, w, h);
  waveCtx.strokeStyle = isRecording ? 'hsl(0, 80%, 65%)' : 'hsl(255, 75%, 70%)';
  waveCtx.lineWidth = 1.4;
  waveCtx.beginPath();
  const step = Math.max(1, Math.floor(samples.length / w));
  for (let x = 0; x < w; x++) {
    const s = samples[x * step] || 0;
    const y = h / 2 + s * (h / 2) * 1.6;
    if (x === 0) waveCtx.moveTo(x, y); else waveCtx.lineTo(x, y);
  }
  waveCtx.stroke();
}

async function startRecording() {
  if (isRecording) return;
  try { await ensureAudio(); }
  catch (e) { setBugs([{ s: 'bad', t: 'микрофонът не може да се отвори: ' + (e.message || e) }]); return; }
  const cfg = getCfg();
  if (cfg.backend === 'webspeech') {
    setBugs([{ s: 'warn', t: 'Web Speech mode: TODO в V2 — ползвай Whisper за тестове' }]);
    return;
  }
  isRecording = true; activeChunks = []; activeFrames = 0; silenceFrames = 0; peakRms = 0;
  recStartTs = performance.now();
  $('vlRec').classList.add('recording'); $('vlRec').textContent = '⏹ СПРИ';
  $('vlRecHint').textContent = 'recording…';
  if (timeoutTimer) clearTimeout(timeoutTimer);
  timeoutTimer = setTimeout(() => stopRecording('timeout'), cfg.timeout_ms);
}

function stopRecording(reason) {
  if (!isRecording) return;
  isRecording = false;
  if (timeoutTimer) { clearTimeout(timeoutTimer); timeoutTimer = null; }
  $('vlRec').classList.remove('recording'); $('vlRec').textContent = '🎤 ЗАПИСВАЙ';
  $('vlRecHint').textContent = 'tap за старт · auto-stop при тишина';

  if (!activeChunks.length) { setBugs([{ s: 'bad', t: 'празен запис — провери микрофон' }]); return; }
  const cfg = getCfg();
  const sr = audioCtx.sampleRate;

  // Concat pre-buffer (in correct order) + active chunks
  let preLen = 0; let preOrdered = null;
  if (preBuf && (preBufFilled || preBufPos > 0)) {
    const useFilled = preBufFilled;
    preOrdered = new Float32Array(useFilled ? preBuf.length : preBufPos);
    if (useFilled) { preOrdered.set(preBuf.subarray(preBufPos)); preOrdered.set(preBuf.subarray(0, preBufPos), preBuf.length - preBufPos); }
    else preOrdered.set(preBuf.subarray(0, preBufPos));
    preLen = preOrdered.length;
  }
  const total = preLen + activeFrames;
  const merged = new Float32Array(total);
  if (preOrdered) merged.set(preOrdered, 0);
  let off = preLen; for (const c of activeChunks) { merged.set(c, off); off += c.length; }

  // reset pre-buffer for next take
  preBufPos = 0; preBufFilled = false;
  if (preBuf) {
    const sz = Math.max(0, Math.floor(sr * cfg.prebuf_ms / 1000));
    if (sz !== preBuf.length) preBuf = sz > 0 ? new Float32Array(sz) : null;
    else preBuf.fill(0);
  } else if (cfg.prebuf_ms > 0) {
    preBuf = new Float32Array(Math.floor(sr * cfg.prebuf_ms / 1000));
  }

  const wavBlob = encodeWav(merged, sr);
  const durMs = Math.round((total / sr) * 1000);
  $('dbgDur').textContent = durMs;

  if (wavBlob.size < 1024) { setBugs([{ s: 'bad', t: 'тих микрофон / запис под 1 KB' }]); return; }

  uploadWav(wavBlob, durMs, reason);
}

// ─── WAV encoder (PCM 16-bit, mono) ───────────────────────────────────────
function encodeWav(samples, sampleRate) {
  const bytesPerSample = 2;
  const buf = new ArrayBuffer(44 + samples.length * bytesPerSample);
  const v = new DataView(buf);
  function ws(off, str) { for (let i = 0; i < str.length; i++) v.setUint8(off + i, str.charCodeAt(i)); }
  ws(0, 'RIFF'); v.setUint32(4, 36 + samples.length * bytesPerSample, true); ws(8, 'WAVE');
  ws(12, 'fmt '); v.setUint32(16, 16, true); v.setUint16(20, 1, true); v.setUint16(22, 1, true);
  v.setUint32(24, sampleRate, true); v.setUint32(28, sampleRate * bytesPerSample, true);
  v.setUint16(32, bytesPerSample, true); v.setUint16(34, 16, true);
  ws(36, 'data'); v.setUint32(40, samples.length * bytesPerSample, true);
  let off = 44; for (let i = 0; i < samples.length; i++) {
    const s = Math.max(-1, Math.min(1, samples[i]));
    v.setInt16(off, s < 0 ? s * 0x8000 : s * 0x7FFF, true); off += 2;
  }
  return new Blob([buf], { type: 'audio/wav' });
}

// ─── Backend call ─────────────────────────────────────────────────────────
async function uploadWav(blob, durMs, reason) {
  const cfg = getCfg();
  const fd = new FormData();
  fd.append('audio', blob, 'voicelab.wav');
  fd.append('lang', cfg.locale);
  if (cfg.hints && cfg.hints.trim()) fd.append('hints', cfg.hints.trim());

  if (abortCtrl) { try { abortCtrl.abort(); } catch (e) {} }
  abortCtrl = new AbortController();
  const t0 = performance.now();
  $('dbgLat').textContent = '… pending';
  $('dbgEng').textContent = '… (waiting)';

  let raceFlagged = false;
  const myCtrl = abortCtrl;
  let resp, json, err = null;
  try {
    resp = await fetch('/services/voice-tier2.php', { method: 'POST', body: fd, credentials: 'same-origin', signal: myCtrl.signal });
    json = await resp.json();
  } catch (e) {
    if (e.name === 'AbortError') raceFlagged = true; else err = e;
  }
  const lat = Math.round(performance.now() - t0);
  $('dbgLat').textContent = lat + ' ms';

  if (raceFlagged) { setBugs([{ s: 'bad', t: 'race detected — заявката беше abort-ната от нов запис' }]); return; }
  if (err || !json) { setBugs([{ s: 'bad', t: 'мрежова грешка: ' + (err && err.message || 'unknown') }]); return; }

  const ok = !!json.ok;
  const data = json.data || {};
  const transcript = data.transcript || '';
  const normalized = data.transcript_normalized || transcript;
  const conf = +data.confidence || 0;
  const engine = data.engine || '?';
  $('dbgEng').textContent = engine + (data.duration_ms ? ' · ' + data.duration_ms + 'ms server' : '');

  const parsed = applyProfile(cfg.profile, normalized);
  showResult({ ok, transcript, normalized, conf, parsed, cfg });
  runBugDetectors({ ok, transcript, normalized, conf, parsed, blob, raceFlagged: false, cfg });
  pushHistory({
    ts: Date.now(), duration_ms: durMs, latency_ms: lat,
    raw: transcript, normalized, parsed, confidence: conf, engine,
    profile: cfg.profile, locale: cfg.locale, settings: cfg,
    status: bugStatus({ ok, transcript, conf, parsed, cfg })
  });
}

// ─── Result + bug detectors ───────────────────────────────────────────────
function showResult(r) {
  $('resRaw').textContent = r.transcript || '∅';
  $('resNorm').textContent = r.normalized || '∅';
  $('resParsed').textContent = (r.parsed === null || r.parsed === undefined) ? '— (не може да се парсне)' : String(r.parsed) + ' ✓';
  let icon = '🟢', cls = 'ok';
  if (r.conf < r.cfg.conf_cutoff) { icon = '🟡'; cls = 'warn'; }
  if (r.conf < 0.50 && r.transcript) { icon = '🔴'; cls = 'bad'; }
  $('resConf').textContent = r.conf.toFixed(2) + ' ' + icon;
  $('resConf').className = 'v ' + cls;
  $('resParsed').className = 'v ' + (r.parsed === null || r.parsed === undefined ? 'warn' : 'ok');
}

function runBugDetectors(r) {
  const out = [];
  // 1) Halucination: text present but very low confidence
  if (r.transcript && r.conf < 0.50) out.push({ s: 'bad', t: 'Halucination: текст с много нисък confidence (<0.50)' });
  else out.push({ s: 'ok', t: 'Не halucination' });
  // 2) Cut-off start: leading "и/а/е/о " suggests truncation
  const head = (r.transcript || '').toLowerCase().trim().split(/\s+/)[0] || '';
  if (['и', 'а', 'е', 'о'].includes(head)) out.push({ s: 'bad', t: 'Cut-off: водеща частица "' + head + '" — възможно отрязано начало' });
  else out.push({ s: 'ok', t: 'Не cut-off' });
  // 3) Race: handled in uploadWav, no current race
  out.push({ s: 'ok', t: 'Не race' });
  // 4) Empty audio
  if (r.blob && r.blob.size < 1024) out.push({ s: 'bad', t: 'тих микрофон (blob < 1 KB)' });
  else out.push({ s: 'ok', t: 'Audio size ok (' + Math.round(r.blob.size / 1024) + ' KB)' });
  // 5) Parser fail
  if (r.transcript && (r.parsed === null || r.parsed === undefined)) out.push({ s: 'warn', t: 'Parser fail: не може да се парсне в profile=' + r.cfg.profile });
  else if (r.transcript) out.push({ s: 'ok', t: 'Parsed успешно' });
  else out.push({ s: 'warn', t: 'Празен transcript' });
  // 6) Confidence cutoff warning
  if (r.transcript && r.conf < r.cfg.conf_cutoff && r.conf >= 0.50) out.push({ s: 'warn', t: 'Confidence ' + r.conf.toFixed(2) + ' < cutoff ' + r.cfg.conf_cutoff.toFixed(2) });
  setBugs(out);
}

function bugStatus(r) {
  if (!r.ok || !r.transcript) return 'red';
  if (r.conf < 0.50) return 'red';
  if (r.parsed === null || r.parsed === undefined) return 'yellow';
  if (r.conf < r.cfg.conf_cutoff) return 'yellow';
  return 'green';
}

function setBugs(items) {
  const wrap = $('vlBugs'); wrap.innerHTML = '';
  for (const it of items) {
    const ico = it.s === 'ok' ? '✅' : (it.s === 'warn' ? '⚠️' : '❌');
    const div = document.createElement('div'); div.className = it.s;
    div.innerHTML = '<span class="ico">' + ico + '</span><span></span>';
    div.children[1].textContent = it.t;
    wrap.appendChild(div);
  }
}

// ─── History ──────────────────────────────────────────────────────────────
function loadHist() { try { return JSON.parse(localStorage.getItem(LS_HISTORY) || '[]') || []; } catch (e) { return []; } }
function saveHist(arr) { try { localStorage.setItem(LS_HISTORY, JSON.stringify(arr)); } catch (e) {} }
function pushHistory(entry) {
  const arr = loadHist(); arr.unshift(entry);
  if (arr.length > 20) arr.length = 20;
  saveHist(arr); renderHist();
}
function renderHist() {
  const arr = loadHist(); const wrap = $('vlHistWrap');
  if (!arr.length) { wrap.innerHTML = '<div class="vl-empty">няма опити още</div>'; return; }
  let html = '<table class="vl-history"><thead><tr><th>time</th><th>dur</th><th>raw</th><th>parsed</th><th>conf</th><th>🚦</th></tr></thead><tbody>';
  for (const e of arr) {
    const t = new Date(e.ts); const tStr = t.getHours().toString().padStart(2, '0') + ':' + t.getMinutes().toString().padStart(2, '0');
    const ico = e.status === 'green' ? '🟢' : (e.status === 'yellow' ? '🟡' : '🔴');
    const raw = (e.raw || '').replace(/[<>&]/g, c => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c]));
    const parsed = (e.parsed === null || e.parsed === undefined) ? '—' : String(e.parsed);
    html += '<tr><td>' + tStr + '</td><td>' + (e.duration_ms / 1000).toFixed(1) + 's</td><td class="tx">' + raw + '</td><td>' + parsed + '</td><td>' + e.confidence.toFixed(2) + '</td><td>' + ico + '</td></tr>';
  }
  html += '</tbody></table>';
  wrap.innerHTML = html;
}
$('vlExport').addEventListener('click', () => {
  const data = JSON.stringify(loadHist(), null, 2);
  const d = new Date(); const pad = (n) => String(n).padStart(2, '0');
  const fname = 'voice_lab_export_' + d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate()) + '_' + pad(d.getHours()) + pad(d.getMinutes()) + '.json';
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([data], { type: 'application/json' })); a.download = fname; a.click();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
});
$('vlClear').addEventListener('click', () => { if (!confirm('Изтрий цялата история?')) return; saveHist([]); renderHist(); });
renderHist();

// ─── Record button ────────────────────────────────────────────────────────
$('vlRec').addEventListener('click', () => { if (isRecording) stopRecording('manual'); else startRecording(); });

// Warn if not HTTPS (mic blocked)
if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
  setBugs([{ s: 'warn', t: 'Не-HTTPS — браузърът най-вероятно ще блокира getUserMedia' }]);
}
</script>
</body>
</html>
