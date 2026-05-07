# HANDOFF S103D — D520BT PRINTER CYRILLIC PRINT

**Дата:** 2026-05-07 (вечер)
**Сесия:** S103D — D520BT thermal label printer Cyrillic печат
**Status:** ✅ WORKING in production (hybrid path, BAR-RLE rendering)
**File lock:** `js/capacitor-printer.js`, `products.php` (само 2 line edits + nov toggle UI)

---

## РЕЗЮМЕ — какво стана и защо

**Изначален проблем (Тихол):** D520BT thermal printer печата кирилицата като латиница (transliterated). Потребителят искаше истинска кирилица.

**Root cause analysis:**
1. Label app sniff показа че Label app пуска **22.7K UTF-8 encoded raster bytes** на DLCI 2 за един BITMAP.
2. Извадихме точните bytes verbatim → пуснахме чрез нашия app → printer не печата правилно (gives stripes, not Cyrillic).
3. Симулатор build-нат на server (`/var/www/runmystore/sim_print.php` + `sim_render.py` + `/sim/index.php`) — приема bytes от phone-а, render-ва TSPL stream → PNG. Visual fast-iteration без physical print. Confirmed JS pipeline байтово коректен.
4. Установено: Capacitor `@e-is/capacitor-bluetooth-serial` plugin прави UTF-8 wrapping на high bytes на native Java side. S97 patch (`mobile/patches/@e-is+capacitor-bluetooth-serial+6.0.3.patch`) сменя UTF_8 → ISO_8859_1 за това, **но е applied само в `node_modules/`** — за да влезе в production build, нужен е APK rebuild. На server-а няма Java/Android SDK → rebuild не е възможен оттук.
5. Pivot: **path 'hybrid'** (S98.PathF) — рендерира кирилицата canvas-side, конвертира black pixels в TSPL `BAR x,y,w,h` команди. Stream е pure ASCII (числа + commands < 0x80) → plugin няма high bytes за мангелва → printer изпраща правилни raster pixels → видима кирилица.

**Резултат:** Cyrillic печата чрез BAR primitives. Не е чрез BITMAP, но визуално е същият резултат.

---

## АКТИВЕН PIPELINE (production)

### Default path: `'hybrid'` (line 1764 в `_printD520`)

`generateTSPL_D520_hybrid` (line 1345) — генерира TSPL job:
- Header: `SIZE 50 mm,30 mm`, `GAP 3 mm,0`, `DIRECTION 1`, `DENSITY 11`, `SPEED 4`, `CLS`
- Layout (50×30 mm = 400×240 dots, compressed S103D.C):
  - **0-86**: BARCODE (ляво, 68 dots high) + name + код (дясно)
  - **86**: SEP1
  - **90-118**: размер pill (REVERSE текст обърнат) + цвят
  - **118**: SEP2
  - **122-166**: евро цена 44px (`placeText(priceEur(amt))`)
  - **168-188**: лв цена 22px (само в 'dual' mode)
  - **192**: SEP3
  - **196-208**: `Внос: <importer>, <city>` (full width, BOT_PAD=16)
  - **210-222**: `<material> · Произход: <country>` (full width)
- Footer: `PRINT <n>\r\n`

### `placeText(text, x, y, fontPx, maxWidth, opts)` (line 1390)

- Ако text е ASCII → TSPL `TEXT` cmd (нативен принтер шрифт)
- Ако text има non-ASCII → `renderTextAsBars(...)` → canvas → BAR-RLE команди

### `renderTextAsBars` (line 1185) — критична функция

- Rasterize text на JS canvas (`'900 ' + fontPx + 'px Arial,sans-serif'`)
- За всеки row, RLE-обхожда black pixels и emit-ва `BAR x,y,w,h` за всеки run
- Defaults: `stripeH=1`, `minRun=1` (S103D — full detail; преди беше 2/2 което даваше jagged look)
- **Right/center align fix (S103D)**: `startX` се изчислява СПЕД като известно canvas width; преди offsetX се ползваше директно → text rendered off-screen при `align: 'right'`

### Подробности за gotcha-та с REVERSE pill (S103D.E)

TSPL `REVERSE x,y,w,h` обръща пикселите в правоъгълника **AT print time**. Ако пишеш текст ПРЕДИ REVERSE → текстът става **бял на черно** (визуално). Ако пишеш ПОСЛЕ → текстът е black on already-black background → **invisible**. Винаги първо `placeText`, после `push('REVERSE ...')`.

