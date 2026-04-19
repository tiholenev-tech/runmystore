# RunMyStore — DESIGN SYSTEM v1.0
**Цел:** Единен Neon Glass стил за ВСЕКИ модул. Claude чете този документ и прилага 1:1 без да пита.
**Последен commit:** S74.7 (април 2026)

---

## 1. МЕТА-ЗАКОН

**ВСЕКИ модул/екран/бутон в RunMyStore МОЖЕ да бъде САМО в Neon Glass стил.**
Ако пишеш нещо плоско/семпло — ГРЕШКА.

Изключения — САМО при:
- ЗАКОН №1 (Пешо не пише) → промяна е нужна
- i18n (без hardcoded БГ)
- DB schema конфликт

При конфликт: ПЪРВО питай Тихол. Никога не режи неон на своя глава.

---

## 2. ДИЗАЙН ТОКЕНИ

### Цветове
```css
--bg-main: #030712;
--hue1: 255;  /* indigo/purple */
--hue2: 222;  /* blue */
--indigo-300: #a5b4fc;
--indigo-400: #818cf8;
--indigo-500: #6366f1;
--indigo-600: #4338ca;
--purple: #8b5cf6;
--purple-bright: #a855f7;  /* за 3D underline focus */
--pink-neon: #d946ef;      /* за gradient акценти */
--success: #22c55e;
--success-soft: #16a34a;
--danger: #ef4444;
--danger-soft: #fca5a5;
--text-primary: #e2e8f0;
--text-dim: rgba(226,232,240,0.55);
--border-subtle: rgba(99,102,241,0.15);
--border-glow: rgba(139,92,246,0.5);
```

### Шрифт
```css
font-family: 'Montserrat', Inter, system-ui, sans-serif;
/* Weights used: 400, 500, 600, 700, 800, 900 */
```

### Корнери
- Малки елементи (chips, side buttons): `10-12px`
- Input контейнери: `10px`
- Карти (glass): `18-20px`
- Телефон wrapper: `38px`
- Pill (edit button, unit chip): `100px`

---

## 3. БАЗА — фон на всяка страница

```css
body {
  background:
    radial-gradient(ellipse 80% 50% at 30% 0%, hsl(var(--hue1) 70% 30% / 0.25), transparent 60%),
    radial-gradient(ellipse 70% 50% at 80% 100%, hsl(var(--hue2) 70% 28% / 0.22), transparent 60%),
    var(--bg-main);
}
```

Никога `background: #000` или `#fff`. ВИНАГИ радиални gradient-и на #030712 база.

---

## 4. КОМПОНЕНТИ

### 4.1 Modal Header (заглавия)

```css
.modal-hdr {
  padding: 14px 16px 10px;
  display: flex; align-items: center; justify-content: space-between;
  background: linear-gradient(180deg, rgba(99,102,241,0.06), transparent);
  border-bottom: 1px solid rgba(99,102,241,0.15);
  position: relative;
}
.modal-hdr::before {
  content: ''; position: absolute; top: 0; left: 20%; right: 20%; height: 1px;
  background: linear-gradient(90deg, transparent, rgba(99,102,241,0.6), transparent);
}
.hdr-title {
  font-size: 17px; font-weight: 800; letter-spacing: -0.02em;
  background: linear-gradient(135deg, #fff 0%, var(--indigo-300) 50%, var(--purple) 100%);
  -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
}
```

### 4.2 Close button (× в header)

SVG, НЕ emoji. Червен нюанс.

```html
<button class="hdr-close">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
  </svg>
</button>
```
```css
.hdr-close {
  width: 36px; height: 36px; border-radius: 12px;
  background: rgba(17,24,44,0.6);
  border: 1px solid rgba(239,68,68,0.18);
  backdrop-filter: blur(8px);
  color: #fca5a5;
  display: flex; align-items: center; justify-content: center;
}
```

### 4.3 Steps Bar (wizard прогрес)

