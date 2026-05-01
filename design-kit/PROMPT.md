# 📋 PROMPT TEMPLATE — нов модул (paste-able) · v1.1

**Цел:** да гарантира че всеки нов чат / Claude Code инстанция ще направи модул в **същата визия** като chat.php / warehouse.php / sale.php — без интерпретация, без "fix-ове", без "подобрения".

**v1.1 промени (01.05.2026):**
- Добавен `theme-toggle.js` като задължителен импорт
- Изрично правило: `<html lang="bg">` БЕЗ `data-theme="dark"` атрибут
- Bootstrap script-ът сам set-ва `data-theme="light"` ако localStorage казва така
- check-compliance.sh v1.1 проверява и тези две неща

---

## 🎯 КАК СЕ ПОЛЗВА

1. Когато започваш нов чат за нов модул (например `orders.php`, `inventory.php`, `transfers.php`) — paste-ваш текста по-долу В НАЧАЛОТО на чата.
2. Тогава задаваш конкретната задача за модула.
3. Чатът няма право да пише собствен CSS за компонент който вече съществува.
4. Накрая пускаш `bash /design-kit/check-compliance.sh module.php` — ако върне грешка, отказваш модула докато не се поправи.

---

## 🔻 ЗАЛЕПИ ВСИЧКО ОТ ТУК НАДОЛУ В НОВ ЧАТ ⬇️

```
ЗАДЪЛЖИТЕЛНО ПРЕДИ ВСЯКА РАБОТА:

1. Прочети /design-kit/README.md в repo tiholenev-tech/runmystore.
   Това е ЕДИНСТВЕНИЯТ закон за дизайн. Не интерпретираш — изпълняваш.

2. Прочети /design-kit/REFERENCE.html — това е визуалният еталон.
   Всеки компонент който видиш там — съществува и НЕ се преписва.

3. Прочети /design-kit/components.css — там са всички класове.
   Преди да напишеш един ред CSS — провери дали класът съществува.

ПРАВИЛА:

✅ ЗАДЪЛЖИТЕЛНО:
   - Импортваш точно тези 5 CSS файла в <head>, в този ред:
     /design-kit/tokens.css
     /design-kit/components-base.css
     /design-kit/components.css
     /design-kit/light-theme.css
     /design-kit/header-palette.css

   - Включваш ТОЧНО тези 2 JS файла, в този ред, преди </body>:
     <script src="/design-kit/theme-toggle.js"></script>
     <script src="/design-kit/palette.js"></script>

   - <html> tag-ът е САМО:  <html lang="bg">
     ⚠️ БЕЗ data-theme="dark" атрибут!
     Default state = няма атрибут = тъмно.
     Bootstrap-ът set-ва data-theme="light" САМО ако localStorage казва така.

   - Inline bootstrap script-а в <head> (преди другите stylesheets):
     <script>try{if(localStorage.getItem('rms_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(_){}</script>

   - Включваш partial-header.html и partial-bottom-nav.html 1:1 (PHP include).
   - Шрифт = Montserrat (от Google Fonts, weights 400-900).
   - Body има class="has-rms-shell".

⛔ ЗАБРАНЕНО:
   - Да пишеш свой .glass / .shine / .glow / .qcard / .pill / .lb-card /
     .s82-dash-* / .briefing-* / .ai-studio-row / .health / .cb-mode-toggle /
     .rms-* / .btn-iri.
   - Да пишеш :root { --hue1: ... } или style="--hue1: ...".
   - Да пишеш свой <header> или <nav>. Копираш partial-ите.
   - Да ползваш backdrop-filter / conic-gradient / mix-blend-mode извън design-kit.
   - Emoji в UI (☀ 🌙 ✨ 📷). Само SVG.
   - Шрифт различен от Montserrat.
   - Да ползваш inline <style> блок > 30 реда. Ако имаш повече — значи
     преписваш design-kit.
   - <html data-theme="dark"> — НЕ! Хардкодира тъмно завинаги, чупи toggle.

⚠️ HUE КЛАСОВЕ — само от тази таблица:
   .qd  → 255 / 222  (default индиго)
   .q1  → 0 / 15     (loss / червен)
   .q2  → 280 / 310  (cause / магия)
   .q3  → 145 / 175  (gain / зелен)
   .q4  → 38 / 28    (order / amber)
   .q5  → 200 / 225  (ocean / син)
   .q6  → 280 / 310  (AI prediction)

ПРИ КОНФЛИКТ:
   /design-kit/ файловете печелят над всякаква друга документация.
   Ако нещо не е ясно — питаш ПРЕДИ да измислиш.

ПРИ ИЗМИСЛЕН НОВ КОМПОНЕНТ:
   Ако компонентът наистина не съществува в /design-kit/components.css:
   1. ПИТАЙ Тихол преди да напишеш CSS-а.
   2. Ако одобри — пишеш с prefix .mod-[име-модул]-* (никога без prefix).
   3. Накрая Тихол решава дали да го mergne в design-kit.

ПРОВЕРКА ПРЕДИ COMMIT:
   bash /design-kit/check-compliance.sh твой-модул.php
   Ако върне грешка — модулът се отказва докато не се поправи.

ПОТВЪРДИ С "OK, спазвам design-kit v1.1" преди да започнеш работа.
```