---

## ИЗМЕНЕНИЯ В ТАЗИ СЕСИЯ (chronological)

### S102D (опит за UTF-8 raster encoding — НЕ работи без APK rebuild)
- Добавен flag `D520_USE_UTF8_RASTER` (line 128, default `false`)
- Добавен helper `rasterToUtf8Bytes(raster)` (line 1283) — encoding rule byte-perfect verified от Label sniff
- Modified `generateTSPL_D520_bigbmp` (line 1313) — pushBmpRaster wraps with rasterToUtf8Bytes ако flag е true
- **STATUS:** код запазен но flag = false. Остава като starting point ако някога APK се rebuild-не.

### S103D (replay path + cache-bust + pivot към hybrid)
- Modified `_printD520` default → `'hybrid'` (line 1764)
- `d520_replay.bin` заменен с verbatim Label Job 1 (22859 bytes, MD5 `3a79935fb5...`); backup в `d520_replay.bin.bak.OLD_OUR_JS_20260507_1251`
- Added `?v=<filemtime>` cache-bust в `<script>` tags (line 4283 на products.php, line 13 на printer-setup.php) — без него WebView кеш блокираше JS updates

### S103D.B/C/D — layout iterations за hybrid path
- B: евро 44px, лв 22px, stacked importer/origin (was side-by-side truncating)
- C: compressed vertical layout to fit 240 dots без overflow
- D: BOT_PAD=16 (extra left margin) — fix-ва ляв edge crop от printer feed alignment
- E: REVERSE order fix (text first, then REVERSE) — fixes "черен квадрат на мястото на размера"

### S103D.F — barcode flexibility (last)
- New helper `pickBarcode(product, opts)` (line 444) returns `{data, type} | null`:
  - 13 digits → EAN13 (auto-checksum if 12)
  - 8 digits → EAN8
  - Other (alphanumeric) → CODE128 (universal)
  - `opts.noBarcode === true` → null (skip)
  - Empty + product.id → fallback "200xxx" EAN13
- В hybrid: BARCODE cmd type-aware (`bcInfo.type` controls TSPL syntax)
- UI toggle "Печат без баркод" в products.php print-modal (около line 7434)
- Двата call sites на `CapPrinter.print(...)` подават `noBarcode: !!S.wizData._noBarcode` (lines 5597 + 12827)

### Debug cleanup (final)
- `dbgLog()` (line 385) → console.log only; `showDebugOverlay()` повикването е disabled. Ако трябва да се върне on-device console — uncomment line 392.
- `D520_SIMULATE` flag премахнат (преди = false). `writeSPP_D520` няма повече sim divert block.
- Diagnostic alerts в replay path премахнати.

---

## SIMULATOR (запазен — useful за debug)

**Endpoint:** `https://runmystore.ai/sim_print.php` (POST raw bytes, X-D520-Path header optional)
**Renderer:** `/var/www/runmystore/sim_render.py` (pure Python, no PIL — uses zlib for PNG)
**Viewer:** `https://runmystore.ai/sim/?last=1` (recent test) или `/sim/` (last 20)

Renders две PNG версии когато raster blob > expected (W*H bytes):
1. `<base>.png` — raw bytes interpretation
2. `<base>_utf8.png` — UTF-8 decoded interpretation

Permissions: `/var/www/runmystore/sim/` е `chmod 777` (Apache www-data write).

**За да активираш sim mode** в JS (debug iteration без printer):
```js
// в capacitor-printer.js, в writeSPP_D520:
// Currently NO simulator hook. To re-add, uncomment / restore the SIM block
// from git history (commit преди final cleanup).
```

---

## КРИТИЧНИ ФАКТИ ЗА ROLLBACK / RECOVERY

1. **production checkout:** `/var/www/runmystore/` (git working tree, branch main, currently dirty с S103D edits — НЕ committed още)
2. **last clean commit:** `54e752c S103D.PRINTER.D520_DEFAULT_REPLAY` (pre-hybrid pivot)
3. **Backups на capacitor-printer.js**:
   - `js/capacitor-printer.js.bak.D520_DEFAULT_REPLAY_20260507_1305` (pre-S103D)
   - `js/capacitor-printer.js.bak.D520_UTF8_20260507_1223` (pre-S102D)
   - `js/capacitor-printer.js.bak.S98.PathD.20260506_0654` (pre-S98)
   - `js/capacitor-printer.js.bak.S95.replay`, `.bak.s96`, `.bak.s97` (по-стари)
