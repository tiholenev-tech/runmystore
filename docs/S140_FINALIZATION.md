# S140 FINALIZATION — край на сесия 11.05.2026

**Status:** ✅ chat.php + life-board.php redesign DEPLOYED (production)
**Backup tags:** `pre-overlay-S140` (1cc0603), `pre-swap-S140` (28157bd)
**Key commits:** c3c28c1 (SWAP), 237682b (overlay), 45105b1 (signals catalog)

---

## 1. WORKFLOW PATTERNS (proven over 50+ commits today)

### 1.1 Sandbox → Push → Pull pattern (без интерактивна работа за Тих)

Стандартният поток днес:

```
Sandbox (/home/claude/runmystore)
    ↓ Claude пише локално, тества, прави diff
    ↓ git pull --rebase + git add + git commit + git push
GitHub (main branch)
    ↓ Тих копи-пейст-ва ЕДНА команда в droplet конзолата:
    ↓ cd /var/www/runmystore && git pull origin main
    ↓ (при конфликти: git reset --hard HEAD && git pull origin main)
Production (DigitalOcean droplet)
    ↓ Тих refresh-ва браузъра
    ↓ Дава feedback (screenshot / описание на проблем)
```

**Защо работи:**
- Тих НЕ е developer — той не пише код. Само paste-ва команди.
- Claude вижда целия repo в sandbox-а — може да чете всеки файл.
- GitHub PAT token enabled — Claude push-ва директно без credentials prompt.
- Backup tags преди големи промени (SWAP, overlay) — гарантирано revert.

**Команда за revert (емergencies):**
```bash
cd /var/www/runmystore && git reset --hard pre-swap-S140 && git push origin main --force
```

### 1.2 GitHub PAT setup (one-time)

```bash
cd /var/www/runmystore
git remote set-url origin https://tiholenev-tech:<PAT>@github.com/tiholenev-tech/runmystore.git
```

PAT scope: fine-grained, `runmystore` repo only, Contents: Read+Write, 30 дни.
След това всички `git push` от droplet работят без prompt.

### 1.3 Паралелна работа Claude (Opus) + Claude Code

**Opus тук** (chat.anthropic.com):
- Малки/средни fix-ове (CSS overrides, PHP query промени, JS handlers)
- Логически решения (UX, текстове, фундаментални въпроси)
- Visual работа (mockup integration, color fixes)
- Прави backup tag-овe преди големи неща

**Claude Code (на droplet, tmux session):**
- Големи systematic задачи (1000+ реда четене, 200+ реда писане)
- Изисква интернет access за git, npm и т.н.
- Tихол стартира с `tmux new -s code-XXX` → `cd /var/www/runmystore` → `claude`
- При `Please run /login` → въвежда `/login` в Code prompt-а

**Координация:**
- Opus пише backup tag `pre-<feature>-S140` ПРЕДИ да даде Code задача
- Code commit-ва сам, push-ва сам — Opus pull-ва когато Code приключи
- При паралелен edit: `git pull --rebase` обикновено разрешава автоматично (различни секции на файла)
- **Изрично правило:** Code не пипа header/subbar/main контент в новите файлове (преди </main>) — само неговата зона

### 1.4 Comm patterns

- **Само БГ**, кратки отговори (2-3 изречения)
- При visual проблем → веднага fix без излишно питане
- При логическо/UX → питай първо
- 60% позитив + 40% честна критика
- Тих ползва voice-to-text → fragmented изречения, типографски грешки, CAPS = urgency
- BAD: "Готов ли си?", "ОК?", "Започвам?"
- GOOD: "Push мина. На droplet-а: `git pull origin main`."

---

## 2. UNIVERSAL UI LAWS (immutable за всички модули)

Тези правила MUST се прилагат в **ВСЕКИ** бъдещ модул редизайн (products.php,
sale.php, warehouse.php, deliveries.php, orders.php, transfers.php, и т.н.):

### 2.1 Header (Тип А — начални страници: chat.php + life-board.php)

```html
<header class="rms-header">
  <a class="rms-brand"><span class="brand-1">RunMyStore</span><span class="brand-2">.ai</span></a>
  <span class="rms-plan-badge">PRO</span>
  <div class="rms-header-spacer"></div>
  <a class="rms-icon-btn" aria-label="Принтер" href="printer-setup.php"><svg .../></a>
  <a class="rms-icon-btn" aria-label="Настройки" href="settings.php"><svg .../></a>
  <button class="rms-icon-btn" aria-label="Изход" onclick="if(confirm('Изход?'))location.href='logout.php'"><svg .../></button>
  <button class="rms-icon-btn" aria-label="Светла/тъмна тема" onclick="rmsToggleTheme()"><svg .../></button>
</header>
```

