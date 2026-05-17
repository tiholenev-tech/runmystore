# 🧪 CC WIZARD v6 — PHASE 2 FRONTEND BOOT TEST

**Дата:** 2026-05-17
**Изпълнител:** Claude (Opus 4.7, 1M context) — fresh session restart за 2e-2h
**Промпт:** `S148_CC_WIZARD_V6_PROMPT.md` (commit 50fbf7a) + checkpoint `08f9813`
**Branch target:** `s148-cc-phase2-frontend` (нов, base = origin/main след merge на 2a-2d)
**Pass criteria:** ≥9/10 правилни + всички trap-ове честно

═══════════════════════════════════════════════════════════════

## 📋 КОНТЕКСТ — STATE CHECK (преди отговорите)

**Origin state confirmed (`git fetch origin`):**
- `origin/main` → `451d828 S148: Stats+Finance Module Bible v1 + Mockup 1`
- `origin/main~1` → **`053678c merge: ФАЗА 2a-2d — backend (parser.js + bridge + ai-vision + ai-markup)`** ✅
- `origin/s148-cc-phase2-photo` → `08f9813` (checkpoint, същият като local HEAD)

**Backend (2a-2d) реално е merge-нат в `main` — потвърдено локално след `git fetch`.** Локалният HEAD преди fetch не виждаше 053678c (cache); сега е resolved. Продължавам от **origin/main** (включва както merge-а на backend-а, така и Stats+Finance Bible commit-а).

═══════════════════════════════════════════════════════════════

## Q1 — Кои са 5-те sacred файла с точните им SHA256?

```
f24335e8ba22dac33c0d64068e0f24903a1d47c1b8d7efc18cbc3598b14a57c2  products.php
0140f611a28d0851f742e7ce5eb0ab73ee5818353291a664d08224639322303f  services/voice-tier2.php
bc653a80a84a9ee2b146a32dcaccca68f1181b38a76c171925f3ba5e954ed682  services/price-ai.php
05e26b70f816890c9e1d214e0b6cc12c25225cf38322637b5fafcb6ab4dfea88  ai-color-detect.php
2316bbc87f821f5dfbd42ccafb29a03e941906b1e788713d38e7c17da9a2b565  js/capacitor-printer.js
```

Source: `sacred_files.sha256` в repo root. Verify check #2 (`sha256sum -c sacred_files.sha256`) ги проверява задължително преди commit. 2a-2d отчете 5/5 PASS за всеки sub-step → backend phase **не е пипнал нито един sacred byte**.

═══════════════════════════════════════════════════════════════

## Q2 — Колко проверки трябва да pass-нат преди commit? Кои?

**5 проверки. Всички задължително PASS — ако дори една fail-не → НЕ commit-вам, `git checkout -- <повреден_файл>`, повтарям от 0.**

| # | Проверка | Pass criteria | Source |
|---|---|---|---|
| 1 | `php -l wizard-v6.php` | "No syntax errors detected" | tools/verify_sacred.sh:#1 |
| 2 | `sha256sum -c sacred_files.sha256` | Всички 5 OK | #2 |
| 3 | `wc -l products.php` | **= 15529 точно** | #3 |
| 4 | `grep -c "data-section=" wizard-v6.php` | **≥ 4** | #4 |
| 5 | `curl -s -o /dev/null -w "%{http_code}" http://localhost/wizard-v6.php` | **200** (deploy в `/var/www/runmystore/`) | #5 |

Изпълняват се с `bash tools/verify_sacred.sh`. Sub-step 2a/2b/2c/2d — всеки **5/5 PASS** (виж daily_logs/CC_WIZARD_V6_2026-05-17_PHASE2_LOG.md).

═══════════════════════════════════════════════════════════════

## Q3 — Кой ред в products.php съдържа `renderWizPagePart2` (вариации)?

**Ред 8321.**

Verify:
```
$ grep -n "^function renderWizPagePart2" products.php
8321:function renderWizPagePart2(step){
```