---

## 📋 БОЙЛЕРПЛЕЙТ ЗА НАЧАЛОТО НА МОДУЛ

```php
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

<!-- DESIGN KIT — точно в този ред -->
<link rel="stylesheet" href="/design-kit/tokens.css?v=<?= @filemtime(__DIR__.'/design-kit/tokens.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components-base.css?v=<?= @filemtime(__DIR__.'/design-kit/components-base.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/components.css?v=<?= @filemtime(__DIR__.'/design-kit/components.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/light-theme.css?v=<?= @filemtime(__DIR__.'/design-kit/light-theme.css') ?: 1 ?>">
<link rel="stylesheet" href="/design-kit/header-palette.css?v=<?= @filemtime(__DIR__.'/design-kit/header-palette.css') ?: 1 ?>">
</head>
<body class="has-rms-shell">

<?php include __DIR__ . '/design-kit/partial-header.html'; ?>

<main class="content">
    <!-- ТУК и САМО ТУК пишеш съдържанието на твоя модул -->
</main>

<?php include __DIR__ . '/design-kit/partial-bottom-nav.html'; ?>

<!-- JS — задължителен ред: theme-toggle ПРЕДИ palette -->
<script src="/design-kit/theme-toggle.js?v=<?= @filemtime(__DIR__.'/design-kit/theme-toggle.js') ?: 1 ?>"></script>
<script src="/design-kit/palette.js?v=<?= @filemtime(__DIR__.'/design-kit/palette.js') ?: 1 ?>"></script>

</body>
</html>
```

---

## 🧪 ПРИМЕР — началото на orders.php в нов чат

> **Тихол paste-ва промта по-горе.**
>
> Тихол: "OK, направи orders.php — list view със карти за всяка поръчка, статус pill, бутон 'Виж'. Подобно на products.php."
>
> Чат: "OK, спазвам design-kit v1.1. Импортирам 5-те CSS файла + theme-toggle.js + palette.js. <html lang='bg'> без data-theme. Ползвам `<div class='qcard q4'>` за всяка поръчка (q4 = amber). Статусът е `<span class='pill sel'>`. Не пиша свой CSS освен `mod-orders-meta` (специфика на модула)."

Това е работа в правилния стил. **Без 20 часа спорове.**

---

## 🔄 АКТУАЛИЗАЦИЯ

Когато се промени `/design-kit/`, Тихол bump-ва версията в `README.md` и в края на този файл.

**Текуща версия:** 1.1 (01.05.2026)
**Базиран на:** v1.0 + S89 GAP REPORT fix (theme toggle gap)
