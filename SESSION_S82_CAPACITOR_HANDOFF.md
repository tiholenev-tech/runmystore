# SESSION S82.CAPACITOR HANDOFF — 22.04.2026

## ✅ Завършено тази сесия

- Node 22 инсталиран на droplet
- mobile/ директория с Capacitor 8.3.1
- @capacitor-community/bluetooth-le@8.1.3
- GitHub Actions — Android APK Build работи
- APK билдва успешно
- index.php router (session → chat/onboarding/login) — commit 18dad57
- .htaccess DirectoryIndex + Options -Indexes — commit 5f97e39
- Safe-area-inset fix за 6 файла — commit de49554
- js/capacitor-printer.js с pair/print/test/forget API
- wizPrintLabelsMobile hook в products.php step 6 — commit 069c63c
- printer-setup.php — dedicated pairing page — commit 5f384e0
- ua-debug.php — diagnostic страница — commit 9115d5b

## 🚨 БЛОКЕР (за Claude Code да реши)

**Симптом:** APK отваря runmystore.ai в external Chrome browser, НЕ в Capacitor WebView.

**Доказателство (ua-debug.php от APK):**
- UA: Mozilla/5.0 (Linux; Android 10; K) Chrome/147.0.0.0 (НЯМА wv маркер)
- window.Capacitor: undefined
- window.CapacitorBluetoothLe: undefined

**Пробвано (не работи):**
1. server.url https://runmystore.ai (commit 0bbe881)
2. JS redirect в www/index.html
3. hostname-only config (commit 985e5fc)
4. URL param + sessionStorage fallback (commit bb25add)

## 📋 Задача за Claude Code (S82.CAPACITOR.2)

**ЦЕЛ:** APK зарежда runmystore.ai в Capacitor WebView (не browser) → window.Capacitor работи → BLE принтер pair/print работи.

**UX изискване:**
- Pair веднъж в printer-setup.php
- Всяко следващо печатане — автоматично, БЕЗ питане за избор

**Опции за опит (по приоритет):**

1. Hybrid mode: www/index.html локално + fetch + DOM inject
2. Iframe + postMessage bridge
3. Различна Capacitor версия (7.x или 6.x)
4. Custom Android WebView activity (Kotlin)
5. PWA + Service Worker
6. Capacitor runtime.js hosted от runmystore.ai

**Debug:**
- adb logcat (USB debugging)
- chrome://inspect
- /ua-debug.php
- /printer-setup.php

**Не пипай:**
- products.php (Chat 1)
- chat.php (Chat 2 активно работи)
- build-prompt.php (Chat 2)
- config/*.php
- DB schema

**Пипай:**
- mobile/*
- js/capacitor-printer.js
- printer-setup.php
- ua-debug.php
- нови файлове (printer-bridge.html и т.н.)

**Commit convention:** S82.CAPACITOR.N: description

**Когато работи:**
- git tag v0.6.0-s82-capacitor
- Update MASTER_COMPASS.md: S82 done, DTM-5811 ✅, Phase A BT print ✅
- Add S82.5 SECURITY (биометрия+PIN)
- REWORK: iOS Capacitor → S85.5

## 🔑 Константи

- Tenant test: 7 (663 products)
- Droplet: /var/www/runmystore/
- Repo: https://github.com/tiholenev-tech/runmystore (public)
- DTM-5811 BDA: DC:0D:51:AC:51:D9, PIN: 0000
- Samsung Z Flip6 — тест устройство на Тихол