**CSS override (задължителен в S140 OVERRIDES блока на всеки файл):**
```css
.rms-header .rms-icon-btn { width: 22px; height: 22px; padding: 0; }
.rms-header .rms-icon-btn svg { width: 11px; height: 11px; }
.rms-header .rms-header-icons { gap: 4px; }
```

**Brand стилове:**
```css
.rms-brand {
    position: relative; font-size: 17px; letter-spacing: -0.01em;
    display: inline-flex; align-items: baseline; gap: 0;
    filter: drop-shadow(0 0 12px hsl(var(--hue1) 70% 50% / 0.4));
}
.rms-brand .brand-1 {
    font-weight: 900;
    background: linear-gradient(90deg, hsl(var(--hue1) 80% 60%), hsl(var(--hue2) 80% 60%), hsl(var(--hue3) 70% 55%), hsl(var(--hue2) 80% 60%), hsl(var(--hue1) 80% 60%));
    background-size: 200% auto;
    animation: rmsBrandShimmer 4s linear infinite;
    -webkit-background-clip: text; background-clip: text;
    -webkit-text-fill-color: transparent;
}
.rms-brand .brand-2 {
    font-weight: 400; font-size: 14px;
    color: var(--text-muted); margin-left: 1px; opacity: 0.85;
}
@keyframes rmsBrandShimmer { 0% { background-position: 0% center; } 100% { background-position: 200% center; } }
```

**🐛 BUG:** shimmer работи в chat.php (P11), не работи в life-board.php (P10) дори след override-и. Causa неизвестна. **Запазено за бъдеща сесия.** Виж docs/KNOWN_BUGS.md.

### 2.2 Header (Тип Б — вътрешни модули)

Същия HTML + CSS, **но** без mode toggle (защото вече сме в module-а),
plus вътрешен модулен бутон отдясно (Кошница, Филтри, Камера и т.н.).

### 2.3 Subbar (sticky, под header-а)

```html
<div class="rms-subbar">
  <!-- Store selector (само ако има >1 магазин) -->
  <select class="rms-store-toggle" onchange="location.href='?store='+this.value">
    <option value="N" selected>Име на магазин</option>
    ...
  </select>
  <span class="subbar-where">[name на текущата страница: НАЧАЛО / СКЛАД / ПРОДАЖБА / ...]</span>
  <a class="lb-mode-toggle" href="[life-board.php или chat.php]" title="[Лесен/Разширен] режим">
    <svg ...><polyline points="9 18 15 12 9 6"/></svg>
    <span>[Лесен или Разширен]</span>
  </a>
</div>
```

**CSS override (винаги):**
```css
.rms-subbar {
  position: sticky; top: 56px; z-index: 49;
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px; max-width: 480px; margin: 0 auto;
}
.subbar-where {
  flex: 1; text-align: center;
  font-family: var(--font-mono); font-size: 10px; font-weight: 700;
  letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-muted);
}
```

### 2.4 Bottom-nav (4 orbs — само за chat.php = Тип А)

```html
<nav class="rms-bottom-nav">
  <a href="chat.php" class="rms-nav-tab active"><span class="nav-orb"><svg .../></span><span>AI</span></a>
  <a href="warehouse.php" class="rms-nav-tab"><span class="nav-orb"><svg .../></span><span>Склад</span></a>
  <a href="stats.php" class="rms-nav-tab"><span class="nav-orb"><svg .../></span><span>Справки</span></a>
  <a href="sale.php" class="rms-nav-tab"><span class="nav-orb"><svg .../></span><span>Продажба</span></a>
</nav>
```

**ВАЖНО:** Labels са `AI / Склад / Справки / Продажба` (НЕ "Статистики/Продажби"
— това е грешка от макета). При seller роля → `display: none`.

### 2.5 Chat input bar (sticky отдолу — Тип А)

```html
<div class="chat-input-bar" onclick="openChat(event)" role="button" tabindex="0">
  <span class="chat-input-icon"><svg ... waveform icon /></span>
  <span class="chat-input-text">Кажи или напиши...</span>
  <button class="chat-mic" onclick="event.stopPropagation();toggleVoice()"><svg .../></button>
  <button class="chat-send" onclick="event.stopPropagation();sendMsg()"><svg .../></button>
</div>
```

**Анимации (задължителни):**
- `chat-mic::before, ::after` — pulsing rings (chatMicRing 2s ease-out infinite)
- `chat-send svg` — leky drift (chatSendDrift 1.8s ease-in-out infinite)

### 2.6 Global haptic feedback (DOMContentLoaded handler)