4. **Backup на d520_replay.bin:** `js/d520_replay.bin.bak.OLD_OUR_JS_20260507_1251` (нашия по-стар JS sniff, преди да го replace-нем с Label Job 1)
5. **products.php backup:** не е създаден за S103D.F (само 1 line edits + 1 toggle div add — easy revert)
6. **Patch files:**
   - `/tmp/s102d_printer.patch` — UTF-8 raster encoding (inactive)
   - `/tmp/s103d_printer.patch` — replay default + S98 path matrix consolidation

---

## АКО НЕЩО ПОЧНЕ ДА БЪГ-ВА — DIAGNOSTIC FLOW

| Симптом | Възможна причина | Стъпка |
|---|---|---|
| Кирилица излиза като garbled / латиница | path не е 'hybrid'; localStorage `d520_path` override-ва default | Browser console: `localStorage.removeItem('d520_path')` |
| Printer не реагира | Plugin write fail; често transient connection drop | Force kill app, power-cycle printer, retry |
| Текстове clip-нат на ляв ръб | Printer feed misalignment; BOT_PAD = 16 е safe minimum | Може да се вдигне BOT_PAD на 20 |
| Бар код не показва | product.barcode празен И product.id null OR opts.noBarcode=true | Виж `pickBarcode` логика, line 444 |
| "Черен квадрат" вместо размер | REVERSE order regression | Виж S103D.E коментар, line 1448 |
| WebView не зарежда нов JS | `?v=mtime` не се рендерира | Провери products.php line 4283 + printer-setup.php line 13 |
| Debug overlay изскача | `dbgLog()` се променя обратно | Виж line 385, увери се че `showDebugOverlay(line)` is commented |

---

## STILL-BROKEN / PENDING

1. **Real BITMAP path не работи** (path = 'replay' / 'bigbmp'). Blocked on **APK rebuild** със verified S97 plugin patch:
   ```bash
   cd /var/www/runmystore/mobile
   npm install              # patch-package applies S97 в node_modules (already done)
   npx cap sync android
   cd android && ./gradlew assembleDebug    # needs Java 17 + Android SDK
   # Install resulting .apk on phone
   ```
   Server lacks Java/Android SDK; sudo password required to apt install. Ако някога се rebuild-не — flip default path обратно на 'replay' или 'bigbmp' и Cyrillic чрез истински BITMAP raster ще работи (по-добра font quality от BAR-RLE).

2. **DTM5811 принтер** — НЕ е докоснат в тази сесия. Path запазен `_printDTM5811`, generators непроменени.

3. **Други D520 paths** (escpos, pathc, bitmap0, bitmap4, bmptest) — оставени за бъдещ debug. Достъпни чрез `localStorage.setItem('d520_path', '<name>')`.

---

## TESTING NOTES

- Първи success Cyrillic print: 17:13 local (15:13 UTC), Тihol photo `Сканирано_20260507-1813.jpg` в Drive root
- Visible label на photo: "дафи 99 (Дафи)" / "ДА99-62 0075487319457" / "4,99 €" / "9,76 лв" / "0% памук 10% еластан · Произход: Турция"
- Edge clip on left side (≤5 dots) — fixed in S103D.D с BOT_PAD=16
- Размер showing as "черен квадрат" — fixed S103D.E
- Final state: всичко чете правилно, голяма цена, размер pill бяло-на-черно

---

## SLEDVASHTI STEPS (ако continue)

1. **Build environment for APK**: install JDK 17 + Android SDK на dev машина (не server). Test S97 patch live, rebuild APK, switch default to 'replay' за по-чист BITMAP printing.
2. **DTM5811 паритет**: ако DTM е sayet to also need Cyrillic в hybrid mode — apply same BAR-RLE подход.
3. **Per-product no_barcode flag**: ако Тihol иска DB-level setting (не само per-print toggle), добавя column products.no_barcode + UI checkbox в edit-product модал.
4. **Variant-specific barcode**: variants също могат да имат own barcode field (it.v.barcode); пр настоящ кoд го взема. Verify че user UX позволява scan-a/edit на variant barcode.

---

**Край на S103D handoff. Hybrid path е stable, real BITMAP остава blocked на APK rebuild.**
