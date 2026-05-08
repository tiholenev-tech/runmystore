# 🎯 PRIORITY_TODAY — 04.05.2026 (Понеделник)

**Beta countdown:** 10 дни до 14-15.05.2026 ENI launch  
**Last EOD:** 03.05.2026 — wizard core ✅, documentation backlog ⚠️  
**Шеф-чат carry-over:** 5 файла documentation backlog от 03.05

---

## 📋 PRE-FLIGHT (10 мин преди СЕСИЯ 1)

### Verify yesterday's commits
```bash
cd /var/www/runmystore
git log --oneline -8
# Expected top: 8100c34 S95.PART1_1_A → 0ccdb52 PART1_1 → cad029e PART1
```

### Verify EOD documentation push
```bash
ls -la /var/www/runmystore/MASTER_COMPASS.md /var/www/runmystore/STATE_OF_THE_PROJECT.md
# Should show 03.05 update timestamp ако EOD е push-нат
```

Ако НЕ → push-вай EOD documentation **първо**, преди execution work.

### Verify TESTING_LOOP
```bash
cat /var/www/runmystore/tools/testing_loop/latest.json | python3 -c "import json,sys; d=json.load(sys.stdin); print('Last snapshot:', d.get('cron_run_at')); print('Status:', d.get('cron_last_status'))"
# Expected: snapshot from 04.05 sutrint OR 03.05 night (S92.STRESS.DEPLOY active)
```

---

## 🥇 TOP 3 PRIORITY (derived from STATE LIVE BUG INVENTORY P0)

### #1 — S95 ЧАСТ 1.2 voice-first (Whisper + trigger words)
**Time:** 2-3 ч  
**Source:** RWQ-72 P0  
**Code Code session:** S95.WIZARD.RESTRUCTURE.PART1_2_VOICE_FIRST  
**Predecessor:** 8100c34 (PART1_1_A)  
**Goal:** Закон №1 — Пешо НЕ пише.  

**Spec:**
- **Web Speech API** (Tier 1): trigger words (следващ, назад, запиши, печатай, пропусни, стоп) + text fields (Име, Доставчик, Категория, Подкатегория, Състав, Произход, Мерна единица)
- **Whisper Groq** (Tier 2): numeric fields (Цена дребно, Доставна, Едро, Брой, Min Quantity, Баркод, Артикулен номер)
- **State machine:** active field мига indigo glow → "следващ" → next field
- **Continuous mic listen** (pause при visibilitychange)
- **Field validation** преди "следващ" разрешен
- **Backend:** services/whisper-transcribe.php (NEW, ~80 LOC, Groq API forward)
- **JS:** js/voice-engine.js (NEW, ~200 LOC) + products.php integration (~400 LOC)
- **LOC budget:** target 700, ceiling 1200

**3 commits planned (push between each):**
1. Backend + audio recording + voice-engine.js skeleton
2. Field state machine + glow CSS + trigger word parallel listen
3. Field-by-field wiring + validation

**DOD:**
- Single product entry voice-only (без писане) → save → mini print overlay
- Voice cost log в voice_command_log table
- Tenant cost increment в tenants.ai_voice_cost_month_eur

### #2 — S95 ЧАСТ 1.3 AI Studio entry (e1 design)
**Time:** 30-45 мин  
**Source:** RWQ-73 P0 + RWQ-77 mockups upload prerequisite  
**Code Code session:** S95.WIZARD.RESTRUCTURE.PART1_3_AI_STUDIO  

**Pre-condition (Тихол manual):**
- Upload `ai-studio-main-v2.html` + `ai-studio-categories.html` + `ai_studio_FINAL_v5.html` в `/var/www/runmystore/mockups/` чрез FileZilla
- `git add mockups/ && git commit -m "S95.AI_STUDIO_MOCKUPS: imported designs from chat 4a90aa70" && git push`
- Без mockups Code Code не може да работи правилно

**Spec (e1 inline design):**
- Под снимка thumbnail в step 1 + step 2 (Single)
- Под снимка thumbnail в step 1 + step 3 (Variations)
- 3 reda visible само ако product има снимка (`S.wizData.photoUrl`)
  - 🖼 Махни фон (€0.05) — fal.ai birefnet endpoint (existing работещ)
  - 📝 SEO описание (€0.02) — Gemini text gen (existing)
  - ✨ AI магия (€0.30) — fal.ai try-on (existing endpoint, но not production-wired RWQ-78)
- За beta: bg removal + SEO работят, AI магия = "Скоро" tooltip (graceful degradation)
- LOC budget: target 100-200

