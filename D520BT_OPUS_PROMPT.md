# 🖨️ D520BT BLE PRINTER — OPUS NEW CHAT PROMPT

**Копирай в нов Claude Opus chat.**

═══════════════════════════════════════════════════════════════

Аз съм Tihol Tiholov, founder на RunMyStore.ai (SaaS за магазини). Beta launch 14-15 май 2026.

**Стек:**
- DigitalOcean VPS (164.90.217.120) /var/www/runmystore/
- PHP/MySQL, Capacitor APK на Samsung Z Flip6
- GitHub: github.com/tiholenev-tech/runmystore (public)

═══════════════════════════════════════════════════════════════
## 🚨 КРИТИЧНО — НЕ ПИПАЙ DTM-5811 НАСТРОЙКИТЕ
═══════════════════════════════════════════════════════════════

Имам **2 принтера**:

### ✅ DTM-5811 — РАБОТЕЩ, НЕ ПИПАЙ
- TSPL protocol, Bluetooth Classic SPP
- BDA: `DC:0D:51:AC:51:D9`, PIN: 0000
- Firmware 2.1.20241127, Codepage CP437→CP1251
- 50×30mm етикети, density 6, speed 3
- **Прекалено много време загубих преди да открием тези параметри. ЗАПЕЧАТАНИ СА. НИКОГА НЕ ПРОМЕНЯЙ КОДА КОЙТО ВЕЧЕ РАБОТИ ЗА DTM-5811.**

### ❌ D520BT — ТУК Е ПРОБЛЕМА
- Phomemo OEM (Zhuhai Quin Technology)
- Голям desktop 4×6" shipping label printer
- BLE protocol (НЕ Bluetooth Classic)
- Ползва затворен Labelife app — НЕ стандартен ESC/POS/TSPL

═══════════════════════════════════════════════════════════════
## ПРОБЛЕМ С D520BT
═══════════════════════════════════════════════════════════════

- Capacitor app pairing-ва успешно
- Изпращане на TSPL → принтерът получава но НЕ печата
- ESC/POS → същото
- Имам нужда да открием правилния BLE GATT protocol

═══════════════════════════════════════════════════════════════
## ИСКАМ ОТ ТЕБ
═══════════════════════════════════════════════════════════════

1. **Web search** + GitHub research:
   - "D520BT protocol", "Phomemo D520 reverse engineering"
   - Opensource Python libraries: pyphomemo, phomemo-printer
   - Phomemo OEM family BLE specs (D11, D110, D30, M110, M02)
   - GATT service UUIDs + write characteristics

2. **Намери:**
   - BLE service UUID
   - Write characteristic UUID
   - Image format (raw bitmap? PCL? compressed?)
   - Header bytes / command structure
   - Init sequence (heater calibration, label feed)

3. **Дай ми работещ JS код** който:
   - Свързва се към D520BT през Web Bluetooth API в Capacitor
   - Конвертира 50×30mm етикет (баркод + име + цена) в правилния формат
   - Печата успешно
   - **НЕ ВЛИЗА В КОНФЛИКТ с DTM-5811 кода** — отделен driver/class

4. **Detection logic** — кода трябва автоматично да разбере кой принтер е свързан (по име, BDA, или GATT service) и да изпрати правилния protocol.

═══════════════════════════════════════════════════════════════
## ДОСТЪП ДО КОДА
═══════════════════════════════════════════════════════════════

Код в GitHub: github.com/tiholenev-tech/runmystore (public, blob URLs работят).

Ключови файлове:
- `js/capacitor-printer.js` — текущ printer driver (DTM-5811 working)
- `products.php` (около ред 11800) — `wizPrintLabelsMobile()` функция
- `sale.php` — print at end of sale

`raw.githubusercontent.com` И `api.github.com` са BLOCKED в твоя sandbox. Метод за четене:
```bash
curl https://github.com/tiholenev-tech/runmystore/blob/main/FILE?plain=1
```
Парсвай `"rawLines":[...]` JSON. Helper: `tools/gh_fetch.py` в репото.

═══════════════════════════════════════════════════════════════
## DELIVERABLES
═══════════════════════════════════════════════════════════════

1. Изследване report — какви opensource драйвери има за Phomemo D family
2. Identified BLE protocol details (UUIDs, image format, init sequence)
3. Работещ JS код — separate `D520BTDriver` class
4. Detection logic в `capacitor-printer.js` (по име на принтер)
5. Test instructions — как да тествам на phone
6. Backup на текущия `capacitor-printer.js` ПРЕДИ всеки edit

**КРИТИЧНО ЗА БЕТА: Това трябва да стане ДНЕС.**

═══════════════════════════════════════════════════════════════
## CONTEXT BOOT
═══════════════════════════════════════════════════════════════

Преди да започнеш — прочети:
- `https://github.com/tiholenev-tech/runmystore/blob/main/STATE_OF_THE_PROJECT.md?plain=1`
- `https://github.com/tiholenev-tech/runmystore/blob/main/MASTER_COMPASS.md?plain=1`
- `https://github.com/tiholenev-tech/runmystore/blob/main/js/capacitor-printer.js?plain=1`

Питай ме само логически решения (UX, какво печатам). Технически решения вземи сам.

Auto-mode. Прави backup, тествай, давай ми copy-paste команди за droplet.
