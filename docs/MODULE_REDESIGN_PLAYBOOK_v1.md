# 📐 MODULE REDESIGN PLAYBOOK v1.0

**За кого:** За всеки чат (Claude / Code / друг агент) който прави redesign на модул.
**Цел:** Да не губим часове в "design-kit правила vs реалност" дискусии. Реалността печели.
**Created:** 2026-05-12 (от S141 шеф-чат, след откриване).

---

## ⚡ TL;DR (за бързо четене)

1. **chat.php (canonical SWAP файл от S140) НЕ импортира design-kit/.** Има inline CSS от mockup.
2. **design-kit/README.md е "идеал" — chat.php е "реалност".** При конфликт → следвай chat.php pattern.
3. **2 типа header:** Тип А (главни — chat.php, life-board.php) с mode toggle + 4 orbs bottom-nav. Тип Б (вътрешни — products, sale, deliveries) с **камера/филтър/кошница бутон вместо mode toggle** + опростено.
4. **Wizard на products.php (voice + Gemini color) е SACRED** — никога не го пипай вътре. Само го include-вай.
5. **SWAP workflow proven** в S140 за chat.php → applies 1:1 за products-v2.php → products.php.

---

## 1. ОТКРИТИЕТО: design-kit/ vs canonical reality

### Документ казва:

> `design-kit/README.md`: "Всеки нов модул ЗАДЪЛЖИТЕЛНО импортира 5 CSS файла: tokens.css → components-base.css → components.css → light-theme.css → header-palette.css. НЕ пиши свой `.glass`, `.shine`, `.lb-card`."

### Реалност в chat.php (canonical SWAP от S140):

```bash
$ grep "design-kit" chat.php
# (нищо)
```

**chat.php има 60 KB inline CSS** в `<style>` блока си. Дефинира собствените си `.glass`, `.shine`, `.glow`, `.lb-card`, `.aurora`, и т.н. **БЕЗ да импортира design-kit/.**

### Защо е важно

Преди това откритие, всеки нов чат:
1. Чете `design-kit/README.md`
2. Опитва да следва "правилата"
3. Inject-ва CSS в production файл
4. CSS conflict с старо CSS
5. Различни bugs (точки във фон, плосък дизайн, дублирани класове)
6. Часове губени в opити да override-ва старо CSS

**След откритието:** Нов модул прави **standalone файл** с собствен inline CSS от mockup. Без import-и. Без CSS overrides. Чисто.

### Заключение

| design-kit/ | chat.php / canonical patterns |
|---|---|
| Документация / идеал | Production реалност |
| 5 CSS файла за импорт | Inline CSS във файла |
| `.mod-*` prefix за нови класове | Без prefix — класове от mockup |
| README.md правила | Working pattern |

**При конфликт → следвай chat.php pattern, не design-kit/README.md.**

---

## 2. ДВА ТИПА HEADER (от S140_FINALIZATION §2.1-2.2)

### Тип А — Главни страници (chat.php, life-board.php)

```html
<header class="rms-header">
  <a class="rms-brand"><span class="brand-1">RunMyStore</span><span class="brand-2">.ai</span></a>
  <span class="rms-plan-badge">PRO</span>
  <div class="rms-header-spacer"></div>
  <a class="rms-icon-btn" aria-label="Принтер" href="printer-setup.php"><svg .../></a>
  <a class="rms-icon-btn" aria-label="Настройки" href="settings.php"><svg .../></a>
  <button class="rms-icon-btn" aria-label="Изход" onclick="logout()"><svg .../></button>
  <button class="rms-icon-btn" id="themeToggle" onclick="rmsToggleTheme()"><svg .../></button>
</header>
```

**Има:**
- ✅ Brand "RunMyStore.ai" + PRO badge
- ✅ 4 икона бутона (Принтер, Настройки, Изход, Тема)
- ✅ Subbar **с mode toggle** (Лесен ↔ Разширен)
- ✅ Bottom-nav 4 orbs (AI / Склад / Справки / Продажба)

**За кого:** Само за chat.php (Detailed home) и life-board.php (Simple home).

### Тип Б — Вътрешни модули (products.php, sale.php, deliveries.php, orders.php, transfers.php, settings.php, и т.н.)

