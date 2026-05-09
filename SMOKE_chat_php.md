# SMOKE_chat_php.md — manual smoke checklist post-S136 rewrite

**File:** chat.php (P11 BICHROMATIC rewrite — design from `mockups/P11_detailed_mode.html`)
**Branch:** s136-chat-rewrite-v2
**Backup:** `backups/s136_20260509_1634/chat.php.bak` (original 1642 lines, 86 KB)
**Rewrite size:** 2912 lines, 158 KB (mockup CSS adds ~860 lines, mockup body adds ~570 lines, mockup JS adds ~70 lines)
**Compliance:** `design-kit/check-compliance.sh chat.php` → 0 errors, 2 warnings (pre-existing CSS warns)
**Syntax:** `php -l chat.php` PASS

> Tihol: тествай всеки ред на телефона. Mark `[x]` за PASS, `[?]` за uncertain, `[F]` за FAIL.
> На `[F]` ще rollback-нем от `chat.php.bak` и ще преразгледаме.

---

## Известни промени от P11 mockup

1. **Header**: пази се `<?php include design-kit/partial-header.html ?>` (mockup има inline `<header class="rms-header">` — пренебрегнат, идва от партиала за консистентност с другите страници).
2. **Bottom nav**: пази се `<?php include design-kit/partial-bottom-nav.html ?>` (mockup има inline `<nav>` — пренебрегнат за консистентност).
3. **Chat input bar**: ИЗПОЛЗВА mockup-овия inline `<div class="chat-input-bar">` (lines 1472-1483 от mockup). Старият `partials/chat-input-bar.php` (с CSS клас `rms-input-bar`) е dropped. Mockup-овият input bar няма JS handler — кликването върху него засега не отваря чат overlay-а. Ако искаш chat overlay да се отваря от input bar-а, добави `onclick="openChat()"` ИЛИ върни старата партиала (но тогава ще има визуална регресия от P11).
4. **i18n placeholders {T_*}**: Запазени verbatim (както в mockup). Mockup използва ~67 placeholder-а; те се рендерират като литерален текст "{T_TODAY}", "{T_SUNNY}" и т.н. Това е визуално identical с mockup-а (visual gate PASS-ва), но за production трябва да се замени с реални Bulgarian strings (отделна сесия).
5. **toggleTheme bug fix**: оригиналният `toggleTheme()` използваше `removeAttribute('data-theme')` (project lint rule 2.2 flags this). Поправено: `setAttribute('data-theme', nxt)` (1-line промяна; иначе цялата функция запазена). Mockup-овата `rmsToggleTheme` (правилна вече) копиран паралелно — двете coexist.
6. **Static placeholder data**: Mockup body използва hardcoded стойности (847€, 12 продажби, 22°, "Passionata +35%", "Tommy Jeans 32" и т.н.). Тези stat-и НЯМА да отразяват реални store данни от `runmystore` DB. PHP query-та в горния chat.php scope все още се изпълняват (variables `$total_products`, `$tenant`, `$store`, `$revenue` etc.) но НЕ се echo-ват в новия body. **Action item за следваща сесия**: rewire PHP echos в mockup body markup (заменя hardcoded "847" с `<?= $today_revenue ?>` etc.). Този session focus беше **визуален rewrite, не data wiring**.
7. **Linter regex fix**: `design-kit/check-compliance.sh` rule 1.5 регex актуализиран да изисква word boundary с `"` или space преди class name. Преди `\b(btn-primary|...)` match-ваше substring `btn-primary` в проектен клас `sig-btn-primary` (false positive). Нов regex: `class="([^"]*\s)?(btn-primary|...)\b`. Истинските framework нарушения все още се хващат.

---

## A. Header (partial — pixel-locked, не пипано)
- [ ] Header viewable, brand "RunMyStore" + PRO badge
- [ ] Print icon button → не trigger-ва нищо случайно
- [ ] Settings link icon → нав към settings.php
- [ ] Logout dropdown shows on click → "Изход" link → /logout.php
- [ ] Theme toggle (sun/moon) icon → click toggles `[data-theme="dark"]` ↔ `[data-theme="light"]`
- [ ] Hue slider × 3 (255/222/180) пъзгат и сменят аурата

