# 🤖 S148 — ЖЕЛЕЗЕН ПРОМПТ ЗА CLAUDE CODE

> **Задача:** Имплементирай новия wizard "Добави артикул" v6 в **НОВ файл `wizard-v6.php`**
> **Сесия:** tmux на droplet 164.90.217.120
> **Време:** 6-8 часа разпределено в 5 фази
> **Beta launch:** 14-15.07.2026

═══════════════════════════════════════════════════════════════

## 🚨 АБСОЛЮТНО ПРАВИЛО: 5× VERIFICATION ПРЕДИ ВСЕКИ COMMIT

Преди `git commit` — изпълняваш `bash tools/verify_sacred.sh`. Ако дори една проверка fail-не → **НЕ commit-ваш** → `git checkout -- <повреден_файл>` → повтаряш проверките от 0.

**5-те проверки:**

| # | Проверка | Pass criteria |
|---|---|---|
| 1 | `php -l wizard-v6.php` | "No syntax errors" |
| 2 | `sha256sum -c sacred_files.sha256` | Всички OK |
| 3 | `wc -l products.php` | **=15529 точно** |
| 4 | `grep -c "data-section=" wizard-v6.php` | **≥4** |
| 5 | `curl -s -o /dev/null -w "%{http_code}" http://localhost/wizard-v6.php` | **200** |

**Създай `tools/verify_sacred.sh`** на първа стъпка (виж §"ФАЗА 0" по-долу).

═══════════════════════════════════════════════════════════════

## 🔒 SACRED ZONES — НИКОГА НЕ ПИПАШ

### Файлове 100% забранени (SHA проверка):

```
f24335e8ba22dac33c0d64068e0f24903a1d47c1b8d7efc18cbc3598b14a57c2  products.php
0140f611a28d0851f742e7ce5eb0ab73ee5818353291a664d08224639322303f  services/voice-tier2.php
bc653a80a84a9ee2b146a32dcaccca68f1181b38a76c171925f3ba5e954ed682  services/price-ai.php
05e26b70f816890c9e1d214e0b6cc12c25225cf38322637b5fafcb6ab4dfea88  ai-color-detect.php
2316bbc87f821f5dfbd42ccafb29a03e941906b1e788713d38e7c17da9a2b565  js/capacitor-printer.js
```

**Това е по нареждане на Тих (founder). Цитат:** *"Гласово диктуване на цифрите. Разпознаването на цветове по снимка от Gemini. Печат. Гласовото въвеждане. И логиките на 'Добави артикул' като цяло и най-вече в частта с вариациите. Това не се пипа."*

### Какво ползваш от sacred файловете (read-only):

- `services/voice-tier2.php` — Whisper Groq STT endpoint (POST audio → text)
- `services/price-ai.php` — BG cyrillic price parser (call от JS)
- `ai-color-detect.php` — Gemini multi-color detection (POST image → colors)
- `js/capacitor-printer.js` — DTM-5811 Bluetooth printer (global window.printer)
- `products.php` — само reading за reference (sacred wizard funcs: `_wizMicWhisper` ред 14341, `_wizPriceParse` ред 14458, `_bgPrice` ред 14418, `renderWizPagePart2` ред 8321 = ВАРИАЦИИ, `renderWizStep2` ред 9228)

### Wizard variations логика — НЕ копираш, НЕ преписваш

Частта с вариациите в `renderWizPagePart2()` (products.php ред 8321) е **най-sacred**. Ако wizard-v6.php има нужда от вариации:
- ИЛИ ги include-ваш от products.php през iframe/AJAX call
- ИЛИ STOP — питаш Тих

═══════════════════════════════════════════════════════════════

## 📋 5 ФАЗИ

### ФАЗА 0 — Setup (30 мин)

1. `cd /var/www/runmystore && git pull origin main`
2. Backup tag: `git tag pre-cc-wizard-v6-$(date +%Y%m%d_%H%M) && git push origin --tags`
3. Създай `tools/verify_sacred.sh` (виж §END_APPENDIX_1)
4. Създай `sacred_files.sha256` (виж §END_APPENDIX_2)
5. Run `bash tools/verify_sacred.sh` — baseline трябва да pass-не
6. Commit: `chore: S148 CC bootstrap — verify script + sacred SHA baseline`