```html
<header class="rms-header">
  <a class="rms-brand"><span class="brand-1">RunMyStore</span><span class="brand-2">.ai</span></a>
  <span class="rms-plan-badge">PRO</span>
  <div class="rms-header-spacer"></div>
  <!-- МОДУЛЕН БУТОН (специфичен за модула) -->
  <button class="rms-icon-btn" aria-label="Камера/Филтър/Кошница" onclick="moduleSpecific()"><svg .../></button>
  <a class="rms-icon-btn" aria-label="Принтер" href="printer-setup.php"><svg .../></a>
  <a class="rms-icon-btn" aria-label="Настройки" href="settings.php"><svg .../></a>
  <button class="rms-icon-btn" aria-label="Изход" onclick="logout()"><svg .../></button>
  <button class="rms-icon-btn" id="themeToggle" onclick="rmsToggleTheme()"><svg .../></button>
</header>
```

**Има:**
- ✅ Brand "RunMyStore.ai" + PRO badge (1:1 с Тип А)
- ✅ 4 икона бутона + **специфичен модулен бутон отдясно (преди другите икони)**
- ✅ Subbar **без mode toggle ВЪТРЕ В МОДУЛА** — но има link "→ Лесен / Разширен" към chat.php / life-board.php
- ❌ **БЕЗ bottom-nav 4 orbs** (защото вече си в модул, не в главна страница)
- ❌ Custom chat-input-bar за simple mode (не bottom-nav)

**За кого:** Всички вътрешни модули.

### Модулните бутони (Тип Б)

| Модул | Модулен бутон | Защо |
|---|---|---|
| **products.php** | 📷 Камера | Сканирай баркод за бърз search |
| **sale.php** | 🛒 Кошница / Numpad | Кошница с артикули в продажбата |
| **deliveries.php** | 📷 Камера + 🎙️ OCR voice | Снимка на фактура за OCR |
| **orders.php** | 📋 Списък | Виж активни поръчки бързо |
| **transfers.php** | 🔄 Между обекти toggle | Прехвърляне между магазини |
| **inventory.php** | 🚶 Zone walk start | Започни инвентаризация |
| **settings.php** | (нищо специално) | Стандартни 4 икони |

### Subbar разлика

**Тип А** subbar:
```
[Магазин ▾] НАЧАЛО [Лесен / Разширен →]
```

**Тип Б** subbar:
```
[Магазин ▾] СКЛАД / ПРОДАЖБА / ДОСТАВКИ / ... [← Лесен / Разширен]
```
- Mode toggle = link към главна (chat.php / life-board.php), не switch вътре в модула
- Label `subbar-where` показва името на модула:
  - products.php → "СКЛАД"
  - sale.php → "ПРОДАЖБА"
  - deliveries.php → "ДОСТАВКИ"
  - orders.php → "ПОРЪЧКИ"
  - transfers.php → "ТРАНСФЕРИ"
  - inventory.php → "ИНВЕНТАРИЗАЦИЯ"

---

## 3. CHAT-INPUT-BAR vs BOTTOM-NAV

**SIMPLE MODE (mode-simple body class):**
- ✅ Sticky chat-input-bar отдолу с pulsing mic + send drift
- ❌ НЯМА bottom-nav 4 orbs

**DETAILED MODE (mode-detailed body class):**
- ❌ НЯМА chat-input-bar
- ✅ Bottom-nav 4 orbs само в Тип А (chat.php). В Тип Б модулите → също скрит ИЛИ ползва се за бърза навигация (depends on module).

**Закон от SIMPLE_MODE_BIBLE §5.2:**
> "В Simple Mode body class `mode-simple` → CSS hide на bottom nav."

```css
body.mode-simple .rms-bottom-nav { display: none !important; }
body.mode-detailed .chat-input-bar { display: none !important; }
```

---

## 4. SACRED — никога не пипай в products.php

| Какво | Файл / редове | Защо |
|---|---|---|
| Voice (Whisper Tier 2 Groq) | `services/voice-tier2.php` (333 реда) | 1 ден работа за БГ числа |
| Voice routing | products.php `wizMic`, `_wizMicWhisper`, `_wizMicWebSpeech` | Sacred от S99 |
| БГ числа parser | `_wizPriceParse`, `_bgPrice` | Commits `4222a66` + `1b80106` LOCKED |
| Color detect (Gemini) | `ai-color-detect.php` (296 реда) | 4-color detection + multi-image |
| Wizard mic бутони | products.php редове 11088, 11097, 11109, 11120, 11148, 11157, 11182, 11193 | 8 input полета — voice working |
| Print (DTM-5811 BLE + D520BT SPP) | `js/capacitor-printer.js` (2097 реда) | 2 принтера, 2 транспорта |