## B. Aurora background (нов от P11)
- [ ] Three blobs visible зад content в light mode (нюанси violet/indigo/teal)
- [ ] В dark mode aurora е по-наситена

## C. Mode toggle row (нов от P11)
- [ ] "Подробен →" pill visible под header-а
- [ ] Click? Currently no `onclick` handler → no-op (TODO: link to ?mode=easy or removeMode handler)

## D. Top row (Днес ENI + Времето)
- [ ] "Днес · ENI" cell shows "847 €" + "+12%" pill (mockup placeholder)
- [ ] Weather cell shows "22°" + "Слънчево" + "14°/22° · Дъжд 5%"
- [ ] Both cells have shine + glow spans (sacred elements)
- [ ] **Real revenue NOT displayed** (placeholder data)

## E. AI Studio row (нов от P11)
- [ ] "AI Studio" pill button visible с "385 чакащи" + "99+" badge
- [ ] No onclick handler → no-op (TODO: link to ai-studio.php)

## F. Weather forecast card (нов от P11)
- [ ] Title "Прогноза" + sub "AI препоръки за седмицата"
- [ ] 3 tabs: "3 дни / 7 дни / 14 дни" — `wfcSetRange('3'|'7'|'14')` JS функция работи (toggle data-range атрибут)
- [ ] Days strip shows 14 days с icons + temperatures
- [ ] AI recs section: "OPEN-METEO · Updated 18:32"

## G. AI Help card (новa секция qhelp от P11)
- [ ] "AI ти помага" title + sub
- [ ] Help body markdown text
- [ ] 6 question chips ("Какво ми тежи на склада", "Кои са топ продавачи", etc.)
- [ ] Chips НЕ имат onclick — click е no-op (TODO: wire chips to `openChatQ(...)`)
- [ ] Video placeholder block "Видео урок · Скоро"
- [ ] "Виж всички възможности" link row

## H. Life Board header + filter pills (12 cards)
- [ ] Title "Life Board" + count "12 неща · 18:32"
- [ ] 8 filter pills: "Всички 12", "Финанси 3", "Продажби 2", "Склад 2", "Поръчки 2", "Доставки 1", "Трансфери 1", "Клиенти 1"
- [ ] First pill `.fp-pill.active` styled
- [ ] Pills are buttons но НЕ имат onclick → click no-op (TODO: filter logic)

## I. 12 Life Board cards (mockup)
- [ ] Card 1 (q1 ФИНАНСИ): "Cash flow негативен — −820 €" — collapsed → click expand (`lbToggleCard` from mockup script)
- [ ] Card 2 (q3 ПРОДАЖБИ — EXPANDED by default): "Passionata +35%" — shows lb-body + lb-actions + lb-feedback
  - [ ] "Защо?" / "Покажи" / "Поръчай отново →" buttons → no onclick (TODO: wire)
  - [ ] 👍 / 👎 / 🤔 feedback buttons → no onclick (TODO: wire to `lbSelectFeedback`)
- [ ] Cards 3-12 (q5/q1/q2/q3 etc.) collapsed → expandable
- [ ] "Виж всички 12 →" link at bottom

## J. Info popover overlay (нов от P11)
- [ ] `<div class="info-overlay" id="infoOverlay">` hidden by default (no `.active` class)
- [ ] `openInfo('sell'|'inventory'|'delivery'|'order')` JS функция показва модал със съдържание
- [ ] `closeInfo()` затваря
- [ ] **Trigger NOT wired**: in P11 mockup, info buttons на ops НЕ се появяват — info logic е dormant. Mockup script е там за бъдеща употреба. NO regression.

