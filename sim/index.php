<?php
// D520 simulator viewer — list latest sim outputs, show PNG + TSPL log side-by-side.
$dir = __DIR__;
$files = glob("$dir/*.png");
usort($files, function($a,$b){ return filemtime($b) - filemtime($a); });

$only_last = isset($_GET['last']);
if ($only_last) $files = array_slice($files, 0, 1);
else            $files = array_slice($files, 0, 20);
?><!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>D520 Sim — RunMyStore</title>
<style>
  body{font-family:-apple-system,sans-serif;margin:0;padding:12px;background:#f4f4f4;color:#222}
  h1{margin:0 0 12px;font-size:18px}
  .test{background:#fff;border:1px solid #ddd;border-radius:6px;padding:12px;margin-bottom:14px;display:flex;flex-wrap:wrap;gap:14px}
  .test img{border:1px solid #888;background:#fff;max-width:100%;height:auto;image-rendering:pixelated}
  .test pre{background:#f8f8f8;border:1px solid #eee;padding:8px;font-size:11px;line-height:1.4;max-width:100%;overflow:auto;flex:1;min-width:280px;margin:0}
  .meta{font-size:12px;color:#666;margin-bottom:6px;width:100%}
  .meta a{margin-right:8px}
  .refresh{position:fixed;top:8px;right:8px;background:#0a7;color:#fff;border:none;padding:8px 12px;border-radius:4px;font-size:14px}
  .empty{padding:20px;text-align:center;color:#888}
</style>
</head>
<body>
<h1>D520 Симулатор — последни <?= $only_last ? '1' : count($files) ?> теста</h1>
<button class="refresh" onclick="location.reload()">⟳ Refresh</button>
<?php if (!$files): ?>
  <div class="empty">Няма още тестове. Натисни печат в RunMyStore app-а с <code>D520_SIMULATE = true</code>.</div>
<?php endif; ?>
<?php foreach ($files as $png):
  $base   = preg_replace('/\.png$/', '', $png);
  $ts     = filemtime($png);
  $name   = basename($png, '.png');
  $log_f  = $base . '.txt';
  $bin_f  = $base . '.bin';
  $log_html = file_exists($log_f) ? htmlspecialchars(file_get_contents($log_f)) : '(no log)';
  $rel    = '/sim/' . basename($png);
  $sz     = file_exists($bin_f) ? filesize($bin_f) : 0;
?>
  <div class="test">
    <div class="meta">
      <strong><?= htmlspecialchars($name) ?></strong>
      &nbsp;·&nbsp; <?= date('H:i:s', $ts) ?>
      &nbsp;·&nbsp; <?= number_format($sz) ?> bytes
      <a href="<?= $rel ?>">PNG</a>
      <a href="/sim/<?= basename($log_f) ?>">log</a>
      <a href="/sim/<?= basename($bin_f) ?>">bin</a>
    </div>
    <div>
      <div style="font-size:11px;color:#888;margin-bottom:4px">RAW (printer interprets bytes directly)</div>
      <img src="<?= $rel ?>?t=<?= $ts ?>" alt="raw render">
    </div>
    <?php $utf8_png = $base . '_utf8.png'; $utf8_rel = '/sim/' . basename($utf8_png); if (file_exists($utf8_png)): ?>
    <div>
      <div style="font-size:11px;color:#888;margin-bottom:4px">UTF-8 DECODED (printer decodes UTF-8 first)</div>
      <img src="<?= $utf8_rel ?>?t=<?= $ts ?>" alt="utf-8 decoded render">
    </div>
    <?php endif; ?>
    <pre><?= $log_html ?></pre>
  </div>
<?php endforeach; ?>
</body>
</html>
