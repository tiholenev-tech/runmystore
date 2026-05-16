# 🎨 HANDOFF — WIZARD ВИЗУАЛЕН REFINEMENT
## За: чат S145 (който измисли концепцията)
## От: S146 (който не успя с дизайна)
## Дата: 15 май 2026 EOD

---

## ⚠️ ПРЕДИ ВСИЧКО

Това НЕ е нова работа. **Тих е изтощен от дизайн iterations.** Целта:
- Вземи СЪЩЕСТВУВАЩИЯ mockup
- Направи го визуално красив
- Никакви нови UX patterns
- Никакви нови flows

Ако измислиш нещо → ще е поне 5-та итерация → Тих ще се откаже.

---

## 📂 ЗАДЪЛЖИТЕЛНО ЧЕТЕНЕ (СТРОГО В ТОЗИ РЕД)

1. **`DESIGN_SYSTEM_v4.0_BICHROMATIC.md`**
   - **§5.4** (редове 720-790) → Sacred Neon Glass CSS — **КОПИРАЙ 1:1**
   - **§3.1** (редове 262-318) → Header 3 форми
   - **§3.3** (редове 449-499) → Bottom-nav session-based

2. **`WIZARD_DOBAVI_ARTIKUL_v5_SPEC.md`**
   Твоята собствена спецификация от S145. Преглеждай я само за flow корекции, НЕ за дизайн.

3. **`mockups/wizard_v6_INTERACTIVE.html`**
   БАЗА за refinement. **НЕ ПИШИ ОТ 0.** Modify съществуващия.

4. **`mockups/P15_simple_FINAL.html`** (визуален reference)
   Това е "красивото" което Тих обича. Aurora + glass + shine + glow.

5. **`products-v2.php`** редове 880-1180 (`.glass` + `.lb-card` neon CSS)
   Реална имплементация на neon pattern. Копирай от тук.

---

## 🔥 КРИТИЧНО — NEON BORDER (НАЙ-ЧЕСТО ПРОБЛЕМ)

Тих няколко пъти каза "грозно е защото нямаш бордовете".

В **dark mode** всяка карта трябва да има:
- **Iridescent рамка** — преливаща виолет/циан около карта
- **Outer glow** — мек ореол ИЗВЪН рамката

Това НЕ е обикновен `border-color`. Постига се с **`.shine` + `.glow` spans** в комбинация с `.glass` parent.

**HTML pattern за всяка карта:**
```html
<div class="glass">
  <span class="shine"></span>
  <span class="shine shine-bottom"></span>
  <span class="glow"></span>
  <span class="glow glow-bottom"></span>
  ... content ...
</div>
```

**CSS:** прочети `DESIGN_SYSTEM_v4.0_BICHROMATIC.md §5.4` редове 720-790. **Копирай дословно. Не измисляй.**

**Verifying:** в browser dev-tools на всяка `.glass` карта трябва да виждаш 4 child span-а с `conic-gradient` background. Ако ги няма → не работи.

---

## 📋 5 FLOW КОРЕКЦИИ (отделно от визуала)

### 1. NO PHOTO state
**Сегашно:** AI fallback полета (Пол/Сезон/Марка/Описание) са най-горе в Section 3.
**Правилно:** **Доставчик + цени + материя + произход + мерна единица** ГОРЕ. AI fallback полета НАЙ-ДОЛУ.
(Защото бизнес-важни полета са по-приоритетни от AI допълнителни.)

### 2. NO PHOTO + AI Markup
AI Markup row ("AI предлага €27.99 ×2.5+.99") трябва **също да работи без снимка**. Добави **малък link "⚙ Настройка"** до бутона "Приеми" — отваря settings за markup по категория.

### 3. WITH PHOTO + SINGLE — ред в Section 1
**Сегашно:** Артикулен номер + Баркод са най-долу.
**Правилно ред:**
```
Име → Цена → AI Markup → Количество → Минимум →
Категория → Артикулен номер → Баркод →
Пол → Сезон → Марка → Описание
```
Артикулният номер и баркодът са важни → ГОРЕ. AI полета са допълнителни → ДОЛУ.

### 4. WITH PHOTO + VARIATIONS — копирай от production
Цитат на Тих: "сега ще състояние нищо на варианттите. Просто беше перфектно и само искам да се смени дизайна нищо повече."

- Отвори **`products.php` редове 8997-9300** (existing wizard)
- Копирай **flow 1:1** — multi-photo cycle, AI detect, color chips, matrix
- Запази НОВИЯ дизайн (sacred neon glass), стария flow

