# Printer dual driver — DTM-5811 (BLE) + D520BT (Classic SPP)

**Sprint:** S96.D520
**Status:** ✅ Phase 3 implemented (raw TSPL via SPP). Phase 4 (frame wrapping) deferred.
**Files:** `js/capacitor-printer.js`, `printer-setup.php`, `tools/d520_classic_test.php`

---

## Architecture

```
js/capacitor-printer.js  (window.CapPrinter)
├── pair()           → pairDTM()           (legacy alias, BLE)
├── pairDTM()        → BLE GATT pair @ service 18f0 / char 2af1
├── pairD520()       → Classic SPP (RFCOMM 1101) bonded-list pick
├── print(p,s,c)     → routes by getActiveType()
│   ├── _printDTM()  → generateTSPL_DTM()  → writeChunked_DTM (BLE)
│   └── _printD520() → generateTSPL_D520() → writeSPP_D520    (RFCOMM)
├── hasPairedDTM(), hasPairedD520(), hasPairedPrinter() (any)
├── forgetDTM(), forgetD520(), forgetAll()
├── setActiveType('DTM' | 'D520')
└── pairDebug, scanDebug, sppListDebug, _diagnostics.info
```

Both printers can be paired in parallel. `printer-setup.php` exposes a
toggle (DTM / D520) when both are paired; the active type drives `print()`.

---

## Why two transports

DTM-5811 advertises BLE service `000018f0-0000-1000-8000-00805f9b34fb` and accepts TSPL writes on characteristic `00002af1-...`. Standard "TSPL over BLE" — works out of the box with `@capacitor-community/bluetooth-le`.

D520BT (AIMO/Phomemo D-family) advertises BLE service `0000ff00-...` but **the BLE channel does not carry print data**. Wireshark btsnoop_hci.log captured during a Labelife app print showed:

- 0 `btatt` (BLE GATT) packets
- All payload travels via `btrfcomm` + `btspp` (Bluetooth Classic SPP)
- SDP service `0x1101` = Serial Port Profile

