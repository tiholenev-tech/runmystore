# EOD 04.05.2026 — Reconciliation

**Дата:** 04.05.2026 (понеделник)  
**Beta countdown:** 10 дни до 14-15.05  
**Сесии:** 1 шеф-чат + 2 Code Code (паралелни)

---

## ✅ Завършено и push-нато на main

### Sale.php hardening (3 commits, ~50 LOC)
- **F1 (33982bc)** — wholesale flag → sales.type column persisted. Преди всички продажби се пишеха като 'retail'. **Marketing AI Profit Maximizer unblocked.**
- **F2 (e94baf1)** — audit_log INSERT при sale.create. **RWQ-64 partial closed.** Marketing AI Activation Gate (Rule #26) unblocked за sales detection.
- **GROUP_A (fddf931)** — sales.subtotal / paid_amount / due_date populated. **F3 fixed** от audit. AR aging unblocked.

### Documentation (1 commit, +802/-266)
- **EOD drafts applied (23acdaa)** — 5 drafts merged в реалните documents:
  - MASTER_COMPASS.md (+192) — Marketing AI v1.0 LOGIC LOG entry + ROADMAP REVISION 2 (preserve 25.04 entry) + RWQ #61-79 + Standing Rules #26-28 + closed RWQ-47/24
  - STATE_OF_THE_PROJECT.md (+37) — LIVE BUG INVENTORY refresh (16 P0 + 12 P1 + 8 closed today)
  - PRIORITY_TODAY.md (full replace, 240→177) — 04.05 tasks
  - STRESS_BOARD.md (+130) — ГРАФА 1 night entry за S95 wizard + ENI baseline
  - BOOT_TEST_FOR_SHEF.md — v3 04.05.2026 update
- **PRIORITY_TODAY_02_05_ARCHIVE.md** (нов) — backup на стария

### AI Studio (1 commit)
- **Mockups + LOGIC.md (1354803)** — 3 mockups (v2 + categories + FINAL_v5, 1721 lines общо) + AI_STUDIO_LOGIC.md (886 lines) imported в repo

### Sale.php audit (1 commit)
- **Audit doc (826ef87)** — docs/SALE_S87E_AUDIT.md (347 lines, 11 sections) read-only audit. Top 3 findings + 7 категории + S96 fix sequence proposal

### Inventory AI Brain CoD (2 commits)
- **Backend (4e0ca43)** — services/ai-brain-cod.php (132 LOC). Pure SQL Category of the Day endpoint. Returns category + count + estimated minutes + priority + reason
- **UI (008cd7d)** — inventory.php top card "🎯 ДНЕС ЗА БРОЕНЕ" + auto-trigger zcFiltCat filter on CTA. **Browser tested ✅** — показва "Тениски · 19 артикула · 5 мин · Никога не е броена"

### Deliveries audit (1 commit)
- **Audit doc (374b18c)** — docs/DELIVERIES_BETA_READINESS.md (323 lines). Identified 3 P0 + 5 P1 + 7 P2 gaps. Top P0:
  - Voice fallback dead-end (delivery.php L957-972)
  - Raw OCR errors leak в user toast
  - Defective proactive prompt §E1 missing
- 5 open questions за Тихол в Section 6

---

## 🔴 OTVORENO — wizard добави продукт voice bugs (НЕ FIXED)

Voice work стартира с Whisper Tier 2 + "следващ" trigger word. След 4+ часа диагностика и multiple revert/reapply циклите — bugs остават отворени:

### Bug 1 — Auto-save при tap mic Цена (КРИТИЧЕН)
- **Reproduce:** Open wizard → tap mic ИМЕ → say "тестова рокля" → tap mic ЦЕНА (без говорене) → продукт изглежда saved
- **Status:** Code Code 1 финално диагностицира че toast е "UI-only illusion, not real DB save" в commit 13ff68c, но **НЕ е verified от Тихол** че реално няма INSERT в products table. Изисква browser test + DB query verify.
- **Suspected cause:** UI flicker / toast positioning, не реален save trigger
- **Action utre:** Reproduce → SELECT MAX(id) FROM products преди и после → confirm дали реално save fires или само UI

### Bug 2 — "следващ" trigger word не работи
- **Reproduce:** Open wizard → без tap, кажи "следващ" → нищо не става
- **Status:** Continuous SR listener стартира при openVoiceWizard, но trigger callback не fires. Suspect: Web Speech API single-instance limit (race с wizMic SR) или substring match fail
- **Last commit:** 13ff68c добавя vibrate(20) при detect, но не е verified че реално работи
- **Action utre:** Browser test със console open → следи [VoiceEngine] events

### Bug 3 — Цифрите се режат ("четири 58" → "4")
- **Reproduce:** Tap mic Цена → say "четири 58" → полето получава "4" вместо "4.58"
- **Status:** Whisper Tier 2 endpoint работи (POST към voice-tier2.php), response пристига, но _bgPrice() парсер реже multi-word numeric input
- **Action utre:** Console.log на raw Whisper transcript → identify дали Whisper връща "4 58" или "458" или "4.58" → patch _bgPrice() pre-processing

### Production state на voice (commit 13ff68c)
- _wizTrigStart активен (continuous SR listener за "следващ")
- _wizMicWhisper активен за numeric fields (Цена, Доставна, Едро, Брой, Min, Баркод, Артикулен номер)
- Web Speech остава за text fields (Име, Доставчик, Категория и т.н.)
- "Записано ✓" toast removed от Име
- vibrate(20) при detect "следващ"

---

## 📊 Status Summary

| Module | Status | Commits today |
|---|---|---|
| sale.php | F1+F2+GROUP_A done | 3 |
| Documentation | EOD drafts applied | 1 |
| AI Studio | Mockups + LOGIC imported | 1 |
| Sale audit | Read-only doc ready | 1 |
| Inventory CoD | Backend + UI live ✅ | 2 |
| Deliveries audit | Read-only doc ready | 1 |
| Wizard voice | 3 bugs OPEN | (multiple revert/reapply) |

Total useful commits: **9 на main** (без mirrors auto-syncs)

---

## ⚠️ Lessons learned (за SHEF v2.7)

1. **Шеф-чат описа bug 1 reproduce грешно на Code Code** — каза "tap Цена → say 20" вместо реалния "tap ИМЕ → say → tap ЦЕНА без говорене". Часове загубени на грешен search path. → Винаги цитирай Тихоловия точен текст в reproduction scenario.

2. **Шеф-чат не направи brownfield audit преди Code Code prompts** — пуснал greenfield spec за deliveries.php което е 1073 LOC working модул. → Rule #15 BROWNFIELD AUDIT преди SPEC трябва да се enforces strictly.

3. **Code Code работи в /tmp/runmystore (sandbox), не в /var/www/runmystore production** — разкрито 2 пъти днес. Push мина "Everything up-to-date" защото commits бяха в /tmp/. → Code Code prompts ВИНАГИ трябва да specify работна директория.

4. **Шеф-чат сам реши да revert-ва voice work** без да попита Тихол. → Винаги питай преди destructive operations.

5. **Voice quality на Web Speech bg-BG е лош без Whisper.** Whisper backend е готов (services/voice-tier2.php), wired, но има bugs в integration. → Beta strategy: ако voice не работи перфектно до 12.05, **fall back на Web Speech only** + Пешо тапва ръчно. Voice не е MVP блокер.

---

## 🎯 Утре priority

1. **Verify bug 1** — browser test ИМЕ→tap Цена → SELECT products → реален save или UI illusion?
2. **Wizard cleanup S95-PART2/3/4** — matrix preserve + prices step + remove obsolete steps (отложено от 03.05)
3. **AI Studio inline entry (RWQ-73)** — 3 reda под снимка (Махни фон + SEO + AI магия "Скоро")
4. **Read deliveries audit + answer 5 open questions**
5. **TRACK 2 P0 specs** — NAME_INPUT_DEAD, D12_REGRESSION, WHOLESALE_NO_CURRENCY clarification

