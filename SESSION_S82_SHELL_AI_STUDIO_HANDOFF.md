# SESSION S82.SHELL + S82.AI_STUDIO — HANDOFF

**Дата:** 25 април 2026
**Модел:** Claude Code (Opus 4.7, 1M context)
**Статус:** ✅ CLOSED — push-нато на main
**Последен commit:** `c12566b`
**Файлове създадени:** 8 нови, 8 модифицирани, 3 stale изтрити

---

## 🎯 SCOPE — какво поиска Тихол

1. Унифициран хедър (лого + принтер + settings + logout + theme toggle) **навсякъде**
2. Унифициран bottom nav с 4 икони (AI / Склад / Справки / Продажба) **навсякъде**
3. Чат прозорец („Кажи или напиши...") във всички модули
4. Цялостен redesign по `chat.php` neon стил, dark default + light optional, **вкл.** `login.php` + `onboarding.php`
5. AI Studio: **bash снимка → bg removal на момента → AI цветове → next step авто-попълнен**
6. Capacitor sync за app

**Технически избори от Тихол:**
- A — Gemini Vision за color detection
- B — fal.ai birefnet за bg removal
- C — auto-flow (без manual review между стъпките; user одобрява накрая)

---

## ✅ DONE

### S82.SHELL — visual unification

| Commit | Файл/обхват |
|---|---|
| `df7de9f` | `partials/{header,bottom-nav,chat-input-bar,shell-init,shell-scripts}.php` + `css/shell.css` |
| `172e730` | Wired в 6 модула: chat / products / sale / inventory / warehouse / stats |
| `112b6b3` | `settings.php` (real, auth-protected) — заменя моя по-ранен `settings.html` stub. Изтрити: `settings.html`, `finance.html`, `S82_UI_PROTOCOL.md`. |
| `9f1e1a2` | `login.php` + `onboarding.php` — `theme.css` link + viewport-fit=cover + FOUC-free theme init |

**Header order (final):** RUNMYSTORE.AI brand → plan badge → spacer → 🖨 принтер → ⚙ settings → ↩ logout → ☀️/🌙 theme toggle.

**Bottom-nav active tab:** auto-detected от `basename($_SERVER['SCRIPT_NAME'])` чрез `partials/shell-init.php`. AI таб = active за chat / simple / life-board / index.

**Chat input bar:** sticky над bottom-nav-а във всеки модул. Click → `rmsOpenChat()` в `partials/shell-scripts.php` → ако модулът има local `openChat()` (chat.php) → call it; иначе → `location.href='chat.php'`.

### S82.AI_STUDIO — AI features

| Commit | Файл/обхват |
|---|---|
| `f529bc6` | DB migration `20260425_001_ai_image_usage` (вече applied) + `ai-image-credits.php` + `ai-image-processor.php` (fal.ai birefnet) + `ai-color-detect.php` (Gemini Vision) |
| `c12566b` | `products.php` wizard auto-flow + auto-populate на цветовете в Step "Варианти" |

**Auto-flow:** Photo upload → click „🪄 AI обработи" → parallel POST към bg removal + color detect → success: preview update + green pill bar с откритите цветове → Step "Варианти": колор axis pre-populates с открития names (ако оси `Цвят/color` няма, преименува първата generic „Вариация N" на „Цвят").

**Plan limits (server-enforced):** FREE 0/ден · START 3/ден · PRO 10/ден. Per `(tenant_id, day, operation)` brояч в `ai_image_usage`. Записва се само при success.

**UI labels:** само „AI" — никога Gemini/fal.ai (BIBLE Закон).

### Cleanup

- Изтрити: `settings.html`, `finance.html`, `S82_UI_PROTOCOL.md` (stale stubs от по-рано в сесията).
- TODO в `products.php:5920` (`// TODO: fal.ai birefnet call`) — закрит. Същият TODO остана в `products_fetch.php:5609` — оставих го (файлът не е include-нат отникъде, кандидат за изтриване в next session).

---

## ⚠️ ИЗИСКВА ДЕЙСТВИЕ ОТ ТИХОЛ

### 1. FAL_API_KEY — задължително за bg removal

`/etc/runmystore/api.env` НЯМА `FAL_API_KEY`. Открих го с grep — само OPENAI + GEMINI x2 присъстват. Без този key:
- Gemini Vision color detection РАБОТИ (споделя GEMINI_API_KEY от chat-send.php)
- fal.ai bg removal **НЕ работи** — endpoint връща 503 с ясно съобщение „AI Studio: липсва конфигурация"
- UI gracefully показва грешката („AI обработката не успя"), не се чупи

**Действие:**
```bash
sudo nano /etc/runmystore/api.env
# Добави нов ред:
# FAL_API_KEY=твоят_ключ_от_fal.ai
sudo chmod 600 /etc/runmystore/api.env
sudo chown www-data:www-data /etc/runmystore/api.env
sudo systemctl reload apache2
```

Където да получиш fal.ai key: https://fal.ai/dashboard/keys

### 2. Capacitor app — обновяване

**Добра новина:** не трябва rebuild на APK. Твоят `mobile/capacitor.config.json` е конфигуриран със `server.url = "https://runmystore.ai"`, което значи app-ът зарежда страниците live от сървъра. Push на main → app вижда промените при next reload.

**Какво трябва да направиш на телефона:**
1. Force-quit на RunMyStore app (swipe up + close)
2. Отвори отново → ще зареди свежите файлове от сървъра
3. Ако виждаш стар layout → Settings → Apps → RunMyStore → Storage → **Clear cache** (НЕ "Clear data" — това трие сесията) → отвори app

**Кога ще трябва rebuild на APK:**
- Само ако нещо в `mobile/**` се промени (Capacitor plugins, AndroidManifest и т.н.)
- В тази сесия НЕ съм пипал `mobile/`, така че rebuild НЕ е нужен.

GitHub Actions автоматично билдва нов APK при `mobile/**` push — workflow `.github/workflows/android-build.yml`.

### 3. Browser cache (за тестване в браузър)

При тест в Chrome / Safari на phone:
- DevTools → Network → tick "Disable cache"
- Или hard reload: Ctrl+Shift+R (desktop) / pull-to-refresh + force quit (mobile Safari/Chrome)

Cache-busting query strings (`?v=filemtime`) са добавени към `theme.css` и `shell.css` — нови version-и ще се теглят автоматично, но aggressive WebView cache може да държи за 5-10 мин.

---

## 🟡 ОТЛОЖЕНО / ОТКРИТИ ЗА БЪДЕЩ ETAP

| Тема | Защо |
|---|---|
| `products_fetch.php` (568 KB) | Не е reference-нат отникъде. Съдържа стария `doStudioWhiteBg` TODO. Кандидат за изтриване (-1 файл, -568K). |
| Старите `toggleTheme()` / `initTheme()` функции в всеки модул | Orphaned dead code (никой не ги вика повече — всички header-и викат `rmsToggleTheme`). Безвредно, но боклук. ~30 реда per file. |
| `simple.php` link в по-стария chat.php header | Премахнат при migration към `partials/header.php`. Тихол не го е поискал в новата спецификация. Ако трябва обратно — добави в `partials/header.php`. |
| `store-switch` в products.php | Преместен в `title-row` (под header). UX-wise все още работи, но е по-малко visible. |
| Light theme background gradients в `login.php` | Hardcoded gradients остават dark в light mode. Не е блокер (login = 5 сек). |
| Capacitor edge-to-edge config | За да `env(safe-area-inset-bottom)` връща реална стойност вместо 0. Известно от S82 REWORK queue. |
| Real biometrics + PIN | S82.5 (вече планирано). Settings page има placeholder. |

---

## 📊 ПРЕРЕЗ НА ПРОЕКТА

**Нови файлове:**
- `partials/shell-init.php`, `header.php`, `bottom-nav.php`, `chat-input-bar.php`, `shell-scripts.php`
- `css/theme.css`, `css/shell.css`
- `ai-image-credits.php`, `ai-image-processor.php`, `ai-color-detect.php`
- `migrations/20260425_001_ai_image_usage.{up,down}.sql`
- `settings.php`
- този handoff

**Модифицирани:** chat.php, products.php, sale.php, inventory.php, warehouse.php, stats.php, login.php, onboarding.php

**Изтрити:** settings.html, finance.html, S82_UI_PROTOCOL.md

**DB:** нова таблица `ai_image_usage`

---

## 🔀 PARALLEL SESSION

Тази сесия течеше паралелно с друг chat върху AI мозъка (chat-send.php / build-prompt.php / compute-insights.php). FILE LOCK на products.php държан от тази сесия е свален с push на `c12566b`.

---

## 🎯 NEXT SUGGESTED SESSION

**S82.6 — AI Studio polish + cleanup:**
1. Изтриване на `products_fetch.php` (потвърди dead code)
2. Изтриване на orphaned `toggleTheme()` / `initTheme()` от 5 модула
3. Real Bluetooth printer status sync (`window.rmsPrinterStatus = 'paired' | 'error' | 'idle'` от capacitor-printer.js → визуален dot в header)
4. Test с реален FAL_API_KEY на тест tenant=7
5. (по желание) Color name → hex mapping в CFG.colors за да се покажат цветни pills в Step "Варианти"

**S82.7 — Capacitor edge-to-edge:**
- Configure Android WindowInsets edge-to-edge → `env(safe-area-inset-*)` връща реални стойности → 120px fallback в product wizard footer може да stане чист `env()`.

---

## ✅ EXIT CRITERIA

- [x] 6 commits на main (`df7de9f`, `172e730`, `112b6b3`, `9f1e1a2`, `f529bc6`, `c12566b`)
- [x] Всички 6 модула php -l clean
- [x] Backend endpoints отговарят правилно (401 Unauthorized без сесия, 429 при quota exceeded, 503 при липсващ API key)
- [x] DB миграция applied
- [x] Handoff записан (този файл)
- [ ] FAL_API_KEY добавен на сървъра (ОЧАКВА Тихол)
- [ ] On-device test на Samsung Z Flip6 (ОЧАКВА Тихол)
