═══════════════════════════════════════════════════════════════════
# SESSION S87/S88 - FULL HANDOFF
Date: 2026-04-27
Duration: ~6 часа
Parallel sessions: 2 (printer + products bugs)

═══════════════════════════════════════════════════════════════════
## ЧАСТ 1: PRINTER MULTI-SUPPORT (S87/S88)
═══════════════════════════════════════════════════════════════════

### Files
- js/capacitor-printer.js (modified, committed)
- printer-setup.php (modified, committed)

### Status
✅ DTM-5811 production - TSPL, 50×30mm, работи
🟡 D520BT - pair-ва се но не печата (proprietary raster needed)
❌ AQ20 - не е тестван (Тихол го отхвърли)

### Архитектура
- SERVICE_UUIDS масив (3 UUIDs)
  - 000018f0 → DTM-5811 (TSPL)
  - 0000ff00 → D520BT data service (Phomemo standard)
  - 0000af30 → D520BT advertisement (GT01 family fallback)
- discoverWriteEndpoint() - dynamic write char per device
- Per-device storage: serviceUuid + writeCharUuid
- Backward compat за DTM paired преди S88

### Debug tools (запазени)
- pairDebug() - GATT tree dump
- scanDebug(ms) - LE scan с RSSI
- DEBUG секция в printer-setup.php (toggle "Показвай debug")
- CapPrinter._diagnostics namespace (от console):
  - .sendRaw(bytes, label)
  - .tspl() / .cpcl() / .escpos() / .phomemoInit()

### D520BT findings
- Service ff00, writeChar ff02
- 4 протокола изпратени БЕЗ error, нито един не печата:
  TSPL 113b, CPCL 58b, ESC/POS 19b, Phomemo Init 15b
- Заключение: иска proprietary Phomemo raster bitmap

### DEFERRED: D520BT raster support
Estimated: 3-5 часа
Стъпки:
1. Render TSPL → bitmap (canvas + Floyd-Steinberg dithering)
2. Bitmap → Phomemo raster bytes (opensource libs)
3. Header/footer команди specific за D520BT
4. Test + debug

Resources:
- github.com/theacodes/phomemo-tools
- github.com/vivier/phomemo-tools

ВАЖНО: НЕ започвай D520BT raster без изрично искане от Тихол.

### Commits (хронологично)
- 37fdcf2 - Премахнат namePrefix:'DTM' filter
- f199cdd - Multi-printer support (DTM + D520BT pairing)
- 69e749f - Reorder priority + ff00 data service
- c28f767 - testRaw() TSPL diagnostic
- c2c2caa - 3 protocol probes (CPCL/ESC-POS/Phomemo)
- 4c026cb - Cleanup test buttons, запазен infrastructure

═══════════════════════════════════════════════════════════════════
## ЧАСТ 2: SECURITY INCIDENT (S87)
═══════════════════════════════════════════════════════════════════

### Намерено
- /var/www/runmystore/updater.sh
- Created: 2026-04-26 11:50:07 UTC
- Modified: 2026-04-26 11:53:00 UTC
- Size: 2105 bytes
- MD5: d6cf7918e29705b6707a8662946cb90c
- SHA256: 67c518f4fe2c82987996f7f67676b29b755a435a49cb284e02443b5e891c5667

### Тип
Mirai/Gafgyt-family botnet dropper.
- Hardcoded C2: 2.27.4.56:5000
- Multi-arch payloads: x86_64, i686, i586, mips, mipsel, armv4l-v7l, m68k, powerpc, sh4
- Detached fork execution
- history -c (умишлено крие следи)

### Drop timeline
- 11:50:07 → updater.sh създаден
- 11:50:11 → първа вълна payloads (/tmp/client_*)
- 11:53:09 → втора вълна (.1 суфикси)
- 13:02:03 → ⚠️ ТРЕТА ВЪЛНА (.2 суфикси, 1ч12мин по-късно)

