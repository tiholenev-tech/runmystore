# S88B.PRODUCTS.KAKTO_PREDNIA_FIX — Handoff

**Сесия:** S88B.PRODUCTS.KAKTO_PREDNIA_FIX — поправки спрямо BIBLE v1.3
**Дата:** 2026-04-29
**Файл:** `/var/www/runmystore/products.php` (12,346 реда)
**Базиран на:** S88.PRODUCTS.KAKTO_PREDNIA (`f6e0090`)
**Commit:** `a2d9679` (1 файл, +68 / −45)

---

## Защо

S88 (commit `f6e0090`) направи UI на "Като предния" 1:1 към mockup, но 3 handlers бяха по СТАРИ инструкции преди BIBLE 7.2.8 + 7.2.8.5 v1.3 да се финализира. S88B alignment-ва code-а с тази override-нала спецификация (10 полета копирани, snimka copy+tap, без opt-in checkboxes, qty винаги 0).

---

## 4 корекции

### #1 — Премахнат auto-increment на code (BIBLE 7.2.8 v1.3)

- **Backend:** `last_product` AJAX endpoint (PHP) — изтрит целият loop който search-ваше `SELECT 1 FROM products WHERE code=?` за свободен инкремент. `$row['next_code'] = ''` (empty literal).
- **Frontend:** `<input type="hidden" id="wCode">` → visible `<input type="text" id="wCode" placeholder="Скенирай barcode или въведи">` с label "Артикулен номер" + voice mic.
- **Voice fallback:** нова функция `kpVoiceCode()` — STT в `bg-BG`, фиксира за alphanumeric+dash, попълва `wCode`.
- **Защо:** "code задължително празно — Митко scan-ва barcode който става code, или voice въвежда".

### #2 — Снимка copy by default + tap за смяна (BIBLE 7.2.8.5 v1.3)

- **Аудит:** В commit `f6e0090` НЯМАШЕ "Същата снимка" checkbox; photo-hero вече auto-показва `d.image_url`. UI/save-flow са вече aligned.
- **Update:** Overlay text "Tap за смяна на снимка" → "Tap за смяна" (по-кратко, smartlooking).
- **CSS:** `.kp-photo-overlay` rewritten:
  - Position: bottom-left (бе center)
  - Pill: `rgba(0,0,0,0.6)` background, `border-radius:8px`, `padding:6px 10px`, `opacity:0.7`
  - Text: 12px white (бе 11px)
- **Save flow (непроменен — verified correct):** `kpCollectIntoWizData` fetch-ва `st.photoUrl` като blob и dataURL когато user не е tap-нал смяна → същ. effect = source image_url копиран snapshot, не reference.

### #3 — Премахнат "Копирай количество" checkbox

- **DELETED:** `<label class="kp-copy-qty-row">...<input type="checkbox" id="kpCopyQty" onchange="kpCopyQtyToggle(...)">...</label>` от `kpSingleSectionHtml`.
- **wMinQty:** hidden input value `'+(parseInt(d.min_quantity)||0)+'` → литерал `0`.
- **wSingleQty:** вече беше `0` (hidden input) — без промяна.
- **Variant matrix:** вече стартира празен → всички cells qty=0; без промяна.
- **kpCopyQtyToggle function:** оставена като orphan dead code (не е извиквана) — за да не нарушаваме "Запази JS handlers" правилото от ограниченията.
- **Защо:** "бройките винаги 0 — нов продукт = 0 до доставка/инвентаризация".

### #4 — 9-то и 10-то поле в copied-card

- **kpFieldDef:** добавени 2 entries:
  - idx=8: `{ key:'wholesale', label:'Цена едро', fmt:'price', editable:true }` — editable read-from-source price
  - idx=9: `{ key:'_variation', label:'Тип артикул', fmt:'variation', computed:true }` — read-only computed
- **kpFieldHtml:** new branch за `_variation` → "Вариационен (N цвята × M размера)" или "Единичен" (от `st.type` + `st.colors` + `st.sizesByColor`).
- **Price empty-state:** старият код показваше `0.00 €` за empty wholesale → сега `—` за `<= 0`.
- **Map:** `[0,1,2,3,4,5,6,7]` → `[0,1,2,3,4,5,6,7,8,9]`.
- **Subtitle:** "8 полета" → "10 полета".