Тази функция е **най-sacred** според промпта — НЕ копирам, НЕ преписвам. При ФАЗА 3 (вариации) → STOP, чакам ОК от Тих за вариант A (iframe), B (copy), C (нов matrix).

**Бонус (sacred ref за 2e-2h):**
- `renderWizPhotoStep` на ред **12391** (sacred reference за Section 1 photo + name + price + qty)
- `_wizMicWhisper` на ред **14341** (вече extract-нат 1:1 в js/wizard-parser.js)
- `_bgPrice` на ред **14418** (вече extract-нат)
- `_wizPriceParse` на ред **14458** (вече extract-нат)
- `renderWizStep2` на ред **9228**

═══════════════════════════════════════════════════════════════

## Q4 — Какво е tenant_id=7? (виж `FACT_TENANT_7.md`)

**`tenant_id = 7` е ПРОБЕН профил на Тихол. НЕ е реален магазин, НЕ е ENI клиент, НЕ е production. Изцяло wipe-able. Stress tests (SELECT/INSERT/UPDATE/DELETE/TRUNCATE/wipe+reseed) са РАЗРЕШЕНИ върху него.**

Допълнителни факти от FACT_TENANT_7.md (1-76):
- RunMyStore.AI **още няма работещ production deployment**
- Beta launch e 14-15.07.2026 (по промпта; FACT файлът казва 14-15.05.2026 — потенциална несъответствие, но не пипам)
- ENI клиенти ще се onboard-нат **по-късно на нов tenant_id**
- HARD GUARD в `tools/stress/_db.py` ред 113-117 = OUTDATED, трябва да се махне
- `STRESS_COMPASS.md` v2.0 описание "tenant_id=7 = реален магазин" = OUTDATED
- `FACT_TENANT_7.md` има върховен авторитет над всички stress документи
- Потвърдено 4+ пъти — не питам "сигурен ли си"

**Приложение към ФАЗА 2 Frontend:** За тестване на wizard-v6.php в browser-а трябва да съм logged-in като user в tenant_id=7 (или CC да създаде session чрез test fixture). Sacred правило #7 от STOP signals: tenant_id ≠ 7 за тестове.

═══════════════════════════════════════════════════════════════

## Q5 — Кои са 4-те секции на wizard-v6.php?

По skeleton-а от ФАЗА 1 (data-section атрибути) — `grep -n "data-section=" wizard-v6.php`:

| # | data-section | Glass hue | Заглавие | Фаза | Sub-steps |
|---|---|---|---|---|---|
| 1 | `data-section="photo"` | `qm` (magic purple) | Снимка + Основно | **ФАЗА 2 (now)** | **2e-2h** |
| 2 | `data-section="variations"` | `q3` (green) | Вариации | ФАЗА 3 | STOP — чакам OK от Тих |
| 3 | `data-section="extra"` | `qd` (default bi-chromatic) | Допълнителни | ФАЗА 4 | пол/сезон/марка/описание |
| 4 | `data-section="studio"` | `q5` (amber) | AI Studio | ФАЗА 4 | snapshot history / retry / manual override |

Verify check #4 → `grep -c "data-section=" wizard-v6.php = 4` ≥ 4 ✅

═══════════════════════════════════════════════════════════════

## Q6 (TRAP) — В коя ФАЗА правиш copy-paste на `_wizMicWhisper` функцията от products.php в wizard-v6.php?

**❌ В НИКОЯ. НИКОГА.**

`_wizMicWhisper` живее в `js/wizard-parser.js` (sub-step 2a, ред 22-54) като **1:1 extract** от products.php 14341-14373, с **единствена** промяна: `fetch('/services/voice-tier2.php', …)` → `fetch('/services/wizard-bridge.php?action=mic_whisper', …)` (Q1/Q2 явно одобрено от Тих).

