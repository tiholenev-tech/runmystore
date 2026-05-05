# 🎯 PRIORITY — 02.05.2026 (СЕСИЯ 1)

**Дата create:** 01.05.2026 края на ден  
**Beta deadline:** 14.05.2026 = **13 дни остават**  
**Документ написан след DOCUMENT_PROTOCOL** — три прочитания на разговора, всички решения извадени, противоречия отбелязани.

---

## 🚪 СТАРТОВ ПРОТОКОЛ — ВИНАГИ ПРЕДИ TMUX/CLAUDE

```bash
# 1. Switch към tihol (Claude Code 2.1.126+ забранява root)
su - tihol

# 2. Влез в проекта
cd /var/www/runmystore

# 3. Стартирай tmux с описателно име
tmux new -s [име]

# 4. Стартирай Claude Code
claude

# 5. Trust folder = 1, после Shift+Tab за auto-accept
```

**Tmux команди:**
- Detach: `Ctrl+B`, после `D`
- Attach: `tmux attach -t [име]`
- Списък: `tmux ls`
- Убий конкретна: `tmux kill-session -t [име]`
- Убий всички: `tmux kill-server`

**Ако Claude Code откаже:** провери prompt-а. `tihol@...$` = OK. `root@...#` = `exit` → `su - tihol` → нов tmux.

---

## 🌅 ПЪРВО НЕЩО УТРЕ — BROWSER TEST

**Само Тихол. ~30-45 мин. Не пускай Code Code преди да приключиш това.**

### А) Test chat.php (visual migration)
- Отвори `runmystore.ai/chat.php` мобилен/десктоп
- Header нормален? Bottom nav нормален?
- Theme toggle (sun/moon top right) → tap → сменя ли тема?
- Hue sliders под лого → mvат hue?
- Изпрати тест съобщение на AI — отговаря ли?
- Виждаш ли insights cards в home? **Колко?**
  - 0-3: routing fix не работи → rollback нужен
  - 4-15: правилно (приключихме)
  - 30+: spam → rollback

### Б) Test sale.php
- Отвори продажба, добави 1 артикул, плати в кеш
- Custom numpad работи?
- Camera-header работи (ако сканираш)?
- Voice бутон [🎤] работи?
- Race condition test (по желание): 2 tab-а паралелно, продай същия артикул с 1 наличност → 1 успех / 1 грешка

### В) Test products.php (Sprint B verify — КРИТИЧНО)
**Code Code откри че Sprint B claim 8/8 done не е верен.** Verify ръчно:
- Отвори "Нов артикул" wizard
- **C1:** В стъпка размери — има ли бутон "+ Добави размер" долу? *(suspect MISSING)*
- **C2:** Поле "Артикулен номер" — има ли scanner икона до него? Поле "Баркод" — също?
- **C3:** Артикулен номер и Баркод — в **отделни** qcard.glass containers ли са? *(или stack-нати?)*
- **C4:** Когато AI auto-fill-ва нещо — виждаш ли hint текст под полето "AI попълни — натисни за промяна"?
- **C5:** Има ли стрелка ← в горния ляв ъгъл за връщане стъпка назад? *(suspect: има, но без правилен class)*
- **D3:** Избери supplier "Дафи" → списъкът категории показва ли само неговите?
- **D5:** Започни да пишеш име което вече съществува → след 3+ символа viждаш ли жълт banner "Близко: ..."?
- **G1:** Swipe ляво/дясно по табове в products — вече **не реагира** ли?

**Документирай:** какво работи, какво не. Това става S92 backlog.

### Г) Test S89 модули (delivery + orders + defectives)
- runmystore.ai/delivery.php?action=new
- runmystore.ai/orders.php
- runmystore.ai/order.php
- runmystore.ai/defectives.php (Detailed Mode only — Simple redirect)
- runmystore.ai/deliveries.php

### Д) Test admin/insights-health.php
- runmystore.ai/admin/insights-health.php (само owner role)
- Виждаш ли разпределението module + count?
- Hidden % е 0% (ако fix работи) или още висок?
- Ако > 30% hidden → S92 routing fix needed