```css
.wiz-steps { display: flex; gap: 5px; padding: 10px 16px 6px; }
.wiz-step {
  flex: 1; height: 5px; border-radius: 3px;
  background: rgba(99,102,241,0.06);
  border: 1px solid rgba(99,102,241,0.08);
  transition: all 0.3s;
}
.wiz-step.done {
  background: linear-gradient(90deg, var(--indigo-600), var(--indigo-500));
  box-shadow: 0 0 8px rgba(99,102,241,0.25);
}
.wiz-step.active {
  background: linear-gradient(90deg, var(--indigo-500), var(--purple));
  box-shadow: 0 0 14px rgba(139,92,246,0.6), inset 0 0 8px rgba(255,255,255,0.1);
  animation: stepPulse 2s infinite;
}
@keyframes stepPulse {
  0%,100% { box-shadow: 0 0 14px rgba(139,92,246,0.6), inset 0 0 8px rgba(255,255,255,0.1); }
  50%     { box-shadow: 0 0 22px rgba(139,92,246,0.9), inset 0 0 10px rgba(255,255,255,0.15); }
}
```

### 4.4 Glass Card Wrapper

Всеки съдържателен блок се загръща в `.glass` (или `.glass v4-glass-pro` за по-силен glow).

```css
.glass {
  position: relative; overflow: hidden;
  border-radius: 20px; padding: 18px 16px 16px;
  background: linear-gradient(145deg, rgba(17,24,44,0.65), rgba(10,13,30,0.85));
  border: 1px solid rgba(99,102,241,0.18);
  backdrop-filter: blur(14px);
  box-shadow: 0 8px 40px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.04);
  margin-bottom: 14px;
}
.glass::before { /* shine отгоре */
  content: ''; position: absolute; top: 0; left: 15%; right: 15%; height: 1px;
  background: linear-gradient(90deg, transparent, rgba(165,180,252,0.5), transparent);
}
.glass::after { /* shine отдолу */
  content: ''; position: absolute; bottom: 0; left: 30%; right: 30%; height: 1px;
  background: linear-gradient(90deg, transparent, rgba(139,92,246,0.3), transparent);
}
```

### 4.5 Input контейнер — 3D Inset Underline (ФИНАЛЕН S74.7)

**ТОЗИ СТИЛ СЕ ПОЛЗВА ЗА ВСЕКИ TEXT/NUMBER INPUT в wizard-ите.**

```css
#wizModal .fc, .v4-fc {
  background: linear-gradient(180deg, rgba(17,24,44,0.55), rgba(8,11,24,0.8)) !important;
  border: 1px solid rgba(99,102,241,0.2) !important;
  border-bottom: none !important;
  border-radius: 10px !important;
  box-shadow:
    inset 0 -3px 0 rgba(139,92,246,0.55),
    inset 0 -4px 10px rgba(139,92,246,0.2),
    0 3px 8px rgba(99,102,241,0.15),
    0 0 14px rgba(139,92,246,0.1) !important;
  transition: all .25s !important;
  position: relative !important;
}
#wizModal .fc:focus, .v4-fc:focus {
  border-color: rgba(139,92,246,0.4) !important;
  box-shadow:
    inset 0 -4px 0 #a855f7,
    inset 0 -6px 14px rgba(217,70,239,0.35),
    0 4px 14px rgba(139,92,246,0.4),
    0 0 24px rgba(139,92,246,0.3),
    0 0 0 1px rgba(139,92,246,0.2),
    inset 0 1px 0 rgba(255,255,255,0.05) !important;
}
```

### 4.6 Footer Buttons (Назад / Print / Запази / Напред)

**Всички са 42px височина. Ред отдолу: `<назад> <print> <запази> <напред>`**

