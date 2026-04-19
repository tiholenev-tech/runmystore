# SESSION 78 HANDOFF
## RunMyStore.ai · 19.04.2026 · Claude Opus 4.7 (1M context)

---

## S78 обобщение

S78 постави фундамента за пълното завършване на products.php. Пусната и верифицирана е цялата S77 DB миграция, поправени са P0 бъговете #7 и частично #6, създаден е skeleton-ът на compute-insights.php с 19 продуктови функции по 6-те фундаментални въпроса, и е оправена навигацията на wizard step 4 според опция В (избор на Пешо, не натрапване).

---

## Готово

**DB миграция** — 3 нови таблици (supplier_orders, supplier_order_items, supplier_order_events), 3 нови колони в ai_insights (fundamental_question ENUM, product_id, supplier_id), 2 нови колони в lost_demand (suggested_supplier_id, resolved_order_id). Backup: `/root/backup_s78_20260419_1829.sql` (1.37 MB). Верификация: SHOW TABLES и DESCRIBE потвърдени, 37 записа в ai_insights остават непокътнати.

**Бъг #7 — sold_30d child aggregation** — correlated subquery в listProducts смени `si99.product_id=p.id` с JOIN към products + `(cp2.id=p.id OR cp2.parent_id=p.id)`, така че parent артикулът сумира и собствените си продажби и тези на child-варианти. Първият опит (CASE WHEN EXISTS children THEN child ELSE parent) беше регресивен — refine-нат в commit `ba4ff1d`. Верификация чрез SQL срещу референтна заявка: 5 от 5 топ артикула съвпадат 1:1.

**compute-insights.php skeleton** — 19 празни `pf*()` функции, групирани по fundamental_question (loss 3, loss_cause 4, gain 2, gain_cause 5, order 2, anti_order 3). Wrapper-ът `computeProductInsights()` е закачен в `computeAllInsights()`. upsertInsight разширен с новите колони. AJAX endpoint `ajax=compute_insights` добавен в products.php. Commit `20736b2`.

**Wizard step 4 footer — опция В** — default wizard-ът слага 2 axes (Вариация 1 + Вариация 2). Когато текущият axis има стойности, а другият е празен, footer-ът показва трите бутона паралелно: "Колко бр.?", "Вариация 2" и "Запиши". Пешо сам избира кое да направи. Когато всички axes са попълнени, средният бутон изчезва и остават Колко бр. + Запиши. Font size и SVG dimensions намалени за да се съберат три бутона на тесни екрани. Commit `c65cdef`.

---

## За S79 (не готово, чака UI тест)

**Бъг #5 — AI Studio `_hasPhoto`** — анализът на кода показа че `S.wizData._hasPhoto=true` вече се сетва правилно на ред 6554 (photoInput change handler, вътре в FileReader onload). Тихол не потвърди в UI дали симптомът все още се проявява. Ако да — значи има друг code path, който не сме видели, и трябва повторен разглед в S79 със сценарий от реалния UI.

**Бъг #6 — renderWizard нулира бройки** — първият fix (commit `18efa48`) защити print qty на step 6 (Печат) чрез wizCollectData/renderWizard hook, ограничен с `S.wizStep===6`. За сценарий с 2 вариации (Размер + Цвят) — където бройките се въвеждат на step 5 матрица — fix-ът не покрива нулирането при re-render. Чака реален UI тест с matrix + 2 axes преди да се реши дали има отделен бъг.

---

## Закони от S78

**1. Commit ≠ готово.** Готово е когато е тестван. Push не затваря задачата — потвърждение затваря задачата.

**2. Frontend → UI тест от Тихол преди done.** Claude не може сам да кликне през UI. Всяка JS/HTML промяна чака ръчно потвърждение. Task остава in_progress докато Тихол каже "работи".

**3. Backend → SQL query преди done.** Всяка промяна в SQL заявка или DB схема се тества с реален query срещу tenant 7/store 47, сравнено с референтна заявка. "Syntax OK" не е тест.