### Проверки
✅ НЕ работят сега (ps clean)
✅ НЕ комуникират с C2 (ss clean)
✅ НЕ е в crontab/systemd
✅ НЕ са пускани от Тихол shell (bash_history clean)
❌ ELF binaries за 12 архитектури седят в /tmp/

### Apache log 11:45-11:55 UTC
Само GET от Тихол мобилен (149.62.205.117, Android 10 Chrome 147).
Нула POST → НЕ е web upload през Apache.
Вектор UNKNOWN (възможни: leaked credentials в git history от 23 април).

### Действия
✅ updater.sh → updater.sh.QUARANTINE_S87 (mv-нат)

### PENDING (не направено - чака Тихол)
1. Backup forensics:
   tar -czf /root/forensics_S87_$(date +%Y%m%d).tar.gz \
     /var/www/runmystore/updater.sh.QUARANTINE_S87 \
     /tmp/client_* \
     /tmp/security_audit_S87/
2. rm -f /tmp/client_*
3. rm -f /var/www/runmystore/updater.sh.QUARANTINE_S87
4. Verify clean след 5 мин: ls /tmp/client_*
5. Смяна на root SSH password
6. Disable SSH password auth (ключове only)
7. git log -p за остатъчни credentials

### Forensics archive
/tmp/security_audit_S87/ - запазен read-only audit с:
- ps_auxf, network ss/netstat, cron dumps
- ssh authorized_keys, sshd config
- recent files in /var/www/runmystore
- apache window log
- auth log
- malware scan: webshell_check, suid_recent

═══════════════════════════════════════════════════════════════════
## ЧАСТ 3: S88 BUG TRACKER (products.php)
═══════════════════════════════════════════════════════════════════

### Files
- products.php (10807+ редове)
- product-save.php

### Status table

| # | Описание | Статус | Commit |
|---|----------|--------|--------|
| 1 | Variation images не излизат в detail | 🟡 DONE in tree, NOT COMMITTED | - |
| 2 | Сигнали празни (q1-q6 home) | ❌ NOT STARTED | - |
| 3 | "..." бутон → "Като предния" | ❌ NOT STARTED | - |
| 4 | Universal fuzzy match за "Добави" | ❌ NOT STARTED | - |
| 5 | Color prediction chips отрязани | ✅ COMMITTED | 5802655 |
| 6 | Дубликати name/code/barcode | ❌ NOT STARTED | - |
| 7 | "Върни и презапиши" history | ❌ NOT STARTED | - |

### БЪГ #1 - Variation images (DONE in tree)
Backend (product-save.php):
- $variant_ids_by_color map (lowercase color → child product IDs)
- JSON response връща variant_ids_by_color

Frontend (products.php):
- wizSave() след success POST-ва upload_image за всеки child variant 
  (групира по ai_color)
- openProductDetail() SELECT-ва p.image_url от вариациите
- Рендерира 32×32 thumbnail (vThumb)

Backups:
- products.php.bak.s88_01_1557
- product-save.php.bak.s88_01_1557

ОСТАВА: commit (потребителят изрично каза да commit-не).

### БЪГ #5 - Color chips (DONE)
- CSS промяна на .photo-color-input
- flex-wrap:wrap, input flex:1 1 100%, order:2
- Резултат: input на собствен ред с full width
- Used само в multi-photo wizard (line 5859)
- Commit: 5802655

### БЪГ #2 - Сигнали празни
Diagnosis findings:
- Backend код за q1-q6 е цял и коректен
- 38 живи insights в DB за tenant=7/store=1
- Demo cards остават защото frontend loadSections() прави early-return
- Подозрения: d.ok=false / store mismatch / AJAX fail

ПРЕПОРЪКА за следваща сесия:
- Добави overlay logging (като DEBUG S87) в loadSections()
- Покажи на екрана какво връща AJAX
- Тихол НЕ може Chrome inspect → trябва on-screen debug

