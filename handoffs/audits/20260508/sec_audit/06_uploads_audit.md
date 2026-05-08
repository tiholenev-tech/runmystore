# File Upload Audit — S116

**Date:** 2026-05-08
**Scope:** All `$_FILES[...]` handlers in repo root (excluding products.php).

## Summary

| File | MIME check | Extension whitelist | Size limit | Output dir served as PHP? |
|------|------------|---------------------|------------|---------------------------|
| `ai-color-detect.php` | ✅ getimagesize | ❌ | ❓ | ❓ |
| `ai-image-processor.php` | ✅ getimagesize | ❌ | ❓ | ❓ |
| `ai-studio-action.php` | ✅ via `studio_validate_upload()` helper | ❓ | ❓ | ❓ |
| `delivery.php` (OCR upload) | ❌ NONE | ❌ NONE | ❌ NONE | OCRRouter handles, but raw `$f['type']` (client-controlled MIME!) is forwarded |
| `inventory.php` (zone photo) | ❌ | ✅ jpg/jpeg/png/webp/gif | ✅ 8 MB | ❓ uploads/zones/ |
| `products_fetch.php` (CSV import) | ❌ | ✅ csv | ❓ | tmp_name only — not stored |

## Detailed Findings

### HIGH — `delivery.php:128-160` (OCR file upload, no validation)

```php
function api_ocr_upload(int $tenant_id, int $store_id, int $user_id): array {
    if (empty($_FILES['file'])) return ['ok' => false, 'error' => 'no file'];

    $files = [];
    $f = $_FILES['file'];
    if (is_array($f['name'])) {
        for ($i = 0; $i < count($f['name']); $i++) {
            if ($f['error'][$i] === UPLOAD_ERR_OK) {
                $files[] = ['path' => $f['tmp_name'][$i], 'mime' => $f['type'][$i]];   // ← $f['type'] is client-controlled
            }
        }
    }
    // ...
    $router = new OCRRouter();
    $ocr = $router->process($files, $tenant_id, [...]);
}
```

**Issues:**
1. `$f['type']` is the client-supplied MIME — **NEVER trust it**. Use `mime_content_type()` or `finfo_file()`.
2. No file size limit at PHP level (relies on `php.ini upload_max_filesize`).
3. No extension whitelist at PHP level. The OCRRouter may have its own validation, but defense in depth requires it here too.
4. `tmp_name` is fine (PHP-controlled), but `OCRRouter::process` may write derivatives to disk — verify.

**Reproduction:** Upload a `.php.gif` file with `Content-Type: image/gif`. Without server-side MIME check, this passes basic filters. If OCRRouter saves the file as-is into a web-served directory, RCE is possible.

**Fix:**
```php
$allowed_mime = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
$detected_mime = mime_content_type($f['tmp_name'][$i] ?? $f['tmp_name']);
if (!in_array($detected_mime, $allowed_mime, true)) {
    return ['ok' => false, 'error' => 'invalid mime'];
}
if ($f['size'] > 20 * 1024 * 1024) {
    return ['ok' => false, 'error' => 'too large'];
}
```

### MEDIUM — `inventory.php:25` (zone photo)

```php
$f=$_FILES['photo'];
$ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
if(!in_array($ext,['jpg','jpeg','png','webp','gif'])){echo json_encode(['ok'=>false,'error'=>'Невалиден формат']);exit;}
if($f['size']>8*1024*1024){echo json_encode(['ok'=>false,'error'=>'Файлът е голям']);exit;}
$fn='zone_'.$tenant_id.'_'.$store_id.'_'.time().'_'.rand(1000,9999).'.'.$ext;
move_uploaded_file($f['tmp_name'],$upload_dir.$fn);
```

**Issues:**
1. **Extension-only check** — `evil.php.jpg` passes (`pathinfo` returns `jpg`). The bigger risk is the **stored filename** which uses `.jpg` extension, NOT the uploaded one. So the file IS saved as `.jpg` — server should treat as image.
2. **BUT no MIME check** — actual file bytes could be anything (PHP code disguised as JPG). If `uploads/zones/` is configured to execute PHP (unlikely on standard Apache/nginx, but possible if .htaccess in that dir is wrong), RCE possible.
3. `rand(1000,9999)` is **not cryptographically secure** — collision possible. Use `bin2hex(random_bytes(4))`.

**Fix:**
```php
$detected = mime_content_type($f['tmp_name']);
if (!in_array($detected, ['image/jpeg','image/png','image/webp','image/gif'], true)) {
    echo json_encode(['ok'=>false,'error'=>'Невалиден формат']); exit;
}
// also: ensure uploads/zones/.htaccess has "php_admin_flag engine off" or equivalent
$rand = bin2hex(random_bytes(8));
$fn = "zone_{$tenant_id}_{$store_id}_".time()."_{$rand}.{$ext}";
```

### MEDIUM — `ai-color-detect.php` and `ai-image-processor.php`

Both use `getimagesize()` which returns false on non-image files — this IS a real validation but only checks if PHP can parse it as an image. Polyglot files (valid JPEG containing PHP code) can pass `getimagesize` and still execute if uploaded to PHP-enabled directory.

**Recommendation:**
- After getimagesize check, also verify `mime_content_type()` matches expected.
- Re-encode the image (e.g., `imagecreatefromjpeg → imagejpeg`) — strips embedded PHP/scripts.
- Strip EXIF (privacy + size).

### LOW — `ai-studio-action.php` uses `studio_validate_upload($_FILES['image'])` helper

Without reading the helper definition, can't confirm strength. Recommend audit:
```bash
grep -nE 'function studio_validate_upload' *.php config/*.php
```

### LOW — `products_fetch.php:730-732` (CSV import)

```php
if (!isset($_FILES['file'])) { echo json_encode(['error'=>'Няма файл']); exit; }
$file=$_FILES['file']['tmp_name']; $rows=[];
if (strtolower(pathinfo($_FILES['file']['name'],PATHINFO_EXTENSION))==='csv') {
```

- Only reads from `tmp_name` (PHP-controlled), parses as CSV.
- No file moved to web dir → no RCE risk.
- Possible: malicious CSV with billion rows → memory DoS. Add row count limit.

## Recommendations Priority Matrix

| # | Action | Severity | Effort |
|---|--------|----------|--------|
| 1 | Add MIME validation to `delivery.php:api_ocr_upload` | HIGH | 30 min |
| 2 | Add MIME validation to `inventory.php` zone photo upload | MEDIUM | 15 min |
| 3 | Re-encode + strip EXIF in ai-color-detect/image-processor | MEDIUM | 1 hour |
| 4 | Replace `rand()` with `random_bytes()` for filenames | LOW | 5 min |
| 5 | Audit `studio_validate_upload` helper | LOW | 30 min |
| 6 | Verify `uploads/*` dir cannot execute PHP (test with `<?php phpinfo();?>` in `evil.jpg.php`) | HIGH | 30 min |
| 7 | Limit CSV row count in products_fetch.php | LOW | 15 min |