Five S95 attempts to print over the BLE channel (Phomemo D30 protocol headers, LuckPrinter SDK enable+wakeup, exact byte replay of Labelife's payload, writeWithoutResponse vs write, raw TSPL) all failed silently — the bytes go through but the print engine never engages. The print engine is hardwired to RFCOMM. S96 ships the Classic SPP driver as the second transport; do not revisit BLE for D520.

---

## Plugin: `@e-is/capacitor-bluetooth-serial@^6.0.3`

Auto-registers via `@CapacitorPlugin` annotation. Capacitor 8 host project, plugin built for Capacitor 6 — no API breaking changes affect this plugin's surface (Bridge / Plugin / JSObject identical between 6 and 8). Confirmed by `npx cap sync android` detecting the plugin without manual MainActivity edit.

API used:
- `isEnabled()`, `enable()`
- `scan()` — returns the system's already-bonded Classic device list
- `connect({address})` / `connectInsecure({address})` / `disconnect({address})`
- `write({address, value: string})`
- `read({address})` — used only in test tool

### ⚠️ Binary-byte caveat (load-bearing)

The plugin's Android `write()` does:

```java
byte[] bytes = value.getBytes(StandardCharsets.UTF_8);
```

Effects on wire bytes given a JS string with code-units 0x00-0xFF:

| JS code unit | UTF-8 encoded bytes | Wire? |
|--------------|---------------------|-------|
| 0x00–0x7F    | 0x00–0x7F (1 byte)  | ✅ identity |
| 0x80–0xFF    | 0xC2/0xC3 prefix + (2 bytes) | ❌ **2 bytes per char, corrupted** |

**Consequence:** ASCII-only TSPL works (TEXT, BARCODE, all command keywords). BITMAP raw raster bytes break (any black pixel run produces ≥0x80 bytes). AIMO frame wrapping (`0x7E ... checksum ... 0x7E`) also breaks.

**Phase 3 design (current):** ASCII-only TSPL. `generateTSPL_D520` emits TEXT/BARCODE only; cyrillic input is transliterated to latin via `BG_TRANSLIT` before TSPL emission.

**Phase 4 design (deferred):** If raw TSPL doesn't print on the actual hardware, the AIMO frame protocol (sniffed from Labelife: `[0x7E][seq][len_lo][len_hi][≤256B payload][checksum][0x7E]`) needs binary writes. Options:

1. **Fork @e-is plugin locally** — change `BluetoothDeviceHelper.toByteArray()` from UTF-8 to ISO-8859-1 (1:1 byte mapping). 5 LOC patch. Install via `file:` dep in `mobile/package.json`.
2. **Add a `writeBytes(base64)` method** to a forked plugin — JS sends base64, Java decodes to byte[].
3. **Switch to a binary-capable plugin** — e.g. `cordova-plugin-bluetooth-classic-serial-port` (older, Cordova-style) or write a custom Capacitor plugin (~100 LOC Kotlin).

Reverse-engineering the AIMO checksum is also incomplete (5 brute-force candidates didn't fit; Labelife APK reverse + `Checksum.java` extraction is the next step).

---

## TSPL generators — DTM vs D520

|                | DTM-5811 (`generateTSPL_DTM`)              | D520BT (`generateTSPL_D520`)            |
|----------------|--------------------------------------------|------------------------------------------|
| Transport      | BLE (binary-safe via `DataView`)            | SPP (UTF-8 string, ASCII-only)           |
| Density        | 10                                          | 11                                       |
| Speed          | 3                                           | 4                                        |
| Gap            | 2 mm                                        | 3 mm                                     |
| Cyrillic       | BITMAP raster (canvas-rendered)             | Latin transliteration via `BG_TRANSLIT`  |
| Store name     | TEXT or BITMAP                              | TEXT only                                |
| Product name   | BITMAP (font 28)                            | TEXT font 3 (transliterated)             |
| Price          | BITMAP (font 38)                            | TEXT font 4, scale 2×2                   |
| Code           | TEXT or BITMAP                              | TEXT only                                |
| Barcode        | BARCODE EAN13/UPCA/EAN8/128                 | BARCODE EAN13/UPCA/EAN8/128 (same)       |

DTM regression: `generateTSPL_DTM` body is **byte-for-byte identical** to the pre-S96 `generateTSPL` (md5 `c73233208635110c4f7105eb071bc7ae`). Verified by diff at S96.D520.2 commit time.

---

## Storage keys (localStorage)

| Key                              | Value                          | Owner |
|----------------------------------|---------------------------------|-------|
| `rms_printer_device_id`          | DTM BLE deviceId (UUID/MAC)     | DTM   |
| `rms_printer_service_uuid`       | DTM BLE service UUID            | DTM   |
| `rms_printer_write_char_uuid`    | DTM BLE write char UUID         | DTM   |
| `rms_printer_d520_address`       | D520 Classic MAC                | D520  |
| `rms_printer_d520_name`          | D520 paired-device name         | D520  |
| `rms_printer_type`               | `'DTM'` or `'D520'`             | active selector |

Last paired wins by default. UI selector in `printer-setup.php` overrides.

---

## Pairing flow

### DTM-5811
1. App-side: open `printer-setup.php` → "Сдвои DTM-5811".
2. `pairDTM()` calls BLE `requestDevice` filtered by `SERVICE_UUIDS = [DTM_SERVICE_UUID]`.
3. Falls back to unfiltered picker if Android doesn't advertise services in scan packets.
4. Connects, runs `discoverDtmEndpoint`, saves keys.

### D520BT
1. **OS-side first** (one-time): Android Settings → Bluetooth → pair "D520BT-Z" → PIN `0000` if prompted.
2. App-side: `printer-setup.php` → "Сдвои D520BT".
3. `pairD520()` calls `BluetoothSerial.scan()` which returns the system's already-bonded Classic list (does NOT do a new discovery).
4. Picks by name match: `D520`, `D520BT`, fallback to `AIMO`/`PHOMEMO`/`241BT`.
5. If not found, throws helpful error listing what bonded devices ARE present.

---

## Troubleshooting

### "BluetoothSerial плъгин не е зареден"
The APK doesn't have S96.D520.1 build. Push commits and rebuild via GitHub Actions; install fresh APK.

### "D520BT не е сдвоен с телефона"
The OS-level Bluetooth pairing didn't happen. Open Android Settings → Bluetooth → scan → bond → PIN 0000.

### D520 prints garbage / no print at all
Phase 3 sends raw TSPL via SPP. If nothing happens:
1. Open `tools/d520_classic_test.php` → click **Connect** → **Send raw** with the default TSPL.
2. If still nothing → the printer needs frame wrapping (Phase 4); see "Phase 4 design" above.
3. If text prints but cyrillic is `?` — that's expected; transliteration via `BG_TRANSLIT` is enabled in production but the hex test mode bypasses it.

### DTM regressed after S96
Check md5 of the body of `generateTSPL_DTM`:
```bash
awk '/function generateTSPL_DTM\(/,/^  }/' js/capacitor-printer.js | md5sum
# Should match c73233208635110c4f7105eb071bc7ae
```
If it's drifted, restore from `js/capacitor-printer.js.bak.s96` and reapply only the rename.

### Cyrillic in price tags shows `?`
Active code path is `generateTSPL_DTM` (BLE), which uses BITMAP raster — should NOT show `?`. If it does, check that `hasCyrillic()` regex matched and `renderTextBitmap` returned non-null. For the D520 path, cyrillic `?` is expected when transliteration table doesn't match (e.g. non-Bulgarian cyrillic like Russian "Ё/Ы" — extend `BG_TRANSLIT` if needed).

---

## References

- **Wireshark capture analysis:** `tshark -r btsnoop_hci.log -Y "btatt"` returned 0 packets during D520 Labelife print → BLE channel proven empty for D520.
- **TSPL command spec:** TSC Auto ID Tech "TSPL/TSPL2 Programming Manual" (latest revision)
- **AIMO frame protocol:** sniffed from Labelife capture, partially decoded (header + length + payload chunks confirmed; checksum unsolved).
- **Plugin source:** `mobile/node_modules/@e-is/capacitor-bluetooth-serial/` — see `android/src/main/java/com/bluetoothserial/` for the Java side.

---

## Phase status (S96)

- [x] **D520.1** — Plugin install, npm + cap sync OK
- [x] **D520.2** — Driver refactor, dual scaffolding, DTM regression-locked
- [x] **D520.3** — Raw TSPL D520 implementation (this commit, Phase 3)
- [ ] **D520.4** — AIMO frame wrapping (deferred; needed only if raw TSPL fails on hardware)
- [x] **D520.5** — printer-setup.php dual UI
- [x] **D520.6** — Docs + standalone test page + tag (this commit)