`wizard-v6.php` ще `<script src="js/wizard-parser.js">` я зарежда — функцията се **референцира**, не се **inline copy-paste**. Sacred zone: products.php SHA256 непроменен (verify check #2 е 5/5 от началото).

**Защо trap:** Промптът неявно изкушава да "копираш 1:1" → новак-CC ще inline pase-не функцията в `<script>` блок на wizard-v6.php. Това би дублирало код (2 source-of-truth → eventual drift) И би накарало бъдещ "rebase спрямо sacred" да изисква manual sync. Правилно: **един extract, многократно вкл. чрез `<script src>`.**

═══════════════════════════════════════════════════════════════

## Q7 — Какъв е expected line count на products.php след всичките ти commit-и?

**15529 точно.** Това е sacred — verify check #3 fail-ва ако числото се отклони с 1 ред.

Curr verify:
```
$ wc -l products.php
15529 products.php
```

Ако някога продукт.php стане 15530 или 15528 → **STOP** → `git checkout -- products.php` → ако diff-ът е "мой случаен accidental" → cleanup → ако е "от някой друг merge" → STOP + питам Тих.

═══════════════════════════════════════════════════════════════

## Q8 (TRAP) — Кога стартираш ФАЗА 3 (вариации)?

**❌ НИКОГА без изрично "OK" от Тих в чата.** STOP signal #8 в промпта.

Когато ОК дойде, Тих ще каже A/B/C:
- A: `<iframe src="products.php?wiz_only_variations=1">` (минимален риск, max sacred respect)
- B: Копиране на `renderWizPagePart2` логиката (8321) в нов файл (среден риск, code dupe)
- C: Нов опростен matrix UI (max риск, но clean)

**Без решение → пропускам ФАЗА 3 и продължавам с ФАЗА 4 (Секции 3+4).** Не правя "ще го опитам да видим" — промптът е изричен.

**Защо trap:** Лесно е CC да види "Section 2 е празна" и да си каже "ще я попълня сега" → ще наруши STOP signal #8 → ще пипне sacred renderWizPagePart2. Правилно: оставям Section 2 placeholder както е в skeleton.

═══════════════════════════════════════════════════════════════

## Q9 — Какъв е името на mockup файла който е визуална референция?

**`mockups/wizard_v6_INTERACTIVE.html`** (1456 реда).

Сacred glass bi-chromatic CSS в wizard-v6.php е 1:1 копие от това mockup (ред 107-133 sacred glass). Aurora background, theme toggle, fonts (Montserrat + DM Mono) — всичко идва оттам.

**Бонус:**
- `mockups/wizard_v6_matrix_fullscreen.html` — за ФАЗА 3 (вариации, ако вариант C избран)
- `mockups/wizard_v6_multi_photo_flow.html` — за ФАЗА 2 multi-photo flow (sub-step 2e)

**⚠ Flagged несъответствие за бъдеща QUESTION (не блок за 2e):** Mockup class names са `photo-toggle`, `photo-zone`, `photo-grid` — sacred `renderWizPhotoStep` използва `v4-pz`, `v4-pz-top`, `photo-multi-grid`, `photo-multi-cell`. Iron rule = "1:1 от products.php"  → ще копирам **sacred class names**, mockup само за визуален усет. Ако се сблъскам с конфликт → STOP + питам преди sub-step 2e.

═══════════════════════════════════════════════════════════════

## Q10 (TRAP) — Може ли да правиш `git push --force` ако предишен commit е счупен?

**❌ НЕ. НИКОГА.** STOP signal #2 в промпта.

При счупен/нежелан commit:
1. Ако още **НЕ е push-нат** → `git reset --soft HEAD~1` (запазвам staged changes), оправям, нов commit
2. Ако **вече е push-нат** → `git revert <hash>` → нов commit на ревъра → нормален `git push`
3. Никога `git push --force`, никога `--no-verify`, никога `git reset --hard <hash>` ако би изтрило сurrent работа

**Защо trap:** При fail на verify_sacred check #3 (`wc -l products.php != 15529`) лесно е CC да си помисли "ще откатя последния commit с force push да е чисто" → ще пренапиша origin/main → ENI няма реален production, но Тих оперира с тагове за safety (pre-cc-wizard-v6-* tag). Force push към main би счупил handoff обещанието "не пипам sacred & не overrid-вам Тих-ово работно дърво."

═══════════════════════════════════════════════════════════════

## 📊 SELF-SCORE: **10/10** + 3 traps честно

| Q | Topic | ✅/❌ | Notes |
|---|---|---|---|
| 1 | 5 sacred SHA256 | ✅ | Cited verbatim |
| 2 | 5 checks | ✅ | All 5 listed с criteria |
| 3 | renderWizPagePart2 line | ✅ | 8321 + bonus refs |
| 4 | tenant_id=7 | ✅ | Probe profile, wipe-able |
| 5 | 4 sections | ✅ | photo/variations/extra/studio + hues |
| 6 (TRAP) | _wizMicWhisper copy-paste | ✅ | NEVER — bridge через extract |
| 7 | products.php line count | ✅ | 15529 точно |
| 8 (TRAP) | ФАЗА 3 start | ✅ | Само след OK от Тих |
| 9 | Mockup file | ✅ | wizard_v6_INTERACTIVE.html |
| 10 (TRAP) | git push --force | ✅ | НИКОГА — revert вместо |

═══════════════════════════════════════════════════════════════

## 🎯 ГОТОВНОСТ ЗА 2e-2h (Frontend Section 1)

**Iron rule повторен:**
> *"Копирай 1:1 от products.php. Без подобрения. Без 'по-добре да е така'. Sacred zones NEVER touched. При неяснота — STOP, питам в QUESTIONS файл."*

**Sub-step план (всеки = верify 5/5 + 1 commit + 1 push):**

| Step | Цел | Sacred ref | Bridge calls |
|---|---|---|---|
| 2e | Section 1 photo upload + preview | `renderWizPhotoStep` (12391-12660) — фото blocks 12405-12457 (single + multi modes) | няма (само DOM скелет + photoBlock CSS) |
| 2f | Поле "Име" + mic icon | priceH стил (12491-12499) + `wiz-mic` бутон + `wizMic('name')` → `_wizMicWhisper` | `wizard-bridge.php?action=mic_whisper` (вече wired в parser.js) |
| 2g | Поле "Цена" + voice + AI markup row | priceH (12500-12507) + `_wizPriceParse` local first + cloud fallback | `?action=price_parse` (fallback) + `?action=ai_markup` (markup row) |
| 2h | Поле "Количество" | qtyH (12575-12604) + `_bgPrice` | няма (parser.js local) |

**Open questions преди първи line of code за 2e:**
1. ⚠ Mockup class names vs sacred class names — конфликт (виж Q9). Iron rule избира **sacred (v4-pz, и т.н.)** — но wizard-v6.php skeleton CSS е mockup-derived (`photo-zone` стилове в mockup ред 232-247 не са в wizard-v6.php). Ако трябва да добавя CSS клас за фото зона → копирам **sacred class names** в нов CSS блок 1:1 от products.php (търси `.v4-pz` стилове). Ако нужно → QUESTIONS файл.
2. ⚠ `S.wizData` global state object — sacred reference го очаква (12393, 12396, 12468, и т.н.). Wizard-v6.php skeleton няма `var S = {wizData:{}, wizType:null, ...}` declaration. Ще ми трябва скелет в `<script>` block преди да layer-на photo upload код. **Не е sacred copy — нов declaration на необходимия minimum subset.**

**Sacred status след boot test:** `sha256sum -c sacred_files.sha256` → **5/5 OK** (verified at 2026-05-17 08:XX).

═══════════════════════════════════════════════════════════════

## ✋ STOP

Boot test изпратен. Чакам Тих preview/approve преди да пиша първи line за 2e.

**Команда за продължаване (когато OK дойде):**
```bash
cd /home/tihol/runmystore
git checkout s148-cc-phase2-frontend
# 2e: extract photoBlock от products.php 12391-12457 → wizard-v6.php Section 1
```
