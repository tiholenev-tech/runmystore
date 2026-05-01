# 🔒 DESIGN KIT — ЕДИНСТВЕНИЯТ ИЗТОЧНИК НА ИСТИНАТА

**Версия:** 1.1 · 01.05.2026
**Статус:** LOCKED. Не се преписва. Не се интерпретира. Не се "адаптира".

**v1.1 changelog (01.05.2026 — S89 GAP fix):**
- ➕ `theme-toggle.js` (нов задължителен файл). v1.0 имаше мъртъв theme бутон.
- ➕ Изрично правило: `<html lang="bg">` БЕЗ `data-theme="dark"` атрибут.
- ➕ check-compliance.sh: 8 → 10 проверки (theme-toggle присъства, html без data-theme).

---

## 🚨 ЗА ВСЕКИ ЧАТ / КЛОД КОД АГЕНТ / РАЗРАБОТЧИК

**Преди да напишеш един ред CSS за нов модул — прочети тук.**

Този файл и всичко в `/design-kit/` е **закон**. Не markdown с правила за интерпретация. Файлове за импорт. Точка.

---

## 📁 СЪДЪРЖАНИЕ НА `/design-kit/`

| Файл | Какво е | Кога се ползва |
|---|---|---|
| `tokens.css` | CSS variables (hue1, hue2, цветове, ease, fonts, glass-bg) | **ЗАДЪЛЖИТЕЛНО** на всеки модул |
| `components-base.css` | базови shell стилове (header / nav / input bar / fab) | **ЗАДЪЛЖИТЕЛНО** |
| `components.css` | реализирани компоненти (.glass / .qcard / .pill / .lb-card / .s82-dash / health / weather / chat...) | **ЗАДЪЛЖИТЕЛНО** |
| `light-theme.css` | iridescent override за `[data-theme="light"]` | **ЗАДЪЛЖИТЕЛНО** |
| `header-palette.css` | 2-row header + неон лого + 2 hue пъзгача | **ЗАДЪЛЖИТЕЛНО** |
| `theme-toggle.js` | **v1.1** — theme switch + localStorage + sun/moon icon swap | **ЗАДЪЛЖИТЕЛНО** |
| `palette.js` | живо превключване на --hue1/--hue2 | **ЗАДЪЛЖИТЕЛНО** |
| `partial-header.html` | готов HTML за header (4 икони + лого + пъзгачи) | copy 1:1 |
| `partial-bottom-nav.html` | 4 заключени таба (AI/Склад/Справки/Продажба) | copy 1:1 |
| `partial-chat-input-bar.html` | sticky chat input bar | copy 1:1 |
| `REFERENCE.html` | визуално еталон — отвори в браузъра, виж стандарта | reference |
| `PROMPT.md` | promt template за нов модул | paste в нов чат |
| `check-compliance.sh` | автоматична проверка | git pre-commit |

---

## ⛔ ЗАБРАНЕНО — НА ВСЕКИ ЦИК

Тези списъци са затворени. Ако нарушиш един — модулът се отхвърля.

### 1. **НИКОГА не пиши свой `.glass`, `.shine`, `.glow`, `.qcard`, `.pill`, `.btn-iri`, `.lb-card`, `.s82-dash-*`, `.briefing-*`, `.ai-studio-row`, `.health-*`, `.cb-mode-toggle`, `.rms-*`.**
   Те съществуват в `components.css` и `components-base.css`. Импортваш. Ползваш. Не дублираш.

### 2. **НИКОГА не пиши `:root { --hue1: ... }` или `:root { --hue2: ... }` в твоя модул.**
   Tokens идват от `tokens.css`. Override-ват се само през `[data-theme="light"]` в `light-theme.css`.

### 3. **НИКОГА не пиши свой `<header>` или `<nav>`.**
   Копираш `partial-header.html` и `partial-bottom-nav.html` 1:1.

### 4. **НИКОГА не пиши `backdrop-filter`, `conic-gradient`, `mask: linear-gradient(...) linear-gradient(...)`, или `mix-blend-mode: plus-lighter` в нов модул.**
   Тези patterns са на shine/glow и принадлежат на components.css. Ако ти трябват — означава че се опитваш да преписваш glass surface. Спри.

### 5. **НИКОГА не правиш свой gradient за активни pills `(.active / .sel)`.**
   Iridescent pearl style вече е дефиниран. Ползваш `.pill.active`.

### 6. **НИКОГА не променяш hue1/hue2 в HTML с `style="--hue1: 280"`.**
   Ползваш hue класовете от таблицата:

   | Клас | hue1 | hue2 | За какво |
   |---|---|---|---|
   | `.qd` | 255 | 222 | default (индиго) |
   | `.q1` | 0 | 15 | loss (червен) |
   | `.q2` | 280 | 310 | cause / magic (виолет) |
   | `.q3` | 145 | 175 | gain (зелен) |
   | `.q4` | 38 | 28 | order / amber |
   | `.q5` | 200 | 225 | ocean (син) |
   | `.q6` | 280 | 310 | AI prediction |