### 5. MATRIX — задължително FULLSCREEN
Тих: "Матрицата трябва да излиза на отделен екран защото е почти невъзможно от този екран да успеем малък да натискаме адекватно цветове и размери."

Matrix НЕ се рендерира inline в акордеон. Винаги:
- Tap "Покажи matrix" → fullscreen overlay
- Header със Close + Title "Брой по комбинация"
- Sticky bottom със "Запази"
- Mockup файл: **`mockups/wizard_v6_matrix_fullscreen.html`** (S146 вече го е създал — refine визуално)

---

## 📦 DELIVERABLES

### Обнови (3 файла)
1. `mockups/wizard_v6_INTERACTIVE.html` — refining (всички 5 flow корекции + neon glass)
2. `mockups/wizard_v6_matrix_fullscreen.html` — neon glass + spacing audit
3. `mockups/wizard_v6_multi_photo_flow.html` — multi-screen filmovи кадри: capture → AI detect colors → result → matrix

### Visual acceptance criteria (Тих ще тества)
- В dark mode виждаш iridescent преливащ border на всяка карта ✓
- Има outer glow ИЗВЪН картите (не остра граница) ✓
- Aurora blobs опесни (opacity 0.4+, blur 80px+) ✓
- Cards имат breathing room (padding 18px+, gap 14px+) ✓
- Заглавия секции 17px font-weight 900 с linear-gradient text ✓
- Header е форма Б: brand + back/title + Като предния + theme (НЕ scan button) ✓

---

## ⛔ ЗАБРАНИ

1. ❌ **НЕ пиши нов CSS от 0.** Копирай от `DESIGN_SYSTEM_v4.0_BICHROMATIC.md §5.4` + `products-v2.php` редове 880-1180.
2. ❌ **НЕ сменяй 4-акордеонната структура** (СНИМКА+ОСНОВНО / Вариации / Допълнителни / AI Studio).
3. ❌ **НЕ добавяй нови UX patterns** които не са в `WIZARD_DOBAVI_ARTIKUL_v5_SPEC.md`.
4. ❌ **НЕ пипай sacred zones** (voice STT, ai-color-detect, capacitor-printer).
5. ❌ **НЕ ползвай** `rgba()` за неон — само `oklch()`.
6. ❌ **НЕ забравяй** `mix-blend-mode: plus-lighter` — без него цветовете са мътни.
7. ❌ **НЕ оставяй** `overflow: hidden` на `.glass` — убива outer glow ::after.
8. ❌ **НЕ ползвай** един `linear-gradient` за border — `conic-gradient` е задължителен (без него няма iridescent).

---

## ✅ BOOT TEST (5 въпроса преди да започнеш)

1. Кои са 3-те задължителни файла за четене? (DESIGN_SYSTEM_v4 §5.4 + wizard_v5_SPEC + wizard_v6_INTERACTIVE.html)
2. Колко span-а трябва да има всяка `.glass` карта вътре? (4: 2 shine + 2 glow)
3. Защо matrix е на отделен екран? (защото на малък екран не може да се натиска адекватно)
4. Кой production файл копирам за variations flow? (products.php редове 8997-9300)
5. Кой CSS pattern е sacred и НЕ се измисля? (.glass + .shine + .glow от §5.4)

Ако сбъркаш 2+ → прочети файловете отново. **Не започваш работа.**

---

## 🎯 КРАЙНА ЦЕЛ

Тих отваря mockup-а в browser на runmystore.ai → казва **"да, това е красиво."**

Ако каже отново "трагедия" — значи не си следвал handoff-а.

---

## 📞 КОГА ЗАВЪРШИШ

```
✅ WIZARD DESIGN COMPLETE

Обновени файлове:
- mockups/wizard_v6_INTERACTIVE.html
- mockups/wizard_v6_matrix_fullscreen.html
- mockups/wizard_v6_multi_photo_flow.html

5-те flow корекции:
✓ No photo: AI полета най-долу
✓ AI Markup link Настройка
✓ Single+photo: артикул/баркод горе
✓ Variations: 1:1 от products.php
✓ Matrix: fullscreen

Neon glass проверка:
✓ Iridescent border на всяка .glass
✓ Outer glow ::after
✓ 4 spans в всяка карта

Commit + push на main.

Тих → pull-ни + отвори в runmystore.ai
```

---

**END HANDOFF**