### БЪГ #3 - "Като предния" duplicate
Изисквания на Тихол:
- Бутон "..." на "Добави артикул" → "📋 Като предния"
- Code: auto-increment (NIKE-001 → NIKE-002)
- Barcode: ВИНАГИ empty (потребителят сканира нов)
- Quantity: 0 по default + checkbox "Копирай и количеството"
- Снимка: копира се + бутон "📷 Смени"
- След save: "📋 Още един такъв?" за следваща итерация
- Достъпно и от product detail на existing артикул

### БЪГ #4 - Universal fuzzy match
Хора-лесна логика:
- Праг: 80% similarity (Levenshtein normalized)
- Прилага се на ВСИЧКИ "Добави" бутони:
  цвят, размер, категория, подкатегория, доставчик, материя
- Modal: "Вече има 'Бял' — да го ползвам ли вместо 'Бяло'?"
- Опции: [Да, ползвай съществуващото] [Не, добави като ново]

ИЗИСКВА: ALTER TABLE products ADD COLUMN material VARCHAR(50) DEFAULT NULL
(не е изпълнен, чака Тихол approve)

### БЪГ #6 - Duplicate name/code/barcode
Изисквания:
- При save → check за existing с same name/code/barcode
- SQL: LOWER(TRIM(name)) match за name, exact за code/barcode
- Modal с 3 опции:
  - "✓ Запази въпреки това"
  - "📂 Отвори съществуващия" → openProductDetail(id)
  - "✕ Отказ"
- Показва ЗАЩО е дубликат (по кое поле)

### БЪГ #7 - История + Revert
Изисквания на Тихол:
- Бутон "Върни и презапиши" ВИНАГИ видим в product detail
- НЕ времево ограничен (опция Б)
- Модал с timeline на промените
- Per-change revert бутон
- AJAX: ajax=revert_change&history_id=X

ВАЖНО: Използвай съществуваща audit_log таблица 
(има old_values longtext, new_values longtext - не trябва нова таблица).
Просто product-save.php трябва да пише по-дълбоки snapshots.

═══════════════════════════════════════════════════════════════════
## ЧАСТ 4: НОВИ ЛОГИКИ ОТ ТИХОЛ (S88+ pending)
═══════════════════════════════════════════════════════════════════

### Филтри (pending implementation)
8 реда филтри в #scrHome И #scrProducts (страница "Виж всички 247"):
1. Цена (до €20 | €20-50 | €50-100 | €100-200 | €200+)
2. Доставчици (динамично)
3. Категории (cascade от доставчик)
4. Размер (cascade от категория)
5. Цвят (cascade)
6. Материя (изисква нова DB колона)
7. Сортиране (Най-продавани | Най-нови | Най-скъпи)
8. Сигнали (collapsed, разгъва 6 chips q1-q6)

Sigali chips:
🔴 Губя | 🟣 Защо губя | 🟢 Печеля | 🔷 Защо печеля | 🟡 Поръчай | ⚫ Не поръчвай

### Backend status за филтри
✅ ajax=categories?sup=X cascade готов
❌ ajax=filter_options - НЕ напишан
❌ ajax=products extension за size/color/material/signal - НЕ напишан

### CSS reuse
.fltr-row / .fltr-btn / .fltr-btn.active / .fltr-label / .fltr-hint
работят 1:1 за rows 1-7. Само ред 8 (сигнали) иска нови класове.

### Размер cascade гарантира
- Категория "Обувки" → 38, 39, 40 (не XS/S/M)
- Категория "Дрехи" → XS, S, M, L, XL
- SELECT DISTINCT size FROM products WHERE category_id=?

### Дубликати в DB (vid в diagnosis)
- 28 цвята с дубликати "Бял"/"Бяло"
- БЪГ #4 fuzzy match трябва да предотврати в бъдеще
- Старите дубликати ще се оправят manual или migration script

### Estimate
~535 LOC + 1 ALTER ≈ 5-7 часа

### Diagnosis файл
/tmp/filter_analysis.txt - пълен анализ от предишна сесия

═══════════════════════════════════════════════════════════════════
## ЧАСТ 5: BIBLE LAWS REMINDER
═══════════════════════════════════════════════════════════════════