```css
/* Назад (secondary) */
.v4-foot-back {
  flex: 1; height: 42px; border-radius: 12px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.1);
  color: #cbd5e1; font-size: 12px; font-weight: 600;
}

/* Print (icon-only) */
.v4-foot-print {
  width: 42px; height: 42px; border-radius: 12px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.1);
  color: #cbd5e1;
}

/* Запази — зелен neon glass + underline glow */
.v4-foot-save {
  flex: 1.3; height: 42px; border-radius: 12px;
  background: linear-gradient(180deg, rgba(34,197,94,0.12), rgba(22,163,74,0.05));
  border: 1px solid rgba(34,197,94,0.4);
  color: #86efac; font-size: 12px; font-weight: 700;
  letter-spacing: 0.02em; position: relative; overflow: hidden;
  box-shadow: 0 0 14px rgba(34,197,94,0.18), inset 0 1px 0 rgba(255,255,255,0.04);
}
.v4-foot-save::after {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 1.5px;
  background: linear-gradient(90deg, transparent, #22c55e, #4ade80, #22c55e, transparent);
  box-shadow: 0 0 8px rgba(34,197,94,0.6);
  opacity: 0.8;
}

/* Напред — индиго neon glass + underline glow */
.v4-foot-next {
  flex: 1.3; height: 42px; border-radius: 12px;
  background: linear-gradient(180deg, rgba(99,102,241,0.18), rgba(67,56,202,0.08));
  border: 1px solid rgba(139,92,246,0.5);
  color: #c4b5fd; font-size: 12px; font-weight: 700;
  letter-spacing: 0.02em; position: relative; overflow: hidden;
  box-shadow: 0 0 14px rgba(139,92,246,0.22), inset 0 1px 0 rgba(255,255,255,0.05);
}
.v4-foot-next::after {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 1.5px;
  background: linear-gradient(90deg, transparent, #6366f1, #8b5cf6, #6366f1, transparent);
  box-shadow: 0 0 8px rgba(139,92,246,0.6);
  opacity: 0.8;
}
```

### 4.7 Снимково каре (All-in-One Photo Zone)

Иконка + заглавие + 2 бутона (Снимай/Галерия) + 4 съвета — всичко ВЪТРЕ в едно каре с dashed border.

```css
.v4-photo-zone {
  position: relative; overflow: hidden;
  border-radius: 18px; padding: 16px 14px 12px; margin-bottom: 16px;
  background:
    radial-gradient(ellipse 80% 60% at 50% 30%, rgba(99,102,241,0.08), transparent 70%),
    linear-gradient(180deg, rgba(99,102,241,0.04), rgba(8,11,24,0.5));
  border: 1.5px dashed rgba(99,102,241,0.3);
}
```

Структура:
1. `.v4-pz-top` — иконка + title "Снимай артикула" + sub "AI анализира снимката"
2. `.v4-pz-btns` — 2 бутона side-by-side: Снимай (primary indigo glow), Галерия (secondary)
3. `.v4-pz-tips` — 4 реда ✓ съвети (зелени SVG tick-ове) в flex-wrap, разделени с dashed top border

### 4.8 Unit Chips + Edit Mode

Pill бутони с едно активно. Режим редакция → всеки показва ×, tap = изтриване.

**JS pattern за избор БЕЗ flicker:**
```js
function selectChip(btn, value) {
  S.data.field = value;
  document.querySelectorAll('.chip').forEach(c => {
    c.style.background = 'inactive...';
    c.style.border = '...';
  });
  btn.style.background = 'active gradient';
  btn.style.boxShadow = 'active glow';
  // НИКОГА renderWizard()!
}
```

**Inline input за добавяне** — видим винаги:
```html
<input placeholder="нова..." id="wNewX">
<button onclick="addFromInput()" style="border-radius:50%;background:linear-gradient(135deg,#16a34a,#15803d)">
  <svg>...✓...</svg>
</button>
```

**Edit бутон** — toggle:
```html
<button onclick="S._edit=!S._edit;renderWizard()">
  ${edit ? 'Готово' : '✎ Редакция'}
</button>
```

### 4.9 AI Hint Bar