(Снимката е в photo-hero, не в copied-card — copied-card е само текстови полета.)

---

## Preserved

- Master/variant логика непроменена (`kpVariantSectionHtml`, matrix encoding, `kpAdj`, `kpRemoveColor`, `kpAddColor`)
- Всички DOM IDs: `kpModal`, `kpPhotoHero`, `kpCfBody`, `kpCf0..kpCf9` (експандирано), `kpVariantSection`, `kpSingleSection`, `wName`, `wCode`, `wBarcode`, `wSubcat`, `wSingleQty`, `wMinQty`, `kpQ_*`, `kpColorTotal_*`, `kpColorCard_*`, `kpMargin`, `kpSingleQtyView`, `kpSingleMinView`, `kpV*`, `kpEdit*`
- JS handlers: `kpClose`, `kpFieldHtml`, `kpEditField`, `kpEditCommit`, `kpToggleCopied`, `kpSwatchClass`, `kpVariantSectionHtml`, `kpSingleAdj`, `kpAdj`, `kpAddColor`, `kpRemoveColor`, `kpPhotoPick`, `kpVoiceName`, `kpCollectIntoWizData`, `kpSave`, `kpSaveThenAIStudio`, `kpPrintNow`, `renderLikePrevPageS88`
- `kpCopyQtyToggle` и `kpCopyQty` checkbox handler — kept в JS (orphan dead code)

## Added

- `kpVoiceCode()` — voice STT за артикулен номер (BIBLE voice fallback)
- `kpFieldHtml` `_variation` branch — read-only display
- visible `wCode` text input + label + voice mic в nameCard

---

## DOD checklist

- [x] Code input стартира ПРАЗЕН на render (no auto-increment) — verified `value=""`, placeholder set
- [x] photo-hero показва copied snimka + "Tap за смяна" overlay (no checkbox) — verified
- [x] qty=0 на всички размери/цветове (no checkbox) — verified `wMinQty=0`, `wSingleQty=0`, matrix={}
- [x] copied-card има 10 полета — verified kpFieldDef array length = 10, map iterates 0..9
- [x] Master/variant логика непроменена — verified (no change to kpVariantSectionHtml, kpAdj, kpAddColor, kpRemoveColor)
- [x] php -l clean — verified
- [x] 1 commit + push — `a2d9679` pushed to `origin/main`

## Known limitations

- **wCode visible но не activated с focus** — за UX може Митко да хареса auto-focus на code след save на name. Defer S88C ако QA го поиска.
- **kpVoiceCode UI feedback** — няма scale-on-active за code mic (за разлика от name mic). Polish defer S88C.
- **kpCopyQty / kpCopyQtyToggle = dead code** — функцията е оставена в JS (preserved per session constraints). Cleanup defer.
- **Wholesale = 0 показва се "—"** — но е editable; ако user натисне edit и въведе число → стандартен flow. Confirmed.
- **Тип артикул computed** — derive-ва се от `st.type` (set от `hasVariants` в `renderLikePrevPageS88`). Source-derived. Не е reactive ако user добавя/маха цветове → display не update-ва автоматично. Defer ако се поиска (БИБЛИЯ казва "копира се структурата" — implication: тип е дефиниран от source, не от ad-hoc edits).

## DEFER list — S88C+

- Custom modal вместо native `prompt()` за `kpAddColor` (Capacitor Android UX)
- Re-render of variation field (idx=9) when colors are added/removed via `kpAddColor`/`kpRemoveColor` (currently stale until full re-render)
- Auto-focus на code input след save на име (UX polish)
- Voice mic visual feedback за `kpVoiceCode`
- Cleanup на `kpCopyQty`/`kpCopyQtyToggle` dead code

---

## Sign-off

S88B completed 2026-04-29. PHP lint clean. 1 commit pushed.

For QA on device: open products.php → tap "..." menu → "Като предния" → verify:
1. Code input visible empty с placeholder
2. Photo show source image + "Tap за смяна" pill в bottom-left
3. Qty steppers всички показват 0
4. Copied card subtitle = "10 полета"
5. Tap copied card → виж 10 полета (включва "Цена едро" + "Тип артикул")
6. Tap "Тип артикул" — read-only (no edit button)