### ФАЗА 1 — Skeleton wizard-v6.php (2h)

Създай `wizard-v6.php` с:
- PHP header: auth check, tenant_id, store_id, csrf token (copy pattern от products.php top 1-50 реда)
- HTML structure: 4 акордеона по mockup `mockups/wizard_v6_INTERACTIVE.html`
- Sacred glass bi-chromatic CSS (1:1 копие от mockup редове 107-133)
- Aurora background (3 blobs)
- Празни секции — БЕЗ JS функционалност все още

```php
<?php require_once 'auth.php'; require_once 'db.php'; ?>
<!DOCTYPE html>
<html data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
  <title>Добави артикул · RunMyStore.ai</title>
  <link rel="stylesheet" href="styles/...">
  <style>
    /* SACRED GLASS — bi-chromatic от mockups/wizard_v6_INTERACTIVE.html ред 107-133 */
    /* [копирай ТОЧНО редовете] */
  </style>
</head>
<body>
  <div class="aurora"><div class="aurora-blob"></div><div class="aurora-blob"></div><div class="aurora-blob"></div></div>

  <header class="wz-header"><!-- back + title + Като предния + theme --></header>

  <main class="wz-main">
    <section data-section="photo" class="glass qm">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <h2>Снимка + Основно</h2>
      <!-- TODO Фаза 2 -->
    </section>

    <section data-section="variations" class="glass q3">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <h2>Вариации</h2>
      <!-- TODO Фаза 3 — STOP, питай Тих преди да пишеш -->
    </section>

    <section data-section="extra" class="glass qd">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <h2>Допълнителни</h2>
      <!-- TODO Фаза 4 -->
    </section>

    <section data-section="studio" class="glass q5">
      <span class="shine"></span><span class="shine shine-bottom"></span>
      <span class="glow"></span><span class="glow glow-bottom"></span>
      <h2>AI Studio</h2>
      <!-- TODO Фаза 4 -->
    </section>
  </main>

  <footer class="wz-foot"><!-- Undo / Print / CSV / Запази --></footer>

  <script src="js/capacitor-printer.js"></script>
  <script>/* TODO Фаза 2-4 */</script>
</body>
</html>
```

**Verification:** `bash tools/verify_sacred.sh` — всички 5 PASS.
**Commit:** `feat: S148 Фаза 1 — wizard-v6.php skeleton с 4 акордеона`
**Push.** Чакай Тих pull + visual review на телефона.

### ФАЗА 2 — Снимка + Основно (Секция 1) (2-3h)

Implementation на първия акордеон:
- Photo upload bridge (camera API или file input) → preview
- При снимка → POST към `services/ai-vision.php` (нов endpoint, виж ФАЗА 5)
- Поле "Име" — text input с `_wizMicWhisper` mic icon (call sacred от products.php през AJAX endpoint, **НЕ** копираш кода)
- Поле "Цена" — number input с `_bgPrice` voice parser (call sacred)
- Поле "Количество" — number input
- AI markup row (под цена): "AI предлага: €X.99" + бутон "приеми"

**КЛЮЧОВО:** Voice/parse функциите се call-ват като REST endpoint, не като copy-paste.

Създай `services/wizard-bridge.php` — мини-endpoint който forward-ва към съществуващите sacred endpoints.

**Verification + commit + push + чакай Тих.**

### ФАЗА 3 — Вариации (Секция 2) — STOP

**НЕ започвай преди изрично "OK" от Тих в чата.**

Когато OK дойде — вариантите са:
- A: iframe към products.php ?wiz_only_variations=1
- B: Копиране на `renderWizPagePart2` логиката
- C: Нов опростен matrix UI

Тих решава. Без решение → пропускаш в Фаза 4.

### ФАЗА 4 — Допълнителни + AI Studio (Секции 3+4) (2h)