### 7. **НИКОГА не слагаш emoji в UI** (☀ 🌙 ✨ 📷 🟢). Само SVG.

### 8. **НИКОГА не сменяш Montserrat шрифта.**

### 9. **НИКОГА не пиши `<html lang="bg" data-theme="dark">`.** *(v1.1)*
   Hardcoded `data-theme="dark"` override-ва bootstrap script-а и **чупи toggle бутона** —
   localStorage `rms_theme=light` няма да се приложи на reload.

   ✔ Правилно: `<html lang="bg">` (БЕЗ атрибут)
   ✔ Bootstrap script в `<head>` сам set-ва `data-theme="light"` при нужда.

### 10. **НИКОГА не пропускаш `theme-toggle.js`.** *(v1.1)*
    Без него `onclick="rmsToggleTheme()"` в header-а е мъртъв бутон.
    Включваш го **ПРЕДИ** `palette.js`.

---

## ✅ ЗАДЪЛЖИТЕЛНО — НА ВСЕКИ НОВ МОДУЛ

### Стъпка 1: HEAD на твоя модул започва ТОЧНО така:

```html
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#08090d">
<title>[Заглавие] — RunMyStore.ai</title>

<!-- Theme bootstrap — ПЪРВОТО нещо в head, преди CSS -->
<script>try{if(localStorage.getItem('rms_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(_){}</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

<!-- DESIGN KIT — задължителни 5 файла, точно в този ред -->
<link rel="stylesheet" href="/design-kit/tokens.css?v=<?= @filemtime(__DIR__.'/design-kit/tokens.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components-base.css?v=<?= @filemtime(__DIR__.'/design-kit/components-base.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components.css?v=<?= @filemtime(__DIR__.'/design-kit/components.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/light-theme.css?v=<?= @filemtime(__DIR__.'/design-kit/light-theme.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/header-palette.css?v=<?= @filemtime(__DIR__.'/design-kit/header-palette.css') ?: 1 ?>">
</head>
```

### Стъпка 2: BODY включва задължителните partials + JS:

```html
<body class="has-rms-shell">

<?php include __DIR__ . '/design-kit/partial-header.html'; ?>

<main class="content">
    <!-- ТУК и САМО ТУК пишеш съдържанието на твоя модул -->
    <!-- Ползваш .glass, .qcard, .pill — НЕ ги пишеш отново -->
</main>

<?php include __DIR__ . '/design-kit/partial-bottom-nav.html'; ?>

<!-- v1.1: задължителен ред — theme-toggle ПРЕДИ palette -->
<script src="/design-kit/theme-toggle.js?v=<?= @filemtime(__DIR__.'/design-kit/theme-toggle.js') ?: 1 ?>"></script>
<script src="/design-kit/palette.js?v=<?= @filemtime(__DIR__.'/design-kit/palette.js') ?: 1 ?>"></script>

</body>
</html>
```

### Стъпка 3: НОВ CSS — само ако компонент НЕ съществува

Прочети `components.css`. Прочети `REFERENCE.html`. Ако компонентът съществува — ползваш го. Ако не съществува — питаш Тихол **преди** да го измислиш.

Ако Тихол одобри нов компонент:
- Пишеш го в `mod-[име-на-модул].css` с **prefix-нати класове**: `.mod-orders-table`, `.mod-orders-row`. Никога без prefix.
- Внасяш го в design-kit едва след одобрение.

---

## 🛡️ COMPLIANCE — Автоматична проверка

Преди commit:

```bash
bash /design-kit/check-compliance.sh path/to/your-module.php
```

Скриптът отказва модула ако намери:
- свой `.glass`, `.shine`, `.glow`
- inline `--hue1` / `--hue2`
- `backdrop-filter` извън design-kit
- emoji в UI
- липсващ design-kit import
- липсващ theme-toggle.js *(v1.1)*
- hardcoded `<html data-theme="dark">` *(v1.1)*

---

## 📝 ЕДНА ИСТИНА

DESIGN_LAW.md, BIBLE_v3_0_TECH.md, SESSION_*_HANDOFF.md са **обяснителни** документи. Те са референция за хора.

Файловете в `/design-kit/` са **изпълнителни**. Те са референция за машини и за нови чатове.

При конфликт между документация и `/design-kit/` — `/design-kit/` печели.

При конфликт между `/design-kit/` и нов мoдул — `/design-kit/` печели. Винаги.

---

## ✏️ КОЙ МОЖЕ ДА ПРОМЕНИ DESIGN-KIT?

**Само Тихол.** В нов чат, с изричен промт "промени design-kit за X". Не се пипа автоматично, не се "fix-ва" мимоходом, не се "усъвършенства".

Когато се промени design-kit — задължително:
1. Bump version в всеки файл (`/* v1.1 */`)
2. Update REFERENCE.html
3. Update PROMPT.md ако нещо ново е добавено
4. Notes в commit message: `DESIGN-KIT v1.1: [какво се промени]`

---

**Край на закона.**