**Правило:** При extract на wizard в `partials/products-wizard.php` — копираш 1:1, **БЕЗ ПРОМЯНА**.

---

## 5. SWAP WORKFLOW (proven в S140)

### Стъпки

```bash
# 1. Backup tag ПРЕДИ start
git tag pre-products-redesign-S141
git push origin pre-products-redesign-S141

# 2. Създай нов файл (от 0, не базиран на старо)
touch products-v2.php
# ... пиши кода (PHP backend + HTML + inline CSS + JS)

# 3. Тествай паралелно
# /var/www/runmystore/products.php       — стар, работещ
# /var/www/runmystore/products-v2.php    — нов, в development

# 4. Когато визията е готова, SWAP:
git mv products.php products.php.bak.S141
git mv products-v2.php products.php
git commit -m "S141 SWAP: products-v2 → production"
git push origin main

# 5. На droplet:
cd /var/www/runmystore && git pull origin main

# 6. Revert (emergency):
git reset --hard pre-products-redesign-S141 && git push origin main --force
```

### Защо работи (опит от S140)

Предишните стратегии (S133/S135/S136 — пълен rewrite чрез Code от scratch) **счупваха визията**: DOM diff 31% при iter 5 → AUTO-ROLLBACK.

S140 SWAP стратегия (reverse approach):
1. Взимаш mockup 1:1 (P11/P10)
2. Inject-ваш PHP логика блок по блок (PHP queries → static числа замени)
3. Тестваш всеки блок поотделно
4. SWAP накрая

**Резултат:** ZERO визуални счупвания. Production deployment в 1 ден.

---

## 6. INLINE CSS PATTERN (от chat.php)

### Структура на head section

```html
<!DOCTYPE html>
<html lang="bg" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>[Модул] · RunMyStore.AI</title>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

<!-- Theme bootstrap -->
<script>(function(){try{var s=localStorage.getItem('rms_theme')||'light';document.documentElement.setAttribute('data-theme',s);}catch(_){document.documentElement.setAttribute('data-theme','light');}})();</script>

<style>
/* ВСИЧКИЯТ CSS тук — inline, от mockup 1:1 */
/* Базови: * { box-sizing }, html, body, button, a */
/* Tokens: :root { --hue1, --hue2, --radius, --ease, etc. } */
/* Light theme: :root[data-theme="light"] { --bg-main, --surface, --shadow-card, ... } */
/* Dark theme: :root[data-theme="dark"] { ... } */
/* Components: .glass, .shine, .glow, .lb-card, .aurora, .top-row, .cell, ... */
/* Animations: @keyframes ... */
</style>
</head>
<body>
...
</body>
</html>
```

### ВАЖНО