```css
.v4-ai-hint {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 11px 13px; margin-bottom: 14px; border-radius: 14px;
  background: linear-gradient(90deg, rgba(99,102,241,0.18), rgba(59,130,246,0.06) 60%, transparent);
  border: 1px solid rgba(99,102,241,0.3);
  position: relative; overflow: hidden;
}
.v4-ai-hint-icon {  /* кръгла gradient кутия с ★ SVG */
  width: 28px; height: 28px; border-radius: 50%;
  background: linear-gradient(135deg, var(--indigo-500), var(--purple));
  box-shadow: 0 0 16px rgba(139,92,246,0.5);
}
```

### 4.10 Voice Overlay (одобрен)

- `rec-ov` — backdrop-filter: blur(8px), НЕ fullscreen black
- `rec-box` — floating bottom, border-radius: 20px, indigo glow
- Запис: червена точка pulse + "● ЗАПИСВА"
- Готово: зелена точка + "✓ ГОТОВО"
- Транскрипция в box + бутон "Изпрати →"

Прилага се в ВСЕКИ модул (products, chat, sale, onboarding).

### 4.11 Choice Cards (избор тип)

2-колона grid с glass карти. Иконка в gradient кутия.

```css
.v4-choice {
  padding: 22px 14px; border-radius: 18px; text-align: center; cursor: pointer;
  background: linear-gradient(145deg, rgba(17,24,44,0.7), rgba(10,13,30,0.9));
  border: 1.5px solid rgba(99,102,241,0.15);
  backdrop-filter: blur(12px);
  position: relative; overflow: hidden;
}
.v4-choice.selected {
  border-color: rgba(139,92,246,0.7);
  background: linear-gradient(145deg, rgba(99,102,241,0.15), rgba(139,92,246,0.08));
  box-shadow:
    0 0 0 1px rgba(139,92,246,0.4),
    0 8px 30px rgba(99,102,241,0.25),
    inset 0 1px 0 rgba(255,255,255,0.06);
}
.v4-choice-icon {
  width: 54px; height: 54px; border-radius: 16px;
  background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(139,92,246,0.1));
  border: 1px solid rgba(99,102,241,0.3);
  /* selected варианта: solid gradient indigo→purple + box-shadow glow */
}
```

### 4.12 Divider (Пожелателно / секция разделител)

```html
<div class="v4-divider"><span>Пожелателно</span></div>
```
```css
.v4-divider { display: flex; align-items: center; gap: 10px; margin: 18px 0 14px; }
.v4-divider::before, .v4-divider::after {
  content: ''; flex: 1; height: 1px;
  background: linear-gradient(90deg, transparent, rgba(99,102,241,0.25), transparent);
}
.v4-divider span {
  font-size: 9px; font-weight: 600; color: rgba(255,255,255,0.35);
  letter-spacing: 0.15em; text-transform: uppercase;
}
```

### 4.13 Side Button (до input — mic/scan)

```css
.v4-side-btn {  /* червен (mic) */
  width: 44px; flex-shrink: 0; border-radius: 12px;
  background: linear-gradient(145deg, rgba(239,68,68,0.1), rgba(239,68,68,0.04));
  border: 1.5px solid rgba(239,68,68,0.25);
  color: #fca5a5;
}
.v4-side-btn.scan {  /* зелен (scan) */
  background: linear-gradient(145deg, rgba(34,197,94,0.12), rgba(34,197,94,0.04));
  border-color: rgba(34,197,94,0.3); color: #86efac;
}
```

---

### 4.14 Required Toggle Pattern (S75.2 — ЕТАЛОН)

**Когато има избор от 2 опции който е ЗАДЪЛЖИТЕЛЕН** (напр. Единичен/С варианти, Дребно/Едро) — използвай този pattern.

**Визуално поведение:**
- При null (нищо избрано) → toggle пулсира жълто (warn) + над него червена буква "▲ Избери първо..."
- При опит за focus на друг input преди избор → блокира се, toast + toggle пулсира **силно червено 3 пъти** + вибрация
- При избор → pulse изчезва плавно, warn label премахва

