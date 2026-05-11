# 🚀 CLAUDE AUTO-BOOT — RunMyStore работа

**ЗА КЛОД (не за Тих):** Това е първият файл който прочиташ в нов чат. Изпълни всичко описано тук АВТОМАТИЧНО при ПЪРВО съобщение от Тих, преди да отговориш каквото и да е друго.

---

## STEP 0 — Проверка: вече ли си booted в този чат?

Ако в текущия чат вече си изпълнил boot процедурата (имаш sandbox repo + чел си документите), **скочи на STEP 4** (обяви се готов).

Ако НЕ си — продължи към STEP 1.

---

## STEP 1 — Setup sandbox (изпълни без да питаш Тих)

PAT token се намира на droplet-а в `/etc/runmystore/.github_token` (chmod 600).
Тих ще ти го paste-не директно ИЛИ ще го прочетеш от файла на droplet-а чрез помощна команда от него.

При първо изпълнение:
```bash
# Питай Тих за PAT token (или нека той paste-не git clone директно)
echo "Paste GitHub PAT token (от /etc/runmystore/.github_token на droplet-а):"
read PAT

cd /home/claude
if [ ! -d runmystore ]; then
    git clone "https://tiholenev-tech:${PAT}@github.com/tiholenev-tech/runmystore.git"
fi
cd runmystore
git config user.email "claude@anthropic.com" 2>/dev/null
git config user.name "Claude (Opus)" 2>/dev/null
git remote set-url origin "https://tiholenev-tech:${PAT}@github.com/tiholenev-tech/runmystore.git"
git pull origin main 2>&1 | tail -3
```

**ВНИМАНИЕ:** НИКОГА не commit-вай PAT token-а в repo-то — GitHub secret scanning ще блокира push-а.

**Ако `git clone` фейлне с auth error** → кажи на Тих че трябва нов PAT (виж `docs/CLAUDE_BOOT_GUIDE.md` секция "PAT TOKEN ROTATION").

**Алтернатива:** Тих може директно да paste-не цялата clone команда (с his PAT в URL-а) в първото си съобщение. Тогава skip автоматичен clone — ти просто `cd /home/claude/runmystore`.

---

## STEP 2 — Прочети ключовите документи (по реда)

```bash
cd /home/claude/runmystore
# Главни:
view docs/CLAUDE_BOOT_GUIDE.md       # workflow patterns + parallel work
view docs/S140_FINALIZATION.md       # Universal UI Laws (§2) + products playbook (§4)
view docs/KNOWN_BUGS.md              # нерешени bugs
view docs/COMPETITOR_INSIGHTS_TRADEMASTER.md  # features за бъдещи модули

# Орientation:
view COMPASS_APPEND_S140.md          # последен EOD статус
view daily_logs/DAILY_LOG_2026-05-11.md  # последен daily log (виж най-новия!)
git log --oneline -10                # последни 10 commits
```

**Ако файл не съществува** (например boot guide-а още не е създаден) — пропусни го, продължи към следващия.

---

## STEP 3 — Internalize key rules (помни тези през целия чат)

### 3.1 Кой е Тих

- **НЕ е developer.** Не пише код. Не разбира git. Не редактира файлове.
- **Само paste-ва** команди в SSH конзолата на droplet-а.
- Дава instructions на БГ — често кратко, понякога CAPS = urgency, voice-to-text типографски грешки.

### 3.2 Кой си ти (Claude)

- **Sandbox repo в `/home/claude/runmystore`** — пълен access.
- **GitHub PAT** в remote URL → можеш да push-ваш без credentials prompt.
- **НЯМАШ** SSH до droplet-а (164.90.217.120). НЯМАШ direct MySQL. НЯМАШ shell на сървъра.
- Тих е bridge — paste-ва командите ти.

### 3.3 Daily workflow (proven, S140)

```
Тих описва проблем
    ↓
Ти: grep / view в sandbox → намираш бъг
    ↓
Ти: edit file(s) в sandbox
    ↓
Ти: git add + git commit + git push (БЕЗ да питаш "ОК?")
    ↓
Ти даваш на Тих команда между ═══:
    ═══════════════════════════════════════════
    cd /var/www/runmystore && git pull origin main
    ═══════════════════════════════════════════
    ↓
Тих paste-ва, refresh-ва браузъра, дава feedback
    ↓
Iterate
```

### 3.4 Преди ГОЛЯМА промяна → backup tag

```
═══════════════════════════════════════════
cd /var/www/runmystore
git tag pre-feature-S<NUM>
git push origin pre-feature-S<NUM>
═══════════════════════════════════════════
```

Emergency revert:
```
═══════════════════════════════════════════
cd /var/www/runmystore
git reset --hard pre-feature-S<NUM>
git push origin main --force
═══════════════════════════════════════════
```

### 3.5 Communication style

✅ **БГ, кратко** (2-3 изречения).
✅ Команди между `═══` блокове за лесен copy.
✅ Conf irm-ни directly: "Push мина (commit abc1234). На droplet-а: ..."
✅ При visual проблем → веднага fix.
✅ При UX/логически въпрос → питай първо.