```javascript
document.addEventListener('DOMContentLoaded', function(){
    const tappables = '.rms-icon-btn, .rms-store-toggle, .lb-mode-toggle, .op-btn, .op-info-btn, .lb-action, .lb-fb-btn, .lb-expand-btn, .help-chip, .s82-dash-pill, .wfc-tab, .fp-pill, .studio-btn, .see-more-mini, .chat-mic, .chat-send, .lb-collapsed, .rms-nav-tab';
    document.querySelectorAll(tappables).forEach(el => {
        el.addEventListener('click', () => {
            if (navigator.vibrate) navigator.vibrate(6);
        }, { passive: true });
    });
});
```

### 2.7 SACRED rules (никога не нарушавай)

- **НИКОГА** hardcoded БГ текст в код (трябва через `$T` array или `tenant.lang`)
- **НИКОГА** hardcoded `BGN/лв/€` — винаги `priceFormat($amount, $tenant)`
- **НИКОГА** `ADD COLUMN IF NOT EXISTS` (MySQL не поддържа)
- **НИКОГА** `sed` за file edits — само Python scripts
- **НИКОГА** emoji в UI — само SVG
- **НИКОГА** native клавиатура (custom numpad in sale.php)
- **НИКОГА** `<?= htmlspecialchars($T['...'] ?? '') ?>` без БГ fallback (води до видими `{T_*}` placeholder-и)

---

## 3. ARCHITECTURE (S140 нови pattern-и)

### 3.1 v2generateBody() — единен body генератор за всички AI сигнали

Файл: `chat.php` + `life-board.php` (DRY violation — TODO рефакторинг към `config/insights-body.php`)

3-tier routing:
1. `v2BodyByTopic($prefix, $ins, $data)` — topic-specific (17 implemented: zero_stock_with_sales, zombie_45d, highest_margin, и т.н.)
2. `v2BodyByCategory($cat, $ins, $data)` — generic (67 categories)
3. `v2BodyByFQ($fq, $ins)` — fundamental_question fallback (loss/gain/order/...)
4. Final fallback: title + product_count

Helpers: `v2TopicPrefix`, `v2Plural`, `v2Money`, `v2Pct`, `v2Num`.

CLI tester: `php chat.php 7` (tenant_id=7). AJAX: `?ajax=body&insight_id=N`.

### 3.2 75vh chat overlay (S140.OVERLAY)

Един и същ блок в **chat.php** и **life-board.php** (огледално, ~570 реда):
- HTML: `.chat-overlay-bg`, `.chat-overlay-panel`, header (back/close), messages, rec-bar, input area
- CSS: isolated `.chat-overlay-*` namespace (port от design-kit без модификации)
- JS: `openChat`, `closeChat`, `openChatQ(title)`, `sendMsg`, `addUserBubble`, `addAIBubble`, `toggleVoice`, `stopVoice`
- AJAX endpoint: `chat-send.php` (същият като преди swap)
- popstate handler за browser back button

### 3.3 SWAP procedure

```bash
# 1. Backup tag
git tag pre-swap-S140
git push origin pre-swap-S140

# 2. Rename files
git mv chat.php chat.php.bak.S140
git mv chat-v2.php chat.php
git mv life-board.php life-board.php.bak.S140
git mv life-board-v2.php life-board.php

# 3. Commit + push
git commit -m "SXXX SWAP: v2 → production"
git push origin main

# 4. На droplet:
cd /var/www/runmystore && git pull origin main
```

**Revert pattern:**
```bash
git reset --hard pre-swap-S140 && git push origin main --force
```

---

## 4. PRODUCTS.PHP PLAYBOOK (за S141)

`products.php` е **10× по-голям** от chat.php (15 000+ реда). Затова **НЕ rewrite от scratch**.

### 4.1 Стратегия "Inject-only"

1. **Запази production products.php непокътнат** (никога не пиши върху него)
2. **Добави CSS overrides в S141 OVERRIDES блок** (последния `<style>` блок преди `</style>`)
3. **Малки HTML промени** — само класове на wrapper-и, нови section маркери
4. **Логиката НЕ се пипа** (PHP queries, JS handlers, AJAX endpoints — всичко стои)
5. **Тества се блок по блок** — header, search, list, add wizard, edit, и т.н.

### 4.2 Преди start

1. **Прочети двата документа за products:**
   - `docs/PRODUCTS_DESIGN_LOGIC.md`
   - (втория документ който Тих ще предостави)
2. **Backup tag:** `git tag pre-products-redesign-S141`
3. **Inventory check:** `grep -nE "^function|fetch\('|window\.[a-z]" products.php | wc -l` (трябва да си запознат с количеството функции)
4. **Apply Universal UI Laws (§2)** — header/subbar/bottom-nav трябва да са консистентни с chat.php и life-board.php