**HTML:**
```html
<div class="v4-tt-warn">▲ Избери първо тип на артикула</div>
<div class="v4-type-toggle needs-select">
  <button class="v4-tt-opt active" onclick="wizSwitchType('single')">
    <svg>...</svg><span>Единичен</span>
  </button>
  <button class="v4-tt-opt" onclick="wizSwitchType('variant')">
    <svg>...</svg><span>С варианти</span>
  </button>
</div>
```

**CSS:**
```css
.v4-type-toggle {
  display: grid; grid-template-columns: 1fr 1fr; gap: 6px;
  padding: 4px; margin-bottom: 14px; border-radius: 14px;
  background: linear-gradient(145deg, rgba(17,24,44,0.65), rgba(10,13,30,0.85));
  border: 1px solid rgba(99,102,241,0.18);
  backdrop-filter: blur(14px);
  box-shadow: 0 4px 20px rgba(0,0,0,0.3), inset 0 1px 0 rgba(255,255,255,0.04);
  position: relative; overflow: hidden;
}
.v4-type-toggle::before {
  content: ''; position: absolute; top: 0; left: 20%; right: 20%; height: 1px;
  background: linear-gradient(90deg, transparent, rgba(165,180,252,0.5), transparent);
}
.v4-tt-opt {
  display: flex; align-items: center; justify-content: center; gap: 7px;
  height: 40px; border-radius: 10px; cursor: pointer;
  background: transparent; border: 1px solid transparent;
  color: rgba(255,255,255,0.55);
  font-family: inherit; font-size: 12px; font-weight: 600;
  letter-spacing: 0.01em; transition: all 0.25s;
  position: relative;
}
.v4-tt-opt.active {
  background: linear-gradient(180deg, rgba(99,102,241,0.25), rgba(67,56,202,0.12));
  border-color: rgba(139,92,246,0.5); color: #fff;
  box-shadow: 0 0 14px rgba(139,92,246,0.3), inset 0 1px 0 rgba(255,255,255,0.08);
}
.v4-tt-opt.active::after {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 1.5px;
  background: linear-gradient(90deg, transparent, #6366f1, #8b5cf6, #6366f1, transparent);
  box-shadow: 0 0 8px rgba(139,92,246,0.6);
}

/* ЖЪЛТ pulse — когато нищо не е избрано (мек, непрекъснат) */
.v4-type-toggle.needs-select {
  border-color: rgba(234,179,8,0.55);
  animation: ttPulseRequired 1.3s ease-in-out infinite;
}
@keyframes ttPulseRequired {
  0%,100% { box-shadow: 0 0 0 0 rgba(234,179,8,0.2), 0 4px 20px rgba(0,0,0,0.3); }
  50%     { box-shadow: 0 0 28px 6px rgba(234,179,8,0.35), 0 4px 20px rgba(0,0,0,0.3); }
}

/* ЧЕРВЕН силен pulse — при опит да заобиколи избора (3 пъти) */
.v4-type-toggle.pulsing-strong {
  animation: ttPulseStrong 0.5s ease-in-out 3;
}
@keyframes ttPulseStrong {
  0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,0.3), 0 4px 20px rgba(0,0,0,0.3); border-color: rgba(239,68,68,0.8); }
  50%     { box-shadow: 0 0 36px 10px rgba(239,68,68,0.55), 0 4px 20px rgba(0,0,0,0.3); border-color: #ef4444; }
}

/* Warn label — жълт, пулсиращ текст */
.v4-tt-warn {
  font-size: 10px; font-weight: 700; color: #fbbf24;
  text-align: center; margin-bottom: 6px; letter-spacing: 0.05em;
  text-transform: uppercase;
  text-shadow: 0 0 8px rgba(234,179,8,0.5);
  animation: warnBlink 1.3s ease-in-out infinite;
}
@keyframes warnBlink { 0%,100% { opacity: 1; } 50% { opacity: 0.55; } }
```