### Е) Mobile responsive check
- Отваряй всички modules на телефон Z Flip6
- Header/footer не се чупят ли?
- Skroll работи ли?

---

## 📋 ЗАДАЧИ ЗА УТРЕ — ПО ПРИОРИТЕТ

### P0 — БЛОКЕРИ ЗА BETA

**1. Browser test (горе) — ПРИКЛЮЧИ ПРЕДИ ВСИЧКО ДРУГО**

**2. Inventory модул — ПОЧВАМЕ (паралел с останалите)**
- Тихол има допълнение към спецификацията — обсъдете преди Code Code
- Reference: `/mnt/project/INVENTORY_v4.md` + `/mnt/project/INVENTORY_HIDDEN_v3.md` в repo
- Файл: inventory.php (~80% built per memory) — verify реален статус
- Цел: end-to-end inventarizatsiya flow в Simple + Detailed modes
- Разделение: Simple (Пешо: voice пре-броене карта по карта) / Detailed (Митко: PDF export, history, edits)

**3. Sprint B follow-up на products.php (S92.PRODUCTS.B_FIX)**
След browser test:
- Добави "+ Добави размер" бутон ако липсва (C1)
- Поправи ChevronLeft да има CSS class (C5 compliance)
- Попълни всеки друг missing fix

**4. Решение за insights routing rollback ако наводни**
- Ако browser test показва > 30 сигнала на life-board → rollback default 'home' fix
- Опция: explicit module='home' само за 6-те S89 функции (Path B от INVESTIGATION_REPORT)

### P1 — ВАЖНИ

**5. Mirror cron auto-sync fix (7 incidents днес — pattern)**
- /etc/cron.d/ или crontab на www-data — къде е?
- Trigger при `git status` modified files? Или периодично?
- Решение: добави `--only=mirrors/` flag или skip-вай `*.php`/`*.md` ако не е в `mirrors/` папка
- Нов commit message override

**6. Update STATE_OF_THE_PROJECT.md (5 дни outdated)**
- Включи S89 + S90 + S91 завършвания
- Phase A2 progress
- DESIGN-KIT v1.1 status
- Beta countdown 13 дни

**7. REWORK QUEUE entries за post-beta**
- C1 + C5 на products.php (open от Sprint B)
- Mirror cron pattern (post-beta priority)
- Insights routing follow-up (ако rollback нужен)
- 46 backdrop-filter blur effects изгубени в migration (visual polish)
- 'Добави размер' и подобни visual claim-ове от handoff не верифицирани browser-wise

### P2 — POST-BETA

**8. Migration на още стари модули към design-kit**
- life-board.php
- warehouse.php (още е скелет)
- ai-studio.php
- stats.php
- printer-setup.php
- onboarding.php

**9. Auto-draft поръчки (compute-orders.php нов файл)**
- Тихол идея запазена — proactive insights в life-board
- Премахнат silent mode — винаги има сигнали
- Spec в ORDERS_DESIGN_LOGIC.md секция 13

**10. STRESS Етап 2 (admin/stress-board.php)**
- Преди Етап 1 (свят) — quick wins без създаване на нов tenant 99 свят

---

## 🚨 ОТКРИТИ FLAGS / ВЪПРОСИ

### От Code Code observations:
1. **'Добави размер' бутон не съществува** в products.php (Sprint B optimistic) — verify утре
2. **ChevronLeft inline без CSS class** — compliance gap S92
3. **46 backdrop-filter blur effects изгубени** — приема ли се визуално? (S91.MIGRATE.SALE + S91.MIGRATE.PRODUCTS)
4. **design-kit/partial-header.html hardcoded "PRO" plan badge** — bug в partial, post-beta fix
5. **design-kit/partial-bottom-nav.html маркира всички 4 таба active** — bug в partial, post-beta fix