**4. Git log преди обвинение на регресия.** Когато се съмнява за регресия, Claude първо чете `git show <commit>` + `git blame` на релевантния код. Много "регресии" са съществуващо поведение на непроменян код от по-ранна сесия.

**5. Паралелна работа само за несвързани задачи.** DB миграция + PHP бъг fix → може паралелно. Два fix-а на същата функция → задължително последователно, с тест между тях.

---

## S79 стартов prompt (copy-paste готов)

```
Продължаваме S79 от /var/www/runmystore.

ЗАДЪЛЖИТЕЛНО ПРОЧЕТИ ПО РЕД:
1. NARACHNIK_TIHOL_v1_1.md
2. docs/BIBLE_v3_0_APPENDIX.md
3. DESIGN_SYSTEM.md
4. docs/ROADMAP.md
5. docs/PRODUCTS_DESIGN_LOGIC.md
6. docs/SESSION_78_HANDOFF.md

СТАТУС S78:
- DB миграцията верифицирана, всички 7 обекта стоят.
- Бъг #7 затворен и верифициран със SQL тест.
- compute-insights.php skeleton с 19 pf() функции, hook-нат.
- Wizard step 4 footer работи с 3 бутона (Колко бр. + Вариация 2 + Запиши).
- Pending UI тестове: Бъг #5 AI Studio _hasPhoto, Бъг #6 renderWizard бройки.

ПРАВИЛА (от NARACHNIK + S78 закони):
- Тихол НЕ е developer. Само български. Кратко. Без "може би".
- ТЕХНИЧЕСКИ решения (скриптове, имена, git flow, backup) = ти сам.
- ЛОГИЧЕСКИ/UX решения = ЗАДЪЛЖИТЕЛНО питай Тихол.
- sed забранен. Python скриптове в /tmp/s79_*.py.
- Backup ПРЕДИ всяка DB промяна: mysqldump → /root/backup_s79_YYYYMMDD_HHMM.sql.
- След успешен fix → git add + commit + push АВТОМАТИЧНО, без питане.
- Commit ≠ готово. Готово = тестван (UI от Тихол или SQL от теб).
- Frontend промяна остава pending докато Тихол потвърди в UI.
- Backend промяна се тества с реален SQL срещу tenant 7/store 47.
- Git log преди обвинение на регресия.
- Пълен код винаги, никога частичен.
- Макс 2 команди наведнъж, чакай резултат.
- Никога "Gemini" в UI — винаги "AI".
- i18n: никога hardcoded BG; винаги tenant.lang + priceFormat.

ПЪРВА ЗАДАЧА: UI тестове на pending от S78.

1. Бъг #5 AI Studio — Тихол тества в UI:
   - Отвори wizard, качи снимка през 'photoInput'
   - Провери дали AI Image Studio вижда _hasPhoto като true
   - Ако работи → маркирай done. Ако не → сценарий, Claude разглежда code path.

2. Бъг #6 renderWizard (сценарий 2 вариации) — Тихол тества:
   - Създай артикул с Размер + Цвят (2 axes)
   - Попълни матрица с бройки
   - Смени tab/tab, редактирай axis, re-render
   - Ако бройките се губят → Claude прави fix. Ако остават → done.

СЛЕД UI ТЕСТОВЕТЕ: започни S79 по ROADMAP — products главна rewrite (6 секции h-scroll) + напълване на 19-те pf() функции с реален SQL.

Команди за старт:
cd /var/www/runmystore && git pull origin main
git log --oneline | head -8
```

---

## Commits в S78 (по ред, всички в origin/main)

```
8fe8584  S78: Fix Bug #7 — sold_30d aggregates child variations to parent
18efa48  S78: Fix Bug #6 — persist print qty across re-renders on step 6
20736b2  S78: compute-insights.php skeleton (19 product functions, 6 questions)
ba4ff1d  S78: Refine Bug #7 fix — union parent + child sales instead of either/or
e8ef499  S78: Wizard step 4 — empty axis treated as non-existent (allow Save)
3f45b93  S78 docs: session handoff
c65cdef  S78: Wizard step 4 footer — show all 3 actions when other axis is empty
```

**КРАЙ НА SESSION 78 HANDOFF**
