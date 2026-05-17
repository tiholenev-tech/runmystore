# 🛑 CC WIZARD v6 — ВЪПРОСИ 17.05.2026

═══════════════════════════════════════════════════════════════

## ВЪПРОС #1 — verify_sacred.sh check #5 НЕ МОЖЕ да pass-не в текущата среда

### Контекст
ФАЗА 1 skeleton `wizard-v6.php` създаден в `/home/tihol/runmystore` (моят git workspace на droplet 164.90.217.120). Файлът е готов, валиден, отговаря на всичко от прoмпт-а. Verify run:

```
═══ 5× VERIFICATION ═══
1. PHP syntax wizard-v6.php ... OK
2. Sacred SHA check ... OK
3. products.php line count ... OK (15529)
4. wizard-v6.php sections ... OK (4)
5. wizard-v6.php HTTP 200 ... FAIL (404)
```

Според 5× правилото → НЕ commit. Но check #5 fail-ва по 2 несвързани с кода причини (двете environmental, не bug в wizard-v6.php).

### Двата проблема с check #5

**Проблем A — localhost routing:**
- Apache vhost-ове на droplet-а (alphabetical first = default за неpravilen Host):
  ```
  donela.conf            ← default за curl localhost (DocumentRoot /var/www/donela.bg/public_html)
  loyalty.donela.bg-*.conf
  runmystore-le-ssl.conf
  runmystore.conf        ← serve `/var/www/runmystore` ама само за Host: runmystore.ai
  ```
- `curl http://localhost/wizard-v6.php` → donela vhost → 404 (файлът не е в donela docroot)
- Това fail-ва check #5 **независимо** от това дали wizard-v6.php съществува

**Проблем B — auth gate:**
- `wizard-v6.php` правилно прави `if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }`
- Дори с правилен Host header → response е HTTP 302 → login.php (без curl `-L` не достига 200)
- Verify script няма `-L` → ще види 302 → check #5 fail
- **Не мога да махна auth gate-а** — би било security регресия (sacred-zone equivalent)

### Доказателство че кодът работи (manual test)

Копирах wizard-v6.php временно в `/var/www/runmystore/`:

```bash
$ curl -s -o /dev/null -w "%{http_code}\n" http://localhost/wizard-v6.php
404                                            ← donela vhost (Проблем A)

$ curl -s -o /dev/null -w "%{http_code}\n" -H "Host: runmystore.ai" http://localhost/wizard-v6.php
301                                            ← Apache форсира HTTPS

$ curl -skL -o /tmp/wiz.html -w "%{http_code}\nurl: %{url_effective}\n" https://runmystore.ai/wizard-v6.php
200
url: https://runmystore.ai/login.php           ← auth redirect (Проблем B), целият chain работи
```

Файлът се парсва, рендира, auth chain работи. Само verify script-ът не може да го види. След теста изтрих копието от `/var/www/runmystore/` — не оставям шарения работен фактор.

### Опции

| | Опит | Цена | Risk |
|---|---|---|---|
| **A** *(препоръка)* | Update `verify_sacred.sh` check #5 → `curl --resolve runmystore.ai:443:127.0.0.1 -skL -o /dev/null -w "%{http_code}" https://runmystore.ai/wizard-v6.php` (force right vhost, follow redirects, accept 200 от login.php като индикатор че php парсва без fatal). | Малък diff (1 ред в script). | Тривиален. |
| B | Reorder Apache vhost-ове: `mv donela.conf 100-donela.conf && mv runmystore.conf 001-runmystore.conf` → runmystore става default за localhost. | system-level промяна. | Може да чупи донела. |
| C | Добави `?healthcheck=1` bypass в wizard-v6.php (return HTTP 200 без auth). | Минимална, ама в wizard-v6.php. | Добавя attack surface, ake misused. |
| D | Accept 4/5 PASS на тази фаза (commit на wizard-v6.php въпреки fail на #5) | Нула. | Нарушава 5× правилото. Лоша precedence. |

### Препоръка моя
**Опция A.** Минимален patch на `verify_sacred.sh`:

```bash
# 5
echo -n "5. wizard-v6.php HTTP 200 ... "
HTTP=$(curl --resolve runmystore.ai:443:127.0.0.1 -skL -o /dev/null -w "%{http_code}" https://runmystore.ai/wizard-v6.php 2>/dev/null || echo "000")
[ "$HTTP" = "200" ] && echo "OK" || { echo "FAIL ($HTTP)"; exit 1; }
```

Това:
- Принудително изпраща Host: runmystore.ai → runmystore vhost
- Следва auth redirect → final URL login.php → 200
- Засича реален PHP fatal (би върнал 500)
- `-k` accepts self-signed/expired Let's Encrypt (in case)
- Изисква wizard-v6.php да е в `/var/www/runmystore/` (post-merge git pull) — което е истинският deploy

Това би значило: verify-та на droplet-а трябва да се пуска **след** `cd /var/www/runmystore && git pull origin <branch>`, не от моя `/home/tihol/runmystore` workspace. За локалния workflow мога допълнително да добавя fallback ако curl returns 000 (Apache недостъпен).

### Какво направих
- Създадох `wizard-v6.php` в `/home/tihol/runmystore` ✅
- Run verify → 4/5 PASS (виж по-горе) ⚠️
- НЕ направих commit (per 5× rule) 🛑
- Pushвам само този QUESTIONS файл на branch `s148-cc-phase1-skeleton`

### Чакам решение
- Опция A / B / C / D?
- Ако A → ще update-на verify_sacred.sh в нов commit на същия branch, ще re-run и ако 5/5 → commit wizard-v6.php
- Ако D → commit wizard-v6.php as-is с explicit acknowledgment

**STOP. Чакам.**