ЗАКОН №1: Пешо не пише. Voice/photo/tap only.
ЗАКОН №2: i18n - t() / $tenant['lang'], никога hardcoded BG
ЗАКОН №3: priceFormat($amount, $tenant), никога hardcoded "лв"/"€"
ЗАКОН №4: DB columns:
  - products.code (NOT sku)
  - products.retail_price (NOT sell_price)
  - inventory.quantity (NOT qty)
  - sales.status='canceled' (one L)
ЗАКОН №5: DB::run() / DB::get() - НЕ raw $pdo
ЗАКОН №6: UI казва "AI" - НЕ "Gemini"
ЗАКОН №7: config/config.php gitignored - НЕ overwrite
ЗАКОН №8: Backup ПРЕДИ всяка промяна

### Decision split
- Технически (имена, скриптове, git, индекси, опции А/Б/В метод) → Claude решава
- Логически/продуктови (UX, текстове, wizard order, UI имена) → Тихол

### Workflow
- Чат (90%) - малки fix-ове, Python скриптове за paste, UX/design
- Claude Code (10%) - големи rewrite-и, multi-hour автономни задачи

### Design law
При design промяна:
- НЕ питай → автоматично прочети DESIGN_SYSTEM.md
- Промени САМО visuals (CSS/HTML wrappers/classes)
- НЕ пипай logic/JS/data flow/handlers
- Конфликт с BIBLE → питай

### Moderate critical approach (LAW)
- 60% positives + 40% critique
- НЕ pure validation
- НЕ destruction
- Honestly flag risks, edge cases, time estimates

═══════════════════════════════════════════════════════════════════
## ЧАСТ 6: НЕЗАПОЧНАТО / ПРЕДСТОЯЩО
═══════════════════════════════════════════════════════════════════

### High priority (next session)
1. Commit S88 #1 (variation images) - DONE in tree
2. S88 #2 - сигнали празни (с overlay logging първо)
3. Security cleanup (delete /tmp/client_*, rotate SSH password)
4. S88 #3 "Като предния"

### Medium priority
5. S88 #6 - duplicate check by name/code/barcode
6. S88 #7 - audit history + revert

### Low priority (large scope)
7. S88 #4 - universal fuzzy match (touches много места)
8. Filters implementation (8 rows × 2 screens)

### Deferred (not in plan)
- D520BT raster support (изисква 3-5ч + не от Тихол искано)
- AQ20 printer (Тихол го отхвърли)

═══════════════════════════════════════════════════════════════════
## ЧАСТ 7: BACKUP ФАЙЛОВЕ
═══════════════════════════════════════════════════════════════════

Активни backup-и в /var/www/runmystore/:
- products.php.bak.s88_01_1557 (преди #1)
- product-save.php.bak.s88_01_1557 (преди #1)
- products.php.bak.s88_05_1536 (преди #5)
- products.php.bak.s88_20260427_1509 / _1511 (диагностика)
- js/capacitor-printer.js.bak.s88_20260427_1516 (преди multi-printer)
- js/capacitor-printer.js.bak.s88_20260427_1533_ff00 (преди ff00)
- js/capacitor-printer.js.bak.20260427_1349 (преди namePrefix removal)
- js/capacitor-printer.js.bak.20260427_1410_overlay (преди debug overlay)

Forensics:
- /tmp/security_audit_S87/ (read-only audit dump)
- /tmp/security_audit_S87.tar.gz (compressed)

═══════════════════════════════════════════════════════════════════
## ЧАСТ 8: ИНСТРУКЦИИ ЗА СЛЕДВАЩА СЕСИЯ
═══════════════════════════════════════════════════════════════════

1. Прочети този handoff целия преди да започнеш работа
2. Прочети BIBLE_v3_0_TECH.md и DESIGN_SYSTEM.md
3. git status - провери дали S88 #1 е committed
4. Питай Тихол откъде продължаваме (приоритет от ЧАСТ 6)
5. Backup ПРЕДИ всяка промяна
6. Координирай ако друг session работи паралелно (НЕ git add .)

═══════════════════════════════════════════════════════════════════

End of handoff.
