# S82.UI — Личен протокол (Claude Code, само-дисциплина)

**Цел на сесията:** Унификация на дизайна (тема, safe-area, print иконка, токени).
**Цел на този файл:** Гарантира 100% спазване — НИЩО логическо не се пипа.

---

## ЗЛАТНО ПРАВИЛО

> Промяна е разрешена САМО ако след `git diff` виждам единствено:
> `<style>` content, нов `<link>`, нов `<button>` (с onclick към СЪЩЕСТВУВАЩА функция или празен fallback toast), нов `<meta viewport>`, или ново CSS variable.
> Всичко друго = REVERT.

---

## ПРЕДИ ВСЯКА РЕДАКЦИЯ (mental checklist)

1. **Read първо** — задължително прочитам блока (Read tool, не cat).
2. **Контекст?** — блокът e ли в `<style>`, `<script>` или PHP? Само `<style>` и `<head>` са fair game. `<script>` се пипа САМО за `toggleTheme()` (нова функция, добавена накрая на script блок). PHP `<?php ... ?>` блокове = НЕ пипаш.
3. **Edit с anchor** — никакъв `sed -i`, никакъв blind regex. Edit tool със `old_string`, който има минимум 2 уникални реда контекст.
4. **php -l** след всеки PHP файл — задължително.
5. **git diff** ръчен преглед — ако видя `function`, `if`, `foreach`, `INSERT/UPDATE/SELECT`, `addEventListener`, `onclick=` (което променя СЪЩЕСТВУВАЩ handler, не нов бутон), `fetch(`, `api(`, `XMLHttpRequest` → `git checkout -- <file>` и започвам отново.
6. **Commit** — 1 файл = 1 commit. Message започва със `S82.UI:`.

---

## РАЗРЕШЕНИ ОПЕРАЦИИ

| ✅ ОК | ❌ НЕ |
|------|------|
| Add `<link rel="stylesheet" href="/css/theme.css">` в `<head>` | Промяна на съществуващи `<link>` |
| Add `viewport-fit=cover` в meta viewport | Премахване на други meta тагове |
| Add `padding: max(X, calc(env(safe-area-inset-top,0px)+X))` на хедър | Промяна на header HTML структура |
| Add нов `<button id="themeToggle">` в хедър (САМО ако модулът няма) | Премахване на съществуващи бутони |
| Add нов `<button id="printStatusBtn">` в products.php хедър | Промяна на onclick на съществуващи бутони |
| Add нова JS функция `toggleTheme()` (точно копие от chat.php) | Промяна на съществуващи JS функции |
| Add нови CSS classes (`.theme-toggle`, `.print-status-btn`) | Преименуване на съществуващи class имена |
| Замяна на hex цвят `#030712` → `var(--bg-main)` САМО в `<style>` блок | Замяна на hex в JS string или PHP echo |

---

## STOP-АНД-REVERT СИГНАЛИ

При `git diff` ако видя:

- 🛑 Промяна в име на PHP функция, променлива, class, ID
- 🛑 Преместен код от един `<script>` в друг
- 🛑 Премахнат event handler
- 🛑 Променен AJAX URL или fetch() body
- 🛑 Преименувана DB колона в SQL
- 🛑 Hex цвят, заменен с `var(--*)` в `<script>` контекст (счупва JS)
- 🛑 Промяна в условия `if`/`switch`/`?:`

→ **`git checkout -- <file>`** + rethink.

---

## ОБХВАТ НА СЕСИЯТА

**Файлове, които ще бъдат пипнати (само CSS/HTML):**
- `css/theme.css` — НОВО
- `chat.php` — добавя link + safe-area (тоglе бутон вече съществува)
- `products.php` — link + safe-area + print btn + theme toggle btn
- `sale.php` — link + safe-area + theme toggle btn
- `inventory.php` — link + safe-area + theme toggle btn
- `warehouse.php` — link + safe-area + theme toggle btn
- `stats.php` — link + safe-area + theme toggle btn
- `settings.html` — НОВО (минимален stub с тема)
- `finance.html` — НОВО (минимален stub с тема)

**Файлове ЗАБРАНЕНИ:**
- ВСЕКИ `*-action.php`, `*-send.php`, `*-api.php`
- `compute-*.php`, `build-prompt.php`, `ai-*.php`
- `chat-send.php`, `auth-*.php`
- DB migration scripts, `config/*`
- Wizard логика в products.php (`renderWizPage`, `wizGo`, `wizSave`, `wizCollectData`, `wizBuildCombinations`, `S.wiz*`)

---

## COMMIT PATTERN

Един commit на файл:
- `S82.UI: theme.css tokens (dark + light)`
- `S82.UI: chat.php — viewport-fit + safe-area`
- `S82.UI: products.php — theme link + safe-area + print btn`
- `S82.UI: sale.php — theme link + toggle`
- `S82.UI: inventory.php — theme link + toggle`
- `S82.UI: warehouse.php — theme link + toggle`
- `S82.UI: stats.php — theme link + toggle`
- `S82.UI: settings.html / finance.html stubs`

Финален tag: `v0.7.1-s82-ui-unified`.
