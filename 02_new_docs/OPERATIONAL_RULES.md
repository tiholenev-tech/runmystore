# ⚙️ OPERATIONAL_RULES — КАК РАБОТИМ

## Workflow правила за RunMyStore development

**Версия:** 1.0  
**Дата:** 21.04.2026

---

## 1. ПРАВИЛА ЗА КОД И DEPLOY

### 1.1 Никога sed — само Python scripts
- Python patch скрипт в `/tmp/sXX_xxx.py`
- Sequence: `heredoc` → `python3 /tmp/...` → `php -l` → git commit+push
- `sed` е чупил файлове. ЗАБРАНЕН.

### 1.2 Никога частичен код
- Винаги пълен файл или targeted Python patch
- Никога "добави този ред на ред 450" — използвай anchor strings

### 1.3 Deployment pattern

```bash
# Стъпка 1: Heredoc → /tmp файл
cat > /tmp/s78_fix.py << 'PYEOF'
#!/usr/bin/env python3
from pathlib import Path
f = Path('/var/www/runmystore/products.php')
content = f.read_text(encoding='utf-8')
# anchor-based replacement
old = """..."""
new = """..."""
if old in content:
    content = content.replace(old, new, 1)
    f.write_text(content, encoding='utf-8')
    print("✓ Patched")
else:
    print("⚠ Anchor not found")
PYEOF

# Стъпка 2: Run
python3 /tmp/s78_fix.py

# Стъпка 3: Validate syntax
php -l /var/www/runmystore/products.php

# Стъпка 4: Commit + push
cd /var/www/runmystore && git add -A && git commit -m "S78: fix X" && git push origin main
```

### 1.4 Python patch scripts — задължителни elements
- **Duplicate-application guard** — проверка дали вече е приложено
- **Уникален anchor string** (не line numbers)
- **Error handling** — ако anchor не намерен → не писва, само print warning

### 1.5 След всеки успешен fix
```bash
cd /var/www/runmystore && git add -A && git commit -m "S[XX]: [desc]" && git push origin main
```
**Без да питам за разрешение.**

### 1.6 Всяка сесия започва с
```bash
cd /var/www/runmystore && git pull origin main
git log --oneline | head -5
```

### 1.7 Големи файлове (>11KB) за paste
- Method: `xz -z file.py && base64 file.py.xz > file.py.xz.b64`
- 1 paste в конзолата
- Decode: `base64 -d file.py.xz.b64 | xz -d > file.py`

### 1.8 GitHub public repo
- URL pattern: `https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/[FILE]`
- Docs: `https://raw.githubusercontent.com/tiholenev-tech/runmystore/main/docs/[FILE]`
- Чета директно без git clone

---

## 2. DB FIELD NAMING — КРИТИЧНИ

Това са полетата с често-греши nomenclature:

| Правилно | НЕ |
|---|---|
| `products.code` | `sku` |
| `products.retail_price` | `sell_price`, `price` |
| `products.image_url` | `image`, `photo` |
| `inventory.quantity` | `qty`, `stock` |
| `sale_items.unit_price` | `price` |
| `sales.status='canceled'` | `'cancelled'` (две L) |
| `tenant_id` | `user_id` (не смесвай) |
| `store_id` | `location_id`, `shop_id` |

### PHP DB patterns

**Правилно:**
```php
DB::run("SELECT * FROM inventory WHERE product_id=? AND store_id=?", [$pid, $sid])
```

**Грешно:**
```php
DB::run("SELECT * FROM inventory WHERE product_id={$pid}")  // SQL injection + бъг
```

**Правило:** винаги `?` placeholders, никога `{$var}` интерполация в SQL.

---

## 3. UI ПРАВИЛА

### 3.1 Никога "Gemini" в UI
- Винаги **"AI"** (или "Асистент" в определени контексти)
- User никога не трябва да знае че е Gemini

### 3.2 Никога hardcoded валута
- Винаги `priceFormat($amount, $tenant)`
- BG двойно обозначаване (€ + лв) ЗАДЪЛЖИТЕЛНО до 8.8.2026
- След 8.8.2026 само €
- EUR default, не BGN

### 3.3 Никога hardcoded български текст
- Винаги `$tenant['language']` conditional
- Или `t('key')` helper
- Translations в отделен файл

### 3.4 Mobile-first винаги
- 56px bottom nav
- Safe area insets
- `viewport-fit=cover`
- Min tap target 44×44px (по iOS HIG)

---

## 4. ПРАВИЛА ЗА КОМУНИКАЦИЯ

### Тихол НЕ е developer. Правилата:

1. **Стегнато, кратко, минимум текст.** All-caps "ДЕЙСТВАЙ" = action без обяснения.
2. **Само български** — никога английски освен технически термини.
3. **МАКС 2 команди наведнъж**, чакай потвърждение.
4. **Python скриптове за конзола** (paste-ready).
5. **Цял код или нов файл през GitHub** — не fragmenti.
6. **60% плюсове + 40% критика** — не чиста валидация ("Ти луд ли си" = signal че има пропуск в контекста).
7. **Никога "може би" / "обаче"** — действай.
8. **Технически** = решавам сам, действам. **Логически/продуктови** = питам Тихол.

### Cheat меню за разрешения

- **1 = Yes** (безопасни команди: cp, cat, grep, ls, git, python, php, mysql, backup)
- **2 = No** (опасни: rm, chmod, DROP, TRUNCATE — питай първо)

### Дизайн промени

Claude автоматично:
- Чете `DESIGN_SYSTEM.md`
- Чете HTML mockups
- Прилага 1:1 БЕЗ да пита
- Дава Python скрипт веднага

Питане за дизайн = дразнене на Тихол.

Конфликт с BIBLE → питам.

---

## 5. СЕСИЙЕН ПРОТОКОЛ

### Начало на сесия

1. `cd /var/www/runmystore && git pull origin main`
2. Чета:
   - `MASTER_COMPASS.md` (винаги)
   - `DOC_01_PARVI_PRINCIPI.md`
   - Последен `SESSION_XX_HANDOFF.md`
   - Specific DOC-ове от router (виж MASTER_COMPASS)
3. Потвърждавам на Тихол: "Прочетох X. Готов за S[XX]."

### По време на сесия

- Commit след всеки working fix (без питане)
- `php -l` преди всеки commit
- При опасност → питам
- При сигурност → действам

### Край на сесия

- Създавам `SESSION_XX_HANDOFF.md` — какво е направено, какво следва
- Update MASTER_COMPASS.md:
  - "Последна завършена сесия"
  - "Следваща сесия"
  - "ЗАДАЧИ В S[XX+1]"
  - Phase Overview % (ако има progress)
  - "Log на решения" (ако има архитектурно решение)
- Ревизия (ако 5+ fixes в сесията):
  - Търсене на мъртъв код
  - Търсене на дубликати
  - Финален commit

---

## 6. СЕРВЪР И INFRASTRUCTURE

### Сървър detail

- DigitalOcean Frankfurt
- 2GB RAM (upgraded от 1GB заради MySQL OOM)
- Path: `/var/www/runmystore/`
- DB creds: `/var/www/runmystore/config/database.php`
- Helpers: `/var/www/runmystore/config/helpers.php`

### Backup

```bash
MYSQL_PWD=<pass> mysqldump -u user runmystore > /root/backup_sXX_$(date +%Y%m%d_%H%M).sql
```

### Папки

- `/var/www/runmystore/` — PHP файлове в корена
- `config/` — database, helpers
- `docs/` — markdown документация
- `css/` — stylesheets
- `js/` — frontend scripts
- `images/` — static assets
- `fonts/` — webfonts
- `includes/` — PHP includes

---

## 7. AI APIS

### Primary / Fallback

- **Primary:** Gemini 2.5 Flash (2 API keys в ротация)
- **Fallback:** OpenAI GPT-4o-mini (при 429/503)
- **Disabled:** Claude API (твърде скъп за production)

### Rotation logic

```php
$apis = [
    ['provider' => 'gemini', 'key' => GEMINI_API_KEY_1],
    ['provider' => 'gemini', 'key' => GEMINI_API_KEY_2],
    ['provider' => 'openai', 'key' => OPENAI_API_KEY]
];

foreach ($apis as $api) {
    $reply = callAPI($api, $message, $system_prompt);
    if ($reply) break;
    // if 429 rate limit → next
}
```

---

## 8. MODELS В CLAUDE

За различни задачи:
- **Opus (Adaptive mode):** архитектура + сложни бъгове
- **Sonnet:** стандартна разработка
- **Haiku:** CSS + текстови промени

---

## 9. HARDWARE

**DTM-5811 термо принтер:**
- Протокол: TSPL
- Интерфейс: Bluetooth
- MAC: `DC:0D:51:AC:51:D9`
- PIN: `0000`
- Етикет: 50×30mm
- ESC/POS fallback за други принтери
- Web Bluetooth API за connect в браузъра

---

## 10. ДЕБАГИРАНЕ — CHECKLIST

Преди да кажа на Тихол "не работи":

1. ✅ `php -l file.php` → syntax error?
2. ✅ Browser console → JS errors?
3. ✅ MySQL error log → DB errors?
4. ✅ Nginx error log → 500s?
5. ✅ Network tab → AJAX status codes?
6. ✅ Git diff → какво е променено?
7. ✅ Проверих ли реалната DB schema, не предположенията ми?

---

**КРАЙ НА OPERATIONAL_RULES.md**