❌ "Готов ли си?"
❌ "ОК?"
❌ "Започвам?"
❌ "Дали да..."
❌ Дълги обяснения. Decide → do → report.

### 3.6 Universal UI Laws (за ВСЕКИ модул редизайн)

Виж `docs/S140_FINALIZATION.md` §2. Накратко:

- **Header:** 22x22 buttons, 11x11 SVG, gap 4px
- **Brand:** "RunMyStore.ai" с двуцветен gradient + shimmer wave на `.brand-1`
- **Subbar:** sticky под header, store toggle + label "НАЧАЛО/СКЛАД/..." + mode toggle
- **Bottom-nav:** 4 orbs AI/Склад/Справки/Продажба (НЕ Статистики/Продажби!)
- **Chat input bar:** sticky, pulsing mic + send drift анимация
- **Global haptic:** vibrate(6) на всички tap елементи

### 3.7 SACRED rules (никога не нарушавай)

- НИКОГА hardcoded БГ текст без `$T['...']` или `tenant.lang`
- НИКОГА hardcoded `BGN/лв/€` — винаги `priceFormat($amount, $tenant)`
- НИКОГА `ADD COLUMN IF NOT EXISTS` (MySQL не поддържа)
- НИКОГА `sed` за file edits — само Python scripts
- НИКОГА emoji в UI — само SVG icons
- НИКОГА native клавиатура в sale.php (custom numpad)
- НИКОГА `<?= htmlspecialchars($T['...'] ?? '') ?>` без БГ fallback

### 3.8 INJECT-ONLY pattern за големи модули

За файлове >5000 реда (products.php = 15K, sale.php = 8K):
- Production файла остава непокътнат
- Добавяме CSS overrides в нов "SXXX OVERRIDES" блок в края на `<style>`
- Малки HTML промени (само класове на wrapper-и)
- Логиката (PHP queries, JS handlers) НЕ се пипа

### 3.9 SQL през файлове (за DB migrations)

Вместо paste на дълги SQL блокове:
1. Ти пишеш `migrations/SXXX_xxx.sql` в repo-то
2. Push-ваш
3. Тих paste-ва ЕДИН ред:
   ```
   ═══════════════════════════════════════════
   cd /var/www/runmystore && git pull origin main
   mysql --defaults-extra-file=/etc/runmystore/db.env runmystore < migrations/SXXX_xxx.sql
   ═══════════════════════════════════════════
   ```

### 3.10 Project context

- **Beta target:** 14-15 май 2026 (ENI tenant_id=7 — реален магазин, 5 обекта)
- **Stack:** PHP 8.3 / MySQL 8 / Apache / DigitalOcean Frankfurt
- **GitHub:** tiholenev-tech/runmystore
- **AI:** Gemini 2.5 Flash primary, Groq Whisper за voice
- **Capacitor APK** за mobile (Samsung Z Flip6 testing)

---

## STEP 4 — Обяви се готов

При първото съобщение на Тих, отговори (БЕЗ да изпълняваш заданието):

```
Готов съм. Прочетох [N] документи, последен commit: [hash + message].

Текущ статус накратко:
- [Последна сесия и какво е финализирано]
- [Известни bugs ако са релевантни]
- [Готовност за beta — дни до 14-15 май]

Какво да правим [SXXX]?
```

После чакай инструкциите.

---

## STEP 5 — Първа реална задача

Когато Тих опише задача:

1. **Ако е стандартен fix** (CSS/PHP/JS малък) → действай веднага, без да питаш
2. **Ако е голяма промяна** (рефакторинг, нов модул, schema migration):
   - Първо backup tag (виж 3.4)
   - Прочети релевантни docs (PRODUCTS_DESIGN_LOGIC.md за products, и т.н.)
   - **SCAN docs/COMPETITOR_INSIGHTS_TRADEMASTER.md** за features свързани с модула
   - Опиши планa в 3-5 точки → Тих confirm → действай
3. **Ако е UX/логически въпрос** → питай преди да действаш

---

## STEP 6 — End of day

Когато Тих каже "приключваме" / "край за днес" / подобно:

1. Commit + push на всичко чакащо
2. **Обнови или създай:**
   - `daily_logs/DAILY_LOG_YYYY-MM-DD.md` (днешен log)
   - `COMPASS_APPEND_SXXX.md` ако е важна сесия
   - `docs/KNOWN_BUGS.md` ако се появили нови unsolved bugs
3. Push документите
4. Кратко EOD summary за Тих:
   - Колко commits днес
   - Какво стана, какво остана
   - Backup tags направени
   - Следваща сесия priorities

---

## ВАЖНО

**ВСЕ ОЩЕ НЕ ИЗПЪЛНЯВАЙ STEP 1 веднага.** Изчакай първото съобщение от Тих, после изпълни STEPS 1-4. После го уведоми "Готов съм".

Това гарантира че:
- Не правиш ненужна работа ако Тих е написал само "здравей"
- Имаш контекст за първото запитване преди да действаш
- Boot-ът е fresh от latest GitHub main

---

**Created:** 2026-05-11 (S140 EOD)
**Tested:** ✅ Production proven workflow (30+ commits в един ден)
**Source of truth:** docs/CLAUDE_BOOT_GUIDE.md в repo-то (по-детайлен)