- Секция 3: пол (chips), сезон (chips), марка (input + recent chips), описание (textarea + AI ✨)
- Секция 4: AI Studio integration (snapshot history, retry, manual override)

**Verification + commit + push.**

### ФАЗА 5 — AI endpoints (паралелно, 2-3h)

Нови файлове, не пипат sacred:
- `services/ai-vision.php` — POST image → Gemini 2.5 Flash → JSON (category, color, gender, season, brand, description)
- `services/ai-markup.php` — POST cost_price + category_id → retail_price + ending

Schema в `docs/AI_AUTOFILL_SOURCE_OF_TRUTH.md`. Cache: ai_snapshots table.

**Verification + commit + push.**

═══════════════════════════════════════════════════════════════

## 📞 COMMUNICATION PROTOCOL

### Кога питаш Тих (STOP сигнали):

1. ✋ Преди ФАЗА 3 (вариации) — задължително
2. ✋ Ако SHA проверка fail-не след rollback опит
3. ✋ Ако mockup-ът има нещо което противоречи на sacred
4. ✋ Ако `bash tools/verify_sacred.sh` fail-ва >3 пъти подред

### Как питаш:

Pусни кратко съобщение в `daily_logs/CC_WIZARD_V6_$(date +%Y-%m-%d)_QUESTIONS.md`:

```
## ВЪПРОС #N — [тема]
Контекст: [1-2 изречения]
Опции: A) ... B) ... C) ...
Препоръка моя: [избор + защо]
Чакам решение.
```

Push-ваш и спираш. Тих ще отговори в шеф-чата → шеф-чатът ще ти каже какво.

### EOD протокол:

В края на работен ден (или преди контекст край):
1. Final `bash tools/verify_sacred.sh` — PASS
2. Commit всичко pending
3. Push
4. Създай `daily_logs/CC_WIZARD_V6_$(date +%Y-%m-%d)_SUMMARY.md`:
   - Какво е завършено (фази)
   - Какво остава
   - Известни bugs
   - Команди за продължаване утре
5. `tmux detach` (не kill — Тих може да види history)

═══════════════════════════════════════════════════════════════

## 🛑 STOP SIGNALS — НИКОГА БЕЗ "OK"

1. ❌ Пипане на sacred files (списък по-горе)
2. ❌ `git push --force`
3. ❌ `rm -rf` каквото и да е под `/var/www/runmystore/`
4. ❌ DROP TABLE, TRUNCATE, DELETE FROM
5. ❌ Промяна в `/etc/runmystore/db.env` или `/etc/runmystore/api.env`
6. ❌ Cron install преди Тих да каже "пускай"
7. ❌ Tenant_id ≠ 7 за тестове
8. ❌ Стартиране на ФАЗА 3 (вариации) без OK

═══════════════════════════════════════════════════════════════

## 🧪 BOOT TEST — PASS = 9/10 + всички trap-ове честно

Преди да започнеш да пишеш код — отговори на тези 10 въпроса (изпрати в `daily_logs/CC_WIZARD_V6_BOOT_TEST.md`):

**Q1:** Кои са 5-те sacred файла с точните им SHA256?

**Q2:** Колко проверки трябва да pass-нат преди commit? Кои?

**Q3:** Кой ред в products.php съдържа `renderWizPagePart2` (вариации)?

**Q4:** Какво е tenant_id=7? (виж `FACT_TENANT_7.md`)

**Q5:** Кои са 4-те секции на wizard-v6.php?

**Q6 (TRAP):** В коя ФАЗА правиш copy-paste на `_wizMicWhisper` функцията от products.php в wizard-v6.php?

**Q7:** Какъв е expected line count на products.php след всичките ти commit-и?

**Q8 (TRAP):** Кога стартираш ФАЗА 3 (вариации)?

**Q9:** Какъв е името на mockup файла който е визуална референция?

**Q10 (TRAP):** Може ли да правиш `git push --force` ако предишен commit е счупен?

**При <9/10 правилни ИЛИ trap излъган → НЕ започваш работа. Прочиташ промпта пак.**

