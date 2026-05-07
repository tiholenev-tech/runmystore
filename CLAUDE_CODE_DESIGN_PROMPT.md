# 🎨 PROMPT — Дизайн в RunMyStore.AI

> Поставяй този промпт ВИНАГИ когато даваш дизайн задача на Claude Code.
> Иначе Claude ще импровизира → Frankenstein.

---

## ЗАДЪЛЖИТЕЛНО ПРЕДИ ВСЯКА ДИЗАЙН ПРОМЯНА

```
Прочети ЦЕЛИЯ DESIGN_SYSTEM.md (= DESIGN_SYSTEM_v4.0_BICHROMATIC.md)
от /var/www/runmystore/DESIGN_SYSTEM.md ПРЕДИ да напишеш или промениш ред CSS/HTML.

Това е ЕДИНСТВЕНИЯТ source of truth за дизайн.
Стари файлове в docs/archived/ са НЕВАЛИДНИ — НЕ ги цитирай.

ЗАДЪЛЖИТЕЛНИ 7 ПРАВИЛА:

1. LIGHT = default. DARK = option (toggle от header).
   Никога не пиши код който работи само в един режим.
   Всеки нов компонент има [data-theme="light"] И [data-theme="dark"] правила.

2. SACRED — НЕ ПИПАЙ:
   - .glass + .shine + .glow с conic-gradient mask-composite
   - rmsToggleTheme() — само setAttribute('data-theme', X), НИКОГА removeAttribute
   - partials/header.php — 7 елемента в точен ред
   - partials/bottom-nav.php — 4 tabs (AI/Склад/Справки/Продажба)

3. ВСИЧКО през CSS variables:
   - Цветове: var(--accent), var(--text), var(--text-muted), var(--qN-*)
   - Радиуси: var(--radius), var(--radius-sm), var(--radius-pill), var(--radius-icon)
   - Сенки: var(--shadow-card), var(--shadow-card-sm), var(--shadow-pressed)
   - Фонт: var(--font) (Montserrat), var(--font-mono) (DM Mono)
   - Easing: var(--ease), var(--ease-spring)
   ZABRANENI: hex директно, hardcoded radius, Inter/Roboto/Arial.

4. CONTINUITY — двата режима споделят:
   ✓ Радиуси: 22px / 14px / 999px / 50%
   ✓ Layout: 1.4fr/1fr top-row, 2x2 ops
   ✓ Font: Montserrat + DM Mono
   ✓ 9 effects (aurora, shine, glow, conic ring, orb, glow ring, shimmer CTA, halo, AI brain)

5. ВКЛЮЧИ Google Fonts при всеки нов модул (иначе browser default = грозно):
   <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">

6. AURORA blobs ВИНАГИ след header.php include:
   <div class="aurora">
     <div class="aurora-blob"></div>
     <div class="aurora-blob"></div>
     <div class="aurora-blob"></div>
   </div>

7. НЕ слагай transition на body (премигва).
   Transitions само на специфични properties на компоненти.

ПРЕДИ COMMIT:
- Запази backup: cp file.php file.php.bak.S<XX>_<TIMESTAMP>
- Run: php -l file.php (PHP lint)
- Тествай LIGHT режим
- Тествай DARK режим (тапни sun/moon icon)
- Покажи на Tihol screenshot преди git push

REFERENCE FILE (eталон): /var/www/runmystore/life-board.php
Ако нещо в задачата ти не е ясно — провери как е направено в life-board.php.
```

---

## КРАТКА ВЕРСИЯ (за бързи задачи)

```
Прочети /var/www/runmystore/DESIGN_SYSTEM.md преди да пишеш CSS.
Light = default, Dark = option. Всичко през CSS variables, никакви hardcoded цветове.
SACRED .glass/.shine/.glow и rmsToggleTheme() — НЕ пипай.
Reference: life-board.php.
```

---

## КОГА ДА ПОЛЗВАШ КОЙ

| Сценарий | Версия |
|----------|--------|
| Нов модул от нула | Пълен промпт |
| Корекция на съществуващ файл | Пълен промпт |
| Bug fix без visual промяна | Кратка |
| Просто питане (без code) | Не нужен |

---

**Дата:** 2026-05-07
**Версия на Bible:** v4.1 BICHROMATIC