### Архитектурни (чакат твое решение):
1. **Дефектни модул място:** A) 6-та card warehouse.php hub, B) бутон в Доставки, C) supplier detail
2. **Insights default routing:** Ако browser test показва spam → rollback или explicit module='home' към 6-те S89?
3. **STRESS бъг #6 path:** STRESS_BOARD казва `tools/diagnostic/cron/sales_pulse.py`, memory казва `tools/seed/sales_populate.py` — verify

### Технически:
1. **GROQ_API_KEY** — pending. Voice flow работи без него (правилен error). Ако имаш ключа: `echo 'GROQ_API_KEY="gsk_..."' | sudo tee -a /etc/runmystore/api.env`
2. **www-data crontab** — за mirror cron diagnose
3. **STRESS Етап 1+2 cron часове** — не са инсталирани още (документирани, не deployed)

---

## 📊 СТАТУС НА ПРОЕКТА (01.05.2026 края на ден)

### Завършено днес:
| Commit | Какво | Резултат |
|---|---|---|
| 25741fb | STRESS docs (4 файла) | ✅ |
| 9862b04 | S89 DELIVERY+ORDERS (13 файла, +4931 реда) | ✅ |
| e6daca2 | S89.HOTFIX fmtMoney duplicates | ✅ |
| e1d7316 + 5fd81d3 | DESIGN-KIT v1.1 theme-toggle + 5 modules | ✅ |
| ee20fc3 | STRESS DECISIONS (8 решения) | ✅ |
| 34041ca | S90.RACE sale.php atomicity | ✅ |
| c0146c6 | S90.PRODUCTS.SPRINT_B (claim 8/8, реално 6/8) | ⚠️ |
| a90bb82 | INVESTIGATION_REPORT.md | ✅ |
| 1c69012 | S91.MIGRATE.CHAT (-51% size) | ✅ |
| 04fa915 | S91.MIGRATE.SALE | ✅ |
| c5e969e | S91.MIGRATE.PRODUCTS | ✅ |
| c9009d2 | S91.INSIGHTS_HEALTH (routing + monitor) | ✅ |

**+ 7 mirror auto-sync hijack commits**

### Live в production (всички runmystore.ai):
- chat.php, sale.php, products.php — мигрирани към design-kit v1.1
- delivery.php, deliveries.php, orders.php, order.php, defectives.php — design-kit native
- admin/insights-health.php — диагностика monitor
- INVESTIGATION_REPORT.md, SESSION_S91_*_HANDOFF.md документи

### Phase progress:
- Phase A1 ~75% (продъжаваме products bugs + inventory)
- DESIGN-KIT v1.1 — stable, използва се
- STRESS система — документирана, не deployed cron-и

---

## ⚠️ ПОУКИ ОТ ДНЕС (за избягване утре)

1. **Не доверявай се на handoff claim-ове без browser test.** Sprint B каза 8/8 done — реално 6/8.
2. **Mobile chat parser слепя bash команди** (bashcat, bashcd). Винаги нови redove между команди.
3. **Heredoc + Python в bash се чупи.** Записвай Python в отделен файл, изпълни отделно.
4. **DigitalOcean web console paste limitations.** Nano с paste от browser не работи стабилно. Python script е по-надежден.
5. **Mirror cron auto-sync — 7 incidents днес.** Не игнорирай pattern; добави в post-beta priority.
6. **Verify chronologically.** "Дни до beta" — днес 01.05, beta 14.05 → 13 дни (не 12).

---

## 🔁 СЕСИЯ COORDINATION ЗА УТРЕ

При множество паралелни Code Code сесии:
- Disjoint files (verify преди започване)
- Selective `git add <files>` (никога `-A`)
- Коректни commit messages с S92.[MODULE].[TASK] формат
- Push веднага след commit (не чакай auto-sync да hijack-не)

Препоръчителен ред (sequential):
1. Browser test (Тихол)
2. Решения от browser test (architecture decisions)
3. Inventory module Code Code (1 сесия)
4. S92.PRODUCTS.B_FIX Code Code (паралелно ако disjoint)
5. Util tasks (STATE update, REWORK QUEUE entries) — sequential

---

ПРОТОКОЛ ИЗПЪЛНЕН: Минах разговора три пъти. Списъкът е покрит изцяло.