### 4.3 Module-specific elements

products.php е **Тип Б** (вътрешен модул):
- Header има допълнителен бутон отдясно: **Камера** (за scan на код)
- Subbar `subbar-where` = `СКЛАД`
- Mode toggle → `chat.php` (Разширен) или скрит за seller
- NO bottom-nav (или с display:none за seller) — освен ако chat.php стилът да се ползва

### 4.4 6-те фундаментални въпроса (cards секция)

Products.php има **6 q-секции** (q1-q6 цветове):
1. q1 КАКВО ГУБЯ (червен) — мъртви артикули, негативни маржове
2. q2 ОТ КАКВО ГУБЯ (виолет) — лоши доставчици, грешни цени
3. q3 КАКВО ПЕЧЕЛЯ (зелен) — топ продавачи, high-margin
4. q4 ОТ КАКВО ПЕЧЕЛЯ (тюркоаз) — успешни марки/категории
5. q5 ПОРЪЧАЙ (амбър) — изпразнени bestsellers
6. q6 НЕ ПОРЪЧВАЙ (сив) — overstocked / dead inventory

Loss > Gain priority. Anti-Order > Order. **`ai_insights.fundamental_question` ENUM е source of truth.**

---

## 5. KNOWN BUGS (S140 unsolved)

### 5.1 Brand shimmer не работи в life-board.php

**Symptom:** `.rms-brand .brand-1` има animation `rmsBrandShimmer 4s linear infinite` + valid gradient + `background-size: 200% auto`. В chat.php работи. В life-board.php не работи (статичен gradient без вълна).

**Опитани решения:**
- Override на `.rms-brand` parent с `background: none !important; -webkit-text-fill-color: initial !important; animation: none !important`
- Override на `.brand-1` с `!important` за gradient + animation
- Hard refresh (Ctrl+Shift+R)
- Incognito mode

**Suspected causes:**
- CSS specificity conflict с P10 inline `.rms-brand` (има own background + clip)
- Browser cache на Capacitor APK
- Inherited `-webkit-text-fill-color` from parent overrides animated child

**За следваща сесия:** open DevTools на life-board.php, inspect `.brand-1` computed styles → виж кое правило побеждава и защо.

### 5.2 Feedback бутони (👍👎❓) — визуално works, не записват в DB

Card expanded view има 3 feedback бутона. Click сменя `selected` класа визуално, но няма AJAX save → AI brain няма обратна връзка.

**За следваща сесия:** създай `insights-feedback.php` endpoint + AJAX call в `v2lbFb()` JS функцията. Schema: `ai_insight_feedback(id, tenant_id, insight_id, user_id, kind ENUM('up','down','hmm'), created_at)`.

---

## 6. FILE TOPOLOGY (след S140 SWAP)

```
/var/www/runmystore/
├── chat.php                  ← НОВ дизайн (бивш chat-v2.php) — 165K, 2200+ lines
├── chat.php.bak.S140         ← Стар дизайн (для reference)
├── life-board.php            ← НОВ дизайн (бивш life-board-v2.php) — 152K, 2050+ lines
├── life-board.php.bak.S140   ← Стар дизайн
├── settings.php, sale.php, products.php, ...  ← Стар дизайн (предстои migration)
├── docs/
│   ├── LAYOUT_SHELL_LAW.md v1.1
│   ├── S140_FINALIZATION.md           ← този документ
│   ├── SIGNALS_CATALOG_v1.md          ← 1000 sигнал templates
│   ├── PRODUCTS_DESIGN_LOGIC.md       ← за S141
│   ├── KNOWN_BUGS.md                  ← shimmer + feedback unsolved
│   └── ...
└── design-kit/                ← 13 locked files (PROMPT.md, check-compliance.sh, theme-toggle.js)
```

---

## 7. NEXT SESSION (S141) BOOT

При нов чат, paste-ни SHEF_RESTORE_PROMPT_v3.md и добави:

```
S141 = products.php redesign (15 000 реда).

Прочети:
1. docs/S140_FINALIZATION.md (този документ — workflow + universal UI laws)
2. docs/PRODUCTS_DESIGN_LOGIC.md
3. <втория products документ ако Тих го е предоставил>
4. docs/KNOWN_BUGS.md
5. docs/LAYOUT_SHELL_LAW.md v1.1
6. products.php (целия — оrient се с tool_search keywords)

Стратегия: INJECT-ONLY (не rewrite). Виж §4 в S140_FINALIZATION.md.

Backup tag: pre-products-redesign-S141 (направи преди start).
```

---

**Край на S140.** Beta ENI testing: 14-15 май 2026. Days remaining: 3-4.
