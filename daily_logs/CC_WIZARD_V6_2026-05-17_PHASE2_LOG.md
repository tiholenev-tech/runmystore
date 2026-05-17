# 📋 CC WIZARD v6 — ФАЗА 2 PROTOCOL LOG

**Branch:** `s148-cc-phase2-photo`
**Backup tag:** `pre-phase2-start-20260517_0722`
**Start:** 2026-05-17 07:35
**Reading list confirmed:** voice-tier2.php (333) · price-ai.php (92) · ai-helper.php callGeminiVision (197-224) · products.php 14341-14510 · handoff §5.1-5.3 + §14

Iron rule (повтаряно преди всеки sub-step):
> *"Копирай 1:1 от products.php. Без подобрения. Без 'по-добре да е така'. Sacred zones NEVER touched. При неяснота — STOP, питам в QUESTIONS файл."*

═══════════════════════════════════════════════════════════════
## SUB-STEP 2a: `js/wizard-parser.js`

### Protocol checklist

- ✓ Прочетох `products.php` ред **14341-14373** — `_wizMicWhisper(field, lang)` (33 реда, audio recording shell)
- ✓ Прочетох `products.php` ред **14418-14495** — `_bgPrice`, `_BG_WORD_NUMS`, `_BG_WORD_KEYS`, `_CYR_B_WP`, `_CYR_A_WP`, `_wizPriceParse`
- ✓ Логиката която копирам: (1) BG voice→price local parser с word→digit lookup; (2) MediaRecorder webm capture с 5s timeout, fallback to Web Speech API
- ✓ Sacred check: НЕ пипам products.php · voice-tier2.php · price-ai.php · ai-color-detect.php · capacitor-printer.js
- ✓ Bridge call: 1 ЕДИНСТВЕНА промяна спрямо 1:1 копие — `fetch('/services/voice-tier2.php', …)` → `fetch('/services/wizard-bridge.php?action=mic_whisper', …)` (Q1/Q2 explicitly approved)

### External references (definitions live в wizard-v6.php script, не в parser.js)
- `_wizClearHighlights()` — wizard-v6 helper, no-op stub ако още няма
- `_wizMicWebSpeech(field)` — Web Speech fallback (TBD sub-step 2f)
- `_wizMicApply(field, text)` — apply transcript to field (TBD sub-step 2f)

(Тези не са в range 14341-14373/14418-14495; те остават отговорност на wizard-v6.php.)

### Verification
- `node --check js/wizard-parser.js` → OK
- SHA256 на products.php редове 14418-14495 = SHA256 на js/wizard-parser.js редове 56-133 → `9fb84020d451da16ef005e124d8ada6d54cd19f91687e2b5c2adbd3fb855133d` (byte-identical 78 реда)
- `diff` на _wizMicWhisper source vs extract → точно 1 ред разлика = URL swap
- `bash tools/verify_sacred.sh` → 5/5 PASS

### Commit
- New file: `js/wizard-parser.js` (138 реда, 1 шапка comment + 117 реда code = 116 sacred + 1 URL change)
- Sacred SHA непроменена.

═══════════════════════════════════════════════════════════════
## SUB-STEP 2b: `services/wizard-bridge.php`

### Protocol checklist

- ✓ Прочетох `services/voice-tier2.php` (333 реда) — auth ходи вътре в endpoint-а, POST FormData
- ✓ Прочетох `services/price-ai.php` (92 реда) — auth вътре, POST JSON
- ✓ Локализирах `ai-color-detect.php` в repo root (не `services/`), 296 реда — POST FormData
- ✓ Логиката която копирам: НЯМА copy от products.php (бридж е НОВ файл, не extract)
- ✓ Sacred check: НЕ пипам voice-tier2.php · price-ai.php · ai-color-detect.php · products.php · capacitor-printer.js
- ✓ Bridge architecture: тънък router `require`-ва съответния sacred endpoint inline. Sacred файловете сами правят session_start + auth + JSON output; бриджът не дублира тази логика. ai_vision и ai_markup target-ите се проверяват с `file_exists` (още не съществуват преди 2c/2d).

### Verification
- `php -l services/wizard-bridge.php` → no syntax errors
- Smoke test no action → `{"ok":false,"error":"unknown action","valid":[...]}`
- Smoke test ai_vision (pre-2c) → `{"ok":false,"error":"endpoint not yet deployed"}`
- `bash tools/verify_sacred.sh` → 5/5 PASS

### Commit
- New file: `services/wizard-bridge.php` (56 реда)
- Sacred SHA непроменена.