**JS Guard (блокира input когато изборът е незаписан):**
```js
function typeGuard(e) {
  if (S.chosenType) return;
  e.preventDefault();
  if (e.target && e.target.blur) e.target.blur();
  showToast('Избери първо...', 'error');
  var tg = document.querySelector('.v4-type-toggle');
  if (tg) {
    tg.classList.add('pulsing-strong');
    setTimeout(() => tg.classList.remove('pulsing-strong'), 1600);
  }
  if (navigator.vibrate) navigator.vibrate([50, 30, 50]);
}

// Attach при render:
document.querySelectorAll('input, select').forEach(el => {
  el.removeEventListener('focus', typeGuard);
  el.addEventListener('focus', typeGuard);
});

// И в Save функцията:
function save() {
  if (!S.chosenType) {
    showToast('Избери първо...', 'error');
    // pulse strong + vibrate ... (същото като guard)
    return;
  }
  // ... save logic
}
```

**Прилага се за:** Единичен/Варианти, Дребно/Едро превключвач, и всеки друг required binary избор преди форма.

### 4.15 Autofill Fix (Chrome/Safari побеляване)

**Проблем:** Chrome/Safari беят autofilled input-и със светъл фон — разрушава неон темата.

**Fix (задължителен за ВСЕКИ модул с input-и):**
```css
#[modalId] input:-webkit-autofill,
#[modalId] input:-webkit-autofill:hover,
#[modalId] input:-webkit-autofill:focus,
#[modalId] input:-webkit-autofill:active {
  -webkit-box-shadow: 0 0 0 30px rgba(17,24,44,0.9) inset !important;
  -webkit-text-fill-color: #fff !important;
  caret-color: #fff !important;
  transition: background-color 9999s ease-out 0s;
}
```

Замени `[modalId]` с ID на модала (wizModal, saleModal, и т.н.) или изпусни selector-а за global fix.


---

## 5. ЗАБРАНИ

| НЕ | ВМЕСТО |
|---|---|
| emoji в UI (✕ ✓ ▲ ✎ 🗑 🎤) | SVG stroke icons, 1.5-2px stroke |
| "Gemini" в UI | "AI" |
| Flat #000 / #fff фонове | Radial gradients на #030712 |
| Native клавиатура в sale/products | Custom numpad + voice (ЗАКОН №1) |
| `renderWizard()` при tap на chip | Update на стилове директно (без flicker) |
| Hardcoded БГ текст | `tenant.lang` i18n |
| Hardcoded "лв"/"BGN"/"€" | `priceFormat($amount, $tenant)` |
| Плоски solid бутони без glow | Gradient + box-shadow + underline glow |

---

## 6. JS PATTERNS

### 6.1 Chip tap без flicker
```js
// DA:
function selectChip(btn, val) {
  S.data.x = val;
  document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
  btn.classList.add('active');
}

// NE:
function selectChip(val) {
  S.data.x = val;
  renderPage(); // FLICKER!
}
```

### 6.2 Edit mode toggle
```js
S._editX = !S._editX;
renderPage(); // тук е OK защото edit mode преглежда цялата секция
```

### 6.3 Confirm преди destructive
```js
if (!confirm('Изтрий "' + name + '"?')) return;
```

---

## 7. РЕФЕРЕНТНИ ФАЙЛОВЕ

За визуална справка:
- `home-neon.html` — главен екран
- `warehouse.php` — склад
- `sale.php` — продажба (camera-header, numpad)
- `products.php` wizard Стъпка 3 (S74.7) — ЕТАЛОН за input + footer

---

## 8. ВЕРСИИ

| Версия | Дата | Промени |
|---|---|---|
| v1.0 | 2026-04-19 | Инициализация след S74.7 — 3D inset underline, all-in-one photo zone, unit edit mode, footer 42px neon |
| v1.1 | 2026-04-19 | S75.2 — Required Toggle pattern (жълт pulse + червен strong pulse + warn label + input guard), autofill побеляване fix |

---

**ПРАВИЛО:** При всяко добавяне на нов компонент в проекта — обновявай този документ. Всеки следващ чат ще го чете и прилага без да пита Тихол.