**DOD:**
- AI Studio entry visible на правилните steps когато снимка present
- Bg removal click → working call → result inserted в product
- SEO описание click → Gemini call → text inserted в Composition field
- AI магия click → "Скоро" tooltip + log feature_request в DB

### #3 — S95 ЧАСТ 2-4 finalize wizard
**Time:** 1.5-2 ч  
**Source:** S95-PART2/3/4 P0 в STATE inventory  

**ЧАСТ 2 (45 мин):** Matrix preserve + zone field S94 already present + ЗАПИШИ button + skip-Single logic  
**ЧАСТ 3 (45 мин):** Move prices/composition към step 3 + save logic for variations  
**ЧАСТ 4 (30 мин):** Cleanup obsolete steps 0/4/6 (HTML hard-remove)

LOC budget: target 700, ceiling 1200

---

## 🥈 SECONDARY priorities (ако time остава)

### #4 — Sale.php S87E + Pesho-in-the-Middle hardening
**Time:** 2-3 ч  
**Source:** sale-S87E P0 + RWQ-64 P0  

8 bugs from Sprint E + audit trail + atomicity protection. Disjoint от products.php → можем паралелна Code Code сесия.

### #5 — TRACK 2 нови P0 fixes
**Time:** 1-2 ч  
**Source:** NAME_INPUT_DEAD, D12_REGRESSION, WHOLESALE_NO_CURRENCY P0  

Need spec first (попитай TRACK 2 помощник за file/line refs). После Code Code fix.

### #6 — Documentation backlog от 03.05
**Time:** 30-60 мин  
**Source:** RWQ-69 P1  

Ако EOD push не е минал успешно → push-ваме днес сутрин:
- COMPASS update + ROADMAP_v2 + STATE refresh + BOOT_TEST update + STRESS_BOARD ГРАФА 1

---

## 📊 STRESS_BOARD ГРАФА 1 — нощни тестове за 04→05.05

(Ще запиша в STRESS_BOARD преди да го push-неш)

```
04.05.2026 нощни сценарии:

S95 wizard restructure:
- Single 2-step save flow (Име+Цена+Брой → ЗАПИШИ → mini overlay → close)
- Auto-formula min qty Math.round(qty/2.5) min 1 — 5→2, 7→3, 10→4
- Manual override on min qty respected
- Toggle Единичен/Вариации mandatory choice
- Dropdowns Доставчик/Категория inline auto-filter
- Print fallback от mini overlay
- "Като предния" button placeholder/active states
- Variations Напред → matrix preserved
- Toast при ЗАПИШИ от step 1 на Variations

ENI critical 4 модула baseline (за подготовка):
- Sale.php S87 production read-only stress (no writes)
- Warehouse.php read-only stress
- Deliveries.php (will create today/tomorrow) — placeholder
- Transfers.php (will create later in week) — placeholder

Edge cases:
- BLE printer reconnect after sleep
- localStorage tenant data integrity post-S88-multi-printer
- Concurrent product create (race condition test)
```

---

## ⚠️ BLOCKERS for 04.05

| # | Blocker | Owner | Action |
|---|---|---|---|
| 1 | EOD documentation от 03.05 не push-нат | Тихол | Push първо сутрин преди execution |
| 2 | AI Studio mockups upload (RWQ-77) | Тихол manual | FileZilla upload в mockups/ + commit |
| 3 | Code Code push-to-main harness блокира | Тихол | Approve permission в settings (one-time) |
| 4 | TRACK 2 P0 specs unclear (NAME_INPUT_DEAD/D12/WHOLESALE) | TRACK 2 помощник | Get file:line refs |
| 5 | FAL_API_KEY working but integration not (RWQ-24b) | post-beta | Defer |

---

## 🎯 SUCCESS CRITERIA за 04.05

✅ S95 wizard ЧАСТ 1.2 + 1.3 + 2-4 ALL DONE на main  
✅ Voice-first работи end-to-end (Single product entry voice-only)  
✅ AI Studio entry visible на правилните steps  
✅ Wizard визуално 3 stъпки (Variations) или 2 stъпки (Single)  
✅ STRESS_BOARD ГРАФА 1 active за нощни тестове  
✅ Beta countdown: 10 дни  

❌ FAILURE if: ЧАСТ 1.2 не завърши (което би значило 4-ти ден на същия проблем)
PENDING REBOOT: kernel 6.8.0-111-generic — изпълни само при чиста сесия (tmux ls празно + git clean) + СЛЕД beta launch (15.05+)
