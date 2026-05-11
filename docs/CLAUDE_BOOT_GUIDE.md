# CLAUDE BOOT GUIDE — за нови чат сесии

**Когато:** Започваш нова Claude (Opus) chat сесия за RunMyStore работа.
**Цел:** Да работиш със същата ефективност като S140 (11.05.2026).

---

## STEP 1 — Clone repo в sandbox (1 минута)

Paste това като първа инструкция в нов чат:

```
Клонирай ми repo-то локално:

cd /home/claude
git clone https://tiholenev-tech:<PAT_TOKEN>@github.com/tiholenev-tech/runmystore.git
cd runmystore
git config user.email "claude@anthropic.com"
git config user.name "Claude (Opus)"

После прочети тези документи и ми разкажи кратко:
- docs/S140_FINALIZATION.md (workflow + Universal UI Laws)
- docs/KNOWN_BUGS.md (нерешени проблеми)
- COMPASS_APPEND_S140.md (последен статус)
- docs/COMPETITOR_INSIGHTS_TRADEMASTER.md (features за бъдещи модули)
```

**PAT token:** виж `/etc/runmystore/.github_token` на droplet-а, или генерирай нов от GitHub Settings → Developer settings → Fine-grained PATs → Generate new (runmystore repo, Contents Read+Write, 30 дни).

---

## STEP 2 — Context priming (1 минута)

Paste и това (като 2-ра инструкция):

```
КЛЮЧОВ КОНТЕКСТ (винаги помни):
- Тих НЕ е developer. Само paste-ва команди в droplet SSH.
- Ти имаш sandbox repo в /home/claude/runmystore + git push към GitHub.
- Droplet (164.90.217.120) достъпен САМО на Тих. Давай готови команди:
  ═══════════════════════════════════════════════
  cd /var/www/runmystore && git pull origin main
  ═══════════════════════════════════════════════
- Backup tag ПРЕДИ голяма промяна:
  git tag pre-feature-SXXX && git push origin pre-feature-SXXX
- Universal UI laws за всеки модул в docs/S140_FINALIZATION.md §2
- INJECT-ONLY стратегия за rewrite на големи модули (не от scratch)
- Beta target: 14-15 май 2026, ENI tenant_id=7
- Само БГ. Кратко. Без "ОК?", "Готов ли си?", "Започвам?"
- При visual проблем → веднага fix, без излишно питане
- При UX/логически въпрос → питай първо
- Memory edits (30 entries) са активни — съдържат всички правила
```

---

## STEP 3 — Verification (30 секунди)

Тествай дали работи:

```
Потвърди че:
1. ls docs/*.md | head
2. git log --oneline -5
3. Разкажи ми накратко §2 от S140_FINALIZATION.md (Universal UI Laws)
```

Ако и трите минават → готов си.

---

## WORKFLOW PATTERN (proven, S140 — 30+ commits в 1 ден)

### Стандартен цикъл:

```
1. Тих описва проблем (БГ, кратко)
2. Claude:
   - grep / view в sandbox да намери код
   - edit file(s) в sandbox
   - git add + commit + push
3. Claude дава команда между ═══:
   ═══════════════════════════════════════════════
   cd /var/www/runmystore && git pull origin main
   ═══════════════════════════════════════════════
4. Тих paste-ва командата
5. Тих refresh-ва браузъра
6. Тих дава feedback ("работи" / "пак не" + screenshot)
7. Iterate
```

### Преди големи промени:

```
1. Backup tag команда:
   ═══════════════════════════════════════════════
   cd /var/www/runmystore && git tag pre-feature-SXXX
   git push origin pre-feature-SXXX
   ═══════════════════════════════════════════════
2. Тих paste-ва
3. Започваш работа
```

### Emergency revert:

```
═══════════════════════════════════════════════
cd /var/www/runmystore
git reset --hard pre-feature-SXXX
git push origin main --force
═══════════════════════════════════════════════
```

---

## PARALLEL WORK — Opus + Claude Code

**Кога да предложиш Claude Code:**
- Задача с 1000+ реда четене / 250+ реда писане
- Multi-file refactor
- Дълъг систематичен преглед (compliance, code audit)
- Тих е готов да отиде за 1-3 часа

**Кога Opus тук:**
- Малки/средни fix (CSS, header, single function)
- Логически решения (UX, текстове)
- Visual работа (mockup mapping)
- Документация

**Координация:**
- Opus прави backup tag ПРЕДИ да даде Code задача
- Code commit-ва + push-ва сам
- Opus прави `git pull --rebase` когато Code приключи
- При merge conflict: auto-rebase в 90% от случаите (различни file секции)
- Изрично казване на Code: "НЕ пипай зона X (преди </main>)"