- **БЕЗ <link> към design-kit/** — нищо от там
- **БЕЗ partial-header.html include** — хедърът е inline в файла
- **БЕЗ partial-bottom-nav.html include** — навигацията е inline
- **БЕЗ palette.js / theme-toggle.js** — JS е inline в `<script>` блок преди `</body>`

---

## 7. SHARED ЧАСТИ (между модулите)

Тези елементи трябва да са **1:1 копирани** между всички модули за визуална консистентност:

| Елемент | Source | Класове |
|---|---|---|
| Aurora background | chat.php :1676 | `.aurora`, `.aurora-blob` |
| Header brand + plan badge | chat.php :1680 | `.rms-brand`, `.brand-1`, `.brand-2`, `.rms-plan-badge` |
| Header icon buttons | chat.php :1684+ | `.rms-icon-btn`, 22×22, 11×11 SVG, gap 4px |
| Subbar | chat.php :1700 | `.rms-subbar`, `.rms-store-toggle`, `.subbar-where`, `.lb-mode-toggle` |
| Chat-input-bar | chat.php :2366 | `.chat-input-bar`, `.chat-input-icon`, `.chat-input-text`, `.chat-mic`, `.chat-send` |
| Bottom-nav | chat.php :2452 | `.rms-bottom-nav`, `.rms-nav-tab`, `.nav-orb`, `.rms-nav-tab-label` |
| Glass neon card | chat.php (CSS) | `.glass`, `.shine`, `.glow`, `.glow-bottom`, `.shine-bottom`, 4 spans |
| Hue классы | chat.php (CSS) | `.qd`, `.q1`, `.q2`, `.q3`, `.q4`, `.q5`, `.q6` |

**За нов модул:** копирай тези блокове 1:1 от chat.php. Промените само main content (`<main class="app">...</main>`).

---

## 8. SACRED RULES (никога не нарушавай)

От `S140_FINALIZATION.md §2.7` + `CLAUDE_AUTO_BOOT.md §3.7`:

| # | Sacred Rule |
|---|---|
| 1 | НИКОГА hardcoded БГ текст без `$T['...']` или `tenant.lang` |
| 2 | НИКОГА hardcoded `BGN/лв/€` — винаги `priceFormat()` |
| 3 | НИКОГА `ADD COLUMN IF NOT EXISTS` (MySQL не поддържа) |
| 4 | НИКОГА `sed` за file edits — само Python scripts |
| 5 | НИКОГА emoji в UI — само SVG |
| 6 | НИКОГА native клавиатура в sale.php (custom numpad) |
| 7 | НИКОГА `<?= htmlspecialchars($T['...'] ?? '') ?>` без БГ fallback |
| 8 | НИКОГА hardcoded `<html data-theme="dark">` (чупи toggle) |
| 9 | НИКОГА пипай wizard mic buttons или voice/color sacred files |

---

## 9. ПРЕДИ ДА ЗАПОЧНЕШ нов модул редизайн

### Pre-flight checklist

1. **Прочети:**
   - Този документ (`docs/MODULE_REDESIGN_PLAYBOOK_v1.md`)
   - `docs/S140_FINALIZATION.md` (Universal UI Laws §2)
   - `docs/KNOWN_BUGS.md` (unsolved bugs)
   - `CLAUDE_AUTO_BOOT.md` (workflow patterns)
   - `[модул]_DESIGN_LOGIC.md` (специфика на модула)

2. **Backup tag:**
   ```bash
   git tag pre-<module>-redesign-S<NUM>
   git push origin pre-<module>-redesign-S<NUM>
   ```

3. **SCAN `docs/COMPETITOR_INSIGHTS_TRADEMASTER.md`** за features свързани с модула.

4. **Прочети canonical mockup:**
   - chat.php (като референция за shell + inline CSS)
   - Модул-специфичен mockup (P3, P15, P2v2, и т.н.)

5. **Опиши план в 3-5 точки → Тих confirm → действай.**

---

## 10. КАК ПИШЕШ НОВ МОДУЛ (стъпки от S140 опит)

### Стъпка 1: Shell

- PHP backend (auth + tenant + store + основни queries) — копирай първите 50 реда от chat.php
- HTML head 1:1 от chat.php (inline CSS pattern)
- Body opening + aurora + header (Тип Б за вътрешен модул, Тип А за главна) + subbar
- Empty `<main class="app">` placeholder

### Стъпка 2: Inline CSS

- Копирай ВСИЧКИЯТ inline CSS от chat.php (60 KB) → adjust за модул-specific class имена
- Add нови класове за модул-specific UI elements (без prefix — следва canonical pattern)
- Включи light + dark theme tokens (`:root[data-theme="light"]`, `:root[data-theme="dark"]`)

### Стъпка 3: Main content

- За simple mode: P15-like layout (тревоги + add + AI поръчка + AI insights)
- За detailed mode: P2v2-like tabs (Преглед / Графики / Управление / Артикули)
- Conditional render: `<?php if ($is_simple_view): ?>...<?php else: ?>...<?php endif; ?>`

### Стъпка 4: Footer

- Chat-input-bar за simple mode
- Bottom-nav 4 orbs за detailed mode (опционално в Тип Б)
- Inline JS handlers

### Стъпка 5: AJAX endpoints

- Копирай нужните endpoints от стария модул (search, save, load, stats, ...)
- НЕ пипай sacred ендпойнти (voice-tier2, ai-color-detect)

### Стъпка 6: Wizard / Modal

- Extract съществуващи sacred блокове (като wizard на products.php) в `partials/[модул]-wizard.php`
- Include в новия модул с `<?php include ... ?>`
- НЕ модифицирай съдържанието

### Стъпка 7: SWAP

```bash
git mv <module>.php <module>.php.bak.S<NUM>
git mv <module>-v2.php <module>.php
git commit -m "S<NUM> SWAP: <module>-v2 → production"
git push origin main
```

---

## 11. БЪДЕЩИ МОДУЛИ (предстоящи)

| Модул | Статус | Mockup | Notes |
|---|---|---|---|
| chat.php | ✅ DEPLOYED (S140) | P11 | Тип А, главна detailed |
| life-board.php | ✅ DEPLOYED (S140) | P10 | Тип А, главна simple |
| products.php | 🔄 IN PROGRESS (S141) | P15 + P2v2 | Тип Б, с wizard |
| sale.php | ⏳ PENDING | TBD | Тип Б, с кошница + numpad |
| deliveries.php | ⏳ PENDING | TBD | Тип Б, с OCR + voice |
| orders.php | ⏳ PENDING | TBD | Тип Б, с активни поръчки |
| transfers.php | ⏳ PENDING | TBD | Тип Б, между обекти |
| inventory.php | ⏳ PENDING | TBD | Тип Б, zone walk |
| customers.php | ⏳ PENDING (NEW) | TBD | Тип Б, нов модул |
| settings.php | ⏳ REFRESH | TBD | Тип Б, без модулен бутон |
| stats.php | ⏳ PENDING | TBD | Тип Б, графики |

Всеки модул → отделен redesign session → проверка с този документ.

---

## 12. КОНФЛИКТИ И КАК ДА ГИ РЕШИШ

| Конфликт | Решение |
|---|---|
| `design-kit/README.md` казва X, `chat.php` прави Y | **chat.php печели** (canonical) |
| Тих казва нещо vs документ казва нещо различно | **Тих печели** (питай за яснота) |
| Два mockup-а с различни визии за същия елемент | Питай Тих кой е canonical |
| Sacred wizard inside vs UI promени | Sacred печели — НЕ пипай |
| Visual perfection vs Sacred preservation | Sacred печели винаги |

---

## 13. КАК ДА БЪДЕШ ЕФЕКТИВЕН (комуникация с Тих)

**Тих не е developer.** Той не пише код. Не разбира git. Прави voice-to-text → typografski грешки, фрагменти, CAPS = urgency.

✅ **Прави:**
- БГ, кратко (2-3 изречения макс)
- Команди в ═══ блокове за copy-paste
- "Push мина (commit abc1234). На droplet: ..."
- Visual проблем → веднага fix
- UX/логически → питай първо

❌ **НЕ прави:**
- "Готов ли си?"
- "ОК?"
- "Започвам?"
- Дълги обяснения
- Многословие (декларирано "ти си многословен" от Тих)

---

## 14. КАК ДА ДОКУМЕНТИРАШ ОТКРИТИЯ

Когато откриеш разлика между документация и реалност (както този document с design-kit vs chat.php):

1. **Веднага** добави в `docs/MODULE_REDESIGN_PLAYBOOK_v<N>.md` (този файл).
2. Bump version number в title.
3. Commit message: `docs: MODULE_REDESIGN_PLAYBOOK v<N>.<X> — открито [какво]`.
4. Push.
5. Кажи на Тих: "Записано в playbook v<N>.<X> за бъдещи чатове."

---

## 15. END OF PLAYBOOK

**Last updated:** 2026-05-12 (от S141 шеф-чат).
**Next review:** При следващ модул редизайн.
**Living document:** Всеки чат може да допише при ново откритие.

**Source of truth ranking:**
1. **chat.php (canonical SWAP файл)** — реалност на S140
2. **Този playbook** — кодифицирано знание от опит
3. **docs/S140_FINALIZATION.md** — Universal UI Laws
4. **docs/KNOWN_BUGS.md** — нерешени bugs
5. **CLAUDE_AUTO_BOOT.md** — workflow patterns
6. **SIMPLE_MODE_BIBLE.md** — Simple Mode правила
7. **design-kit/README.md** — идеал (последен, защото канонично се различава)

---

**Край.** Жив документ.
