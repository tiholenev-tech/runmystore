# 🎨 CLAUDE_CODE_DESIGN_PROMPT.md — Wrapper за Code Code дизайн сесии

**Версия:** v2.0
**Дата:** 09.05.2026
**Статус:** АКТИВЕН — задължителен за всяка design rewrite

---

## 🛡 PROTECTION RULES (защита от катастрофи)

ABSOLUTE NO:
1. НЕ пипай извън "ВЛАДЕЕШ" списъка
2. НЕ пипай: partials/*, MASTER_COMPASS.md, /etc/*, ~/.ssh/*, db.env, api.env, mockups/
3. НЕ изпълнявай: rm -rf, DROP TABLE, TRUNCATE, git reset --hard, git push --force
4. НЕ ползвай: base64 за файлове, sed -i, git add -A
5. НЕ прави: APK rebuild, schema migrations извън prompt-а

ЗАДЪЛЖИТЕЛНО преди commit:
6. Backup: cp -p $FILE backups/<session>_<TS>/$FILE.bak
7. php -l $FILE → MUST pass
8. git pull --rebase origin main преди push
9. git add $FILE (selective, не -A)

ПРИ ПРОБЛЕМ:
10. Syntax fail → rollback от backup
11. Merge conflict → STOP + handoff
12. Auth/401 → STOP + handoff (не retry)
13. Scope drift > 50% → handoff с какво е готово

---

## 🔍 ANTI-REGRESSION RULES (защита на логика)

Тези пазят от ТИХА регресия — CC мълчаливо премахва функция за да "опрости".

ПРЕДИ rewrite:

14. PRE-INVENTORY — извлечи в backups/<session>_<TS>/INVENTORY_<file>_pre.md:
    - PHP функции: grep -nE "^[[:space:]]*function [a-zA-Z_]" $FILE
    - AJAX endpoints: grep -nE "(action|do)=" $FILE
    - DB queries: grep -nE "(SELECT|INSERT|UPDATE|DELETE)[[:space:]]" $FILE
    - Form names: grep -oE 'name="[^"]+"' $FILE | sort -u
    - JS handlers: grep -nE "on(click|change|submit|blur|focus|keyup)=" $FILE
    - $_SESSION / $_POST / $_GET ключове
    Commit ПРЕДИ rewrite: "S<NN>.PRE: inventory преди rewrite на $FILE"

СЛЕД rewrite, преди commit:

15. POST-INVENTORY — същото, име _post.md

16. DIFF GATE — diff pre/post:
    - Всяка PHP функция от pre присъства в post (или explicit rename в commit msg)
    - Всеки AJAX endpoint запазен
    - Всяка DB query присъства
    - Всяко form name запазено
    - Всеки JS handler намерим
    - Всеки $_SESSION/$_POST/$_GET ключ запазен
    Ако нещо липсва → STOP, не commit-вай. Върни го ИЛИ handoff.

17. LOGIC IS SACRED — mockup дефинира HTML/CSS/layout; функции/handlers/queries се копират от оригинала ДУМА ПО ДУМА.

18. ADD ONLY, NEVER REMOVE — добавяш само ако промптът изрично иска (нов бутон Y → нова функция). Старата логика остава нетронато.

19. SMOKE CHECKLIST — backups/<session>_<TS>/SMOKE_<file>.md с всеки interactive element:
    "Бутон 'X' (line N): Click → expect Y → smoke status: [ ]"
    Това е твоят отчет към Тихол; той го минава ръчно преди merge.

ПРИ СЪМНЕНИЕ:

20. STOP-IF-IN-DOUBT — функция от оригинала "не пасва" в новия дизайн → STOP, commit прогрес, питай Тихол. НИКОГА не решаваш сам че функция е остаряла.

21. SCOPE BOUNDARY — забелязваш bug в друг файл → BACKLOG_<session>.md, не пипай.

---

## 📋 PROMPT TEMPLATE

PHASE 0: pwd && cd /var/www/runmystore && pwd && git rev-parse HEAD

ROLE: [конкретно задание]
HARD LIMIT: 4-6 часа
BRANCH: s<NN>-<scope>-<descriptor>

ВЛАДЕЕШ:
- [конкретен файл/файлове]
- backups/<session>_*/

ЧЕТИ ПЪРВО (github main):
- mockups/<file>.html (1:1 ground truth)
- DESIGN_SYSTEM_v4.0_BICHROMATIC.md
- design-kit/check-compliance.sh

NEW BEHAVIOR (ако промптът изрично иска — иначе SKIP)

EXIT CRITERIA:
- PRE-INVENTORY committed
- Rewrite 1:1 по mockup
- POST-INVENTORY committed
- DIFF без липсващи elements
- SMOKE_<file>.md generated
- design-kit/check-compliance.sh PASS
- php -l PASS
- Commit на branch (НЕ main)

---

## 🔄 SEQUENTIAL PILOT (Standing Rule #33)

1 файл на сесия. Преди следващ rewrite — текущият merged + ENI smoke passed.

Ред:
1. life-board.php → P10 (pilot — най-малък)
2. chat.php → P11 (среден)
3. ai-studio.php + 5 partials → P8/P8b/P8c (сложен)
4. products.php scrHome → P3 (среден)
5. products.php "Добави артикул" → P13 (5 sub-сесии S136a-e)

---

## ⚠️ STOP CONDITIONS

1. DIFF gate fail
2. design-kit/check-compliance.sh fail
3. Mockup съдържа логика която не съществува в оригинала
4. Намериш _wizPriceParse / voice STT в файла → ZERO TOUCH
5. Промптът има "ВЛАДЕЕШ" но трябва да пипнеш друг файл
6. Mockup противоречи на DESIGN_SYSTEM
7. PHP syntax error след rewrite — rollback, не "почисти"
8. Backup липсва — направи го преди да продължиш

---

END v2.0