**Стандартен prompt за Code:**
```
S141.<FEATURE> — <кратко описание>

СТРОГИ ПРАВИЛА:
1. ЗАБРАНЕНО да пипаш каквото и да е в <file> ПРЕДИ <marker>
2. ЗАБРАНЕНО да променяш CSS variables, цветове, gradient-и
3. ЗАБРАНЕНО да трогнеш SKILL.md, други модули
4. РАЗРЕШЕНО: ...
ТЕСТОВЕ: ...
В края: push + кажи на Тих какво си направил.
```

---

## ИЗВЪН-SCOPE НА CLAUDE (какво НЕ мога)

- ❌ Директен SSH на droplet-а (network policy блокира 164.90.217.120)
- ❌ MySQL production queries (не съм в allowed domains list-а)
- ❌ Restart Apache / php-fpm
- ❌ Виж logs в реално време
- ❌ Тествам в реален browser

**Network policy ми позволява САМО:** anthropic.com, github.com, npmjs, pypi, и още малко similar. **Droplet IP-то НЕ Е в списъка.**

**Заобикаляме чрез файлове в repo-то:**

### SQL операции — pattern (препоръчван за DB migrations)

Вместо да paste-ва дълги SQL блокове в mysql conзолата, Тих paste-ва ЕДИН ред:

```bash
# Аз пиша migrations/S141_xxx.sql в repo-то и push-вам
# Тих после:
cd /var/www/runmystore && git pull origin main
mysql --defaults-extra-file=/etc/runmystore/db.env runmystore < migrations/S141_xxx.sql
```

**Структура за migrations:**
```
/var/www/runmystore/migrations/
├── S95_products_variants.sql
├── S141_trademaster_features.sql   ← пример
└── ...
```

**Best practices в migration файла:**
```sql
-- migrations/S141_xxx.sql
-- Описание: какво прави, защо
-- Дата: 2026-MM-DD
-- Reversible: YES/NO

START TRANSACTION;

-- Backup table преди ALTER (ако е голяма промяна)
CREATE TABLE IF NOT EXISTS _backup_products_S141 AS SELECT * FROM products;

ALTER TABLE products ADD COLUMN ...;
-- (НИКОГА ADD COLUMN IF NOT EXISTS — MySQL не поддържа)

-- Verify
SELECT COUNT(*) FROM products WHERE ...;

COMMIT;
```

### Debug / sanity check команди

Аз давам готови команди, Тих paste-ва, връща output:

```bash
# Дай ми output на:
mysql --defaults-extra-file=/etc/runmystore/db.env runmystore -e "SELECT COUNT(*) FROM products WHERE tenant_id=7"

# Или (за multi-line)
cat << 'EOF' | mysql --defaults-extra-file=/etc/runmystore/db.env runmystore
SELECT id, name FROM products
WHERE tenant_id = 7
LIMIT 5;
EOF
```

---

## ВАЖНИ ФАЙЛОВЕ ЗА ВСЕКИ НОВ ЧАТ

```
docs/
├── S140_FINALIZATION.md           ← workflow + UI laws + products playbook
├── KNOWN_BUGS.md                  ← unsolved bugs (shimmer, feedback)
├── COMPETITOR_INSIGHTS_TRADEMASTER.md ← features mapping за нови модули
├── LAYOUT_SHELL_LAW.md            ← v1.1 канон за header/nav
├── PRODUCTS_DESIGN_LOGIC.md       ← за S141 products redesign
└── SIGNALS_CATALOG_v1.md          ← AI signals (от Code)

/
├── MASTER_COMPASS.md              ← живия overall документ
├── COMPASS_APPEND_S140.md         ← последен EOD summary
└── daily_logs/DAILY_LOG_YYYY-MM-DD.md ← daily chronology
```

---

## PAT TOKEN ROTATION

Token-ите изтичат след 30 дни. Когато:

```
remote: Invalid username or token. Password authentication is not supported for Git operations.
```

Генерирай нов:
1. GitHub.com → Settings → Developer settings → Personal access tokens → Fine-grained
2. Generate new token
3. Repository: runmystore only
4. Permissions: Contents Read+Write
5. Expiration: 30 days
6. Update remote: `git remote set-url origin https://tiholenev-tech:<NEW_PAT>@github.com/tiholenev-tech/runmystore.git`
7. Update този документ + `/etc/runmystore/.github_token` на droplet-а

---

**Last updated:** 2026-05-11 (S140 EOD)
**Tested workflow:** ✅ Production proven (30+ successful commits in 1 day)