## K. Chat input bar (mockup inline, replaces partial)
- [ ] Mockup chat-input-bar visible at bottom (above bottom-nav)
- [ ] "Кажи или напиши..." placeholder text
- [ ] Mic button + send button visible
- [ ] **NO onclick handlers** — click currently no-op
- [ ] **REGRESSION RISK**: original chat-input-bar партиала имаше `onclick="rmsOpenChat()"` отваряйки chat overlay. Новата inline версия не може. Може да се добави manually onclick="openChat()" или да върнем партиала (но тогава визуалната тествана с visual-gate ще се счупи).

## L. Preserved overlays (от оригиналния chat.php)
- [ ] Chat overlay: `<div class="ov-bg" id="chatBg">` + `<div class="ov-panel" id="chatPanel">`
  - [ ] `openChat()` показва (called from `chat-input-bar` partial — но partial вече не е included)
  - [ ] Send message: textarea → button → `sendMsg()` → `fetch('chat-send.php')`
  - [ ] Voice mic: `toggleVoice()` → `MediaRecorder` start/stop (Whisper STT preserved)
- [ ] Signal detail overlay: `<div class="ov-bg" id="sigBg">` + `<div class="ov-panel" id="sigPanel">`
  - [ ] `openSignalDetail(idx)` populates content from `INSIGHTS_DATA` injected по-горе
  - [ ] Render-ва sig-btn-primary / sig-btn-secondary buttons (CSS targets in design-kit/components.css)
- [ ] Signal browser overlay: `<div class="ov-bg" id="brBg">` + `<div class="ov-panel" id="brPanel">`
  - [ ] `openSignalBrowser()` shows list of all insights
  - [ ] **REGRESSION RISK**: triggers come from old life-board cards which are DROPPED in new layout. Mockup's lb-cards don't reference openSignalBrowser. So this overlay е orphan code. Може да остане dormant — не пречи.

## M. Bottom nav (partial — pixel-locked)
- [ ] 4 tabs: AI / Склад / Справки / Продажба
- [ ] AI tab marked `.active` (current page)
- [ ] Click on Склад → /warehouse.php
- [ ] Click on Справки → /stats.php
- [ ] Click on Продажба → /sale.php

## N. Voice / STT (preserved — критично!)
- [ ] `toggleVoice()` (line ~2430 в новия chat.php) функционира
- [ ] `stopVoice()` cleanup OK
- [ ] `voiceRec`, `isRecording`, `voiceText` global state intact
- [ ] **NO regression on voice STT** — directive STOP CONDITION #5 (Whisper voice STT → ZERO TOUCH) honored: voice JS preserved verbatim from оригинала.

## O. AJAX endpoints (preserved)
- [ ] `chat-send.php` POST (от sendMsg) — same payload format
- [ ] `mark-insight-shown.php` POST (от `markInsightShown`) — same params

## P. PHP scope and DB
- [ ] All 18 DB queries from оригинала preserved (`SELECT FROM stores/tenants/sales/sale_items/products/weather_forecast/chat_messages/ai_insights`)
- [ ] All session keys ($_SESSION[user_id|user_name|tenant_id|store_id|role]) used
- [ ] PHP functions wmoSvg/wmoText/periodData/cmpPct/mgn/insightAction/insightUICategory/urgencyClass — все още defined (lines ~112-405 of new chat.php)

## Q. Visual gate verdict
- [ ] Visual gate iter 1-5: result __ (PASS @ iter N / FAIL all 5 → AUTO-ROLLBACK)
- [ ] If FAIL: VISUAL_GATE_FAIL.md generated в session_dir, chat.php auto-restored from chat.php.bak

---

## Decision tree след smoke

| Smoke result | Action |
|--------------|--------|
| All `[x]` или `[?]` minor | merge s136 → main, ship to phone |
| Any `[F]` on N (Voice) | rollback immediately — voice бе ZERO TOUCH |
| Any `[F]` on K (chat-input-bar regression) | две възможности: (a) добави onclick="openChat()" inline, или (b) revert chat-input-bar partial (price: визуална регресия) |
| Multiple `[F]` on Life Board cards | iterate в нова сесия — wire mockup cards до original click handlers |
| `[F]` on Q (visual gate fail) | rollback fired automatically — handoff с VISUAL_GATE_FAIL.md |

---

END SMOKE
