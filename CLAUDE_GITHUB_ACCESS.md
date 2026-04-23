# 🤖 CLAUDE GITHUB ACCESS — ЗАДЪЛЖИТЕЛНО В НАЧАЛОТО НА ВСЕКИ ЧАТ

**Версия:** 1.0 · 23.04.2026
**Автор на решението:** Claude (S82.CAPACITOR sesisa) + Тихол
**Цел:** ЕДНОЗНАЧЕН, РАБОТЕЩ метод за Claude да чете файлове от
`tiholenev-tech/runmystore` репото, без Тихол да качва нищо ръчно.

---

## 🚨 ЗАЩО СТАРОТО ПРАВИЛО НЕ РАБОТИ

Claude паметта дълго казваше:
> „Чети с `https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/[FILE]`"

**Това вече НЕ работи в sandbox-а на Claude.** Проверено е в текущата сесия:

| Домейн | Статус | Използване |
|---|---|---|
| `raw.githubusercontent.com` | ❌ **BLOCKED** | Не — връща 403 "Host not in allowlist" |
| `api.github.com` | ❌ **BLOCKED** | Не — връща 403 |
| `github.com` | ✅ **ALLOWED** | Да — единственият достъпен path |

Затова всяко `web_fetch` към raw URL-и се проваля в нови чатове
("This URL cannot be fetched because it was not provided by the user").

---

## ✅ РАБОТЕЩОТО РЕШЕНИЕ — ДВУСТЕПЕННО

### 1. За малки/средни файлове (< ~500 реда)

`github.com/USER/REPO/blob/BRANCH/FILE?plain=1` връща HTML страница, в
която съдържанието е в JSON масив `"rawLines":[...]`. Парсваме го с
Python regex.

### 2. За големи файлове (products.php = 8825 реда и т.н.)

GitHub не рендерира `rawLines` за такива файлове. Fallback:
`git clone --depth=1` в `/tmp/gh_cache/` (`github.com` е allowed, git
protocol работи).

Скриптът `tools/gh_fetch.py` прави и двете **прозрачно** — просто
`python3 gh_fetch.py PATH`.

---

## 🚀 BOOTSTRAP В НАЧАЛОТО НА ВСЕКИ НОВ ЧАТ

Claude пуска тази команда **веднъж** в `bash_tool`. Тя взима скрипта
от репото и го слага в `/tmp/gh.py`. След това всичко се чете с
`python3 /tmp/gh.py PATH`.

```bash
cd /tmp && git clone --depth=1 https://github.com/tiholenev-tech/runmystore.git gh_cache/tiholenev-tech_runmystore 2>/dev/null || git -C gh_cache/tiholenev-tech_runmystore pull --quiet; cp gh_cache/tiholenev-tech_runmystore/tools/gh_fetch.py /tmp/gh.py && echo "✔ gh.py ready — usage: python3 /tmp/gh.py PATH [-l|-r 1:100|-o FILE|--list|--refresh]"
```

След това:

```bash
# Прочети handoff файла
python3 /tmp/gh.py SESSION_S82_CAPACITOR_HANDOFF.md

# Прочети само редове 100-200 от products.php
python3 /tmp/gh.py products.php -r 100:200

# Виж всички файлове в репото
python3 /tmp/gh.py --list

# Force refresh от GitHub
python3 /tmp/gh.py SOME_FILE.md --refresh

# Запис в локален файл
python3 /tmp/gh.py CLAUDE.md -o /tmp/claude.md
```

---

## 📋 ЗАДЪЛЖИТЕЛНО ЧЕТЕНЕ В НАЧАЛОТО НА ВСЕКИ НОВ ЧАТ

След bootstrap командата, Claude чете в този ред:

1. `CLAUDE_GITHUB_ACCESS.md` (този файл — да провери за v1.1+ update)
2. `MASTER_COMPASS.md` — текущо състояние на проекта, dependency tree
3. `NARACHNIK_TIHOL_v1_1.md` — правилата на Тихол
4. `docs/BIBLE_v3_0_CORE.md` + `BIBLE_v3_0_TECH.md` + `BIBLE_v3_0_APPENDIX.md`
5. `DESIGN_SYSTEM.md`
6. `ROADMAP.md`
7. Последен `SESSION_S*_HANDOFF.md` (за конкретната задача)
8. Модул-специфичен design logic (`PRODUCTS_DESIGN_LOGIC.md`,
   `ORDERS_DESIGN_LOGIC.md`, и т.н.)

---

## 🔧 АЛТЕРНАТИВИ (ако скриптът не работи)

### Fallback A — директно git clone
```bash
cd /tmp && git clone --depth=1 https://github.com/tiholenev-tech/runmystore.git repo
cat /tmp/repo/PATH/TO/FILE
```

### Fallback B — curl + ръчно парсване на rawLines
```bash
curl -sL "https://github.com/tiholenev-tech/runmystore/blob/main/FILE.md?plain=1" \
  | python3 -c "import sys,re,json; h=sys.stdin.read(); m=re.search(r'\"rawLines\":(\[.*?\])(?=,\")', h, re.S); print('\n'.join(json.loads(m.group(1))))"
```

---

## 🛠 ПОДДРЪЖКА

Ако GitHub промени HTML структурата и `"rawLines":[...]` изчезне:
- Fallback към `git clone` продължава да работи — скриптът го прави
  автоматично.
- Ако и двете се счупят — Тихол пуска `git pull && cat FILE` в
  droplet-а и paste-ва резултата тук.

---

## 📝 CHANGELOG

- **v1.0 (23.04.2026)** — първоначална версия.
  Причина: raw.githubusercontent.com и api.github.com са блокирани в
  Claude sandbox. Правило #1 в Claude's memory е обновено.