═══════════════════════════════════════════════════════════════

## 📦 END_APPENDIX_1 — `tools/verify_sacred.sh`

```bash
#!/bin/bash
set -e

echo "═══ 5× VERIFICATION ═══"

# 1
echo -n "1. PHP syntax wizard-v6.php ... "
php -l wizard-v6.php > /tmp/php_lint.out 2>&1 && echo "OK" || { cat /tmp/php_lint.out; exit 1; }

# 2
echo -n "2. Sacred SHA check ... "
sha256sum -c sacred_files.sha256 > /tmp/sha.out 2>&1 && echo "OK" || { cat /tmp/sha.out; exit 1; }

# 3
echo -n "3. products.php line count ... "
LC=$(wc -l < products.php)
[ "$LC" = "15529" ] && echo "OK ($LC)" || { echo "FAIL ($LC, expected 15529)"; exit 1; }

# 4
echo -n "4. wizard-v6.php sections ... "
SEC=$(grep -c 'data-section=' wizard-v6.php 2>/dev/null || echo 0)
[ "$SEC" -ge "4" ] && echo "OK ($SEC)" || { echo "FAIL ($SEC, expected ≥4)"; exit 1; }

# 5
echo -n "5. wizard-v6.php HTTP 200 ... "
HTTP=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/wizard-v6.php 2>/dev/null || echo "000")
[ "$HTTP" = "200" ] && echo "OK" || { echo "FAIL ($HTTP)"; exit 1; }

echo "✅ ALL 5 PASSED — safe to commit"
```

**Make executable:** `chmod +x tools/verify_sacred.sh`

## 📦 END_APPENDIX_2 — `sacred_files.sha256`

```
f24335e8ba22dac33c0d64068e0f24903a1d47c1b8d7efc18cbc3598b14a57c2  products.php
0140f611a28d0851f742e7ce5eb0ab73ee5818353291a664d08224639322303f  services/voice-tier2.php
bc653a80a84a9ee2b146a32dcaccca68f1181b38a76c171925f3ba5e954ed682  services/price-ai.php
05e26b70f816890c9e1d214e0b6cc12c25225cf38322637b5fafcb6ab4dfea88  ai-color-detect.php
2316bbc87f821f5dfbd42ccafb29a03e941906b1e788713d38e7c17da9a2b565  js/capacitor-printer.js
```

═══════════════════════════════════════════════════════════════

## 📚 READING LIST (преди да пишеш първи ред код)

1. `FACT_TENANT_7.md` (1 мин) — tenant правила
2. `mockups/wizard_v6_INTERACTIVE.html` (визуална референция, цял)
3. `mockups/wizard_v6_matrix_fullscreen.html` (за Фаза 3)
4. `mockups/wizard_v6_multi_photo_flow.html` (за Фаза 2)
5. `WIZARD_DOBAVI_ARTIKUL_v5_SPEC.md` (продуктова спец, цял)
6. `WIZARD_v6_IMPLEMENTATION_HANDOFF.md` (handoff от S147 — за context)
7. `docs/AI_AUTOFILL_SOURCE_OF_TRUTH.md` (Gemini schema)
8. `AUTO_PRICING_DESIGN_LOGIC.md` (markup formulas)
9. `DESIGN_SYSTEM_v4_0_BICHROMATIC.md` §5.4 (Sacred Glass CSS)
10. **Tази инструкция (S148_CC_WIZARD_V6_PROMPT.md) — два пъти**

═══════════════════════════════════════════════════════════════

## 🎬 ПЪРВО ДЕЙСТВИЕ

```bash
cd /var/www/runmystore
git pull origin main
cat S148_CC_WIZARD_V6_PROMPT.md   # този файл
cat FACT_TENANT_7.md
ls mockups/wizard_v6_*
```

След това:
1. Boot test → push отговорите
2. ФАЗА 0 setup
3. ФАЗА 1 skeleton
4. STOP → Тих review
5. ФАЗА 2 ...

═══════════════════════════════════════════════════════════════

**КРАЙ. Започвай само след boot test PASS.**
