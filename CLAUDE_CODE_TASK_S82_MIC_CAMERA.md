# TASK FOR CLAUDE CODE — S82.CAPACITOR.MIC_CAMERA

**От:** Opus 4.7 (CHAT 1.3 — S81.BUGFIX.V2)
**Дата:** 24.04.2026
**Приоритет:** P0 (блокер за ЕНИ voice-first + баркод workflow)

---

## ПРОБЛЕМ

В Capacitor APK (Samsung Z Flip6 на Тихол, Android 16, UA `...wv`):
- ❌ Микрофонът не работи (тап на 🎤 → нищо, никакъв permission prompt)
- ❌ Камера/баркод скенер не работи (тап на scan → нищо)
- ✅ Работят в Chrome browser на същия телефон на runmystore.ai

## ВЕЧЕ НАПРАВЕНО ОТ OPUS 4.7

1. ✅ `mobile/android/app/src/main/AndroidManifest.xml` — добавени permissions:
   - `RECORD_AUDIO`
   - `MODIFY_AUDIO_SETTINGS`
   - `CAMERA`
   - `uses-feature microphone` (required=false)
   - `uses-feature camera` (required=false)
   - Commit: `a717cde`

2. ✅ APK rebuild през GitHub Actions, инсталиран на телефона от Тихол

3. ❌ **РЕЗУЛТАТ: Нищо не се промени.** Нито микрофона, нито камерата работят. Android НЕ показва permission prompt при тап.

## ДИАГНОЗА

Manifest permissions не са достатъчни за Capacitor WebView. Capacitor има **2 слоя permissions**:
1. **Manifest level** — декларация (DONE ✓)
2. **Runtime level** — WebView трябва да преодолее `PermissionRequest` event от JavaScript `getUserMedia()` / `SpeechRecognition`

Capacitor по default **не propagate-ва** browser permission request-а към Android. Трябва custom bridge.

## ВЪЗМОЖНИ РЕШЕНИЯ (за Claude Code да избере)

### Вариант 1 — Capacitor Voice Recorder plugin + Speech Recognition plugin
- `@capacitor-community/speech-recognition`
- Native speech recognition, не Web Speech API
- Замества `webkitSpeechRecognition` в `wizMic()` (products.php ред 8103)
- НО: променя JS код в products.php (конфликт с CHAT 1)

### Вариант 2 — WebChromeClient permission passthrough (препоръчително)
- Override `WebChromeClient.onPermissionRequest()` в Capacitor MainActivity.java
- Дава WebView-а правото да показва native permission prompts
- Нула JS промени — `wizMic()` продължава да ползва Web Speech API
- Само Kotlin/Java код в `mobile/android/app/src/main/java/...`

### Вариант 3 — Capacitor Camera + BarcodeScanner plugins
- `@capacitor-mlkit/barcode-scanning` (native, не Web API)
- Замества `wizScanBarcode()` в products.php
- Същия проблем: променя products.php

## ПРЕПОРЪКА

**Вариант 2** — MainActivity.java override. Нула JS промени. Web Speech API + getUserMedia продължават да работят. Само permission bridge.

## ФАЙЛОВЕ КОИТО ТЕ ОЧАКВАТ

- `mobile/android/app/src/main/java/ai/runmystore/app/MainActivity.java` (или каквото е package-а)
- `mobile/android/app/src/main/AndroidManifest.xml` (permissions DONE — проверка)

## ЗАБРАНЕНО

- ❌ products.php (CHAT 1 работи там — S81.PRODUCTS_COMPLETE)
- ❌ chat.php
- ❌ compute-insights.php

## ПРОВЕРКА ЧЕ РАБОТИ

1. Rebuild APK
2. Uninstall старата, инсталирай новата
3. Тап на 🎤 в products.php wizard
4. Android показва prompt "RunMyStore wants to record audio"
5. Allow → mic индикатор се вижда → говориш → текст се появява в поле "Име"
6. Същото за баркод — тап на scan → camera prompt → Allow → камера отваря се

## ГИТ КОНТЕКСТ

- Branch: main
- Последен commit (Opus 4.7): `a717cde`
- CHAT 2 (S79.SELECTION_ENGINE) работи на: `migrations/`, `config/ai_topics.php`, `selection-engine.php`
- Ти работиш на: `mobile/android/` — нула overlap

Git tag при успех: `v0.6.0-s82-mic-camera`

