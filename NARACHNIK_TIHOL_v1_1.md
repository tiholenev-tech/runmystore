# НАРЪЧНИК — ТИХОЛ · RunMyStore.ai

**За Claude:** Този файл се чете АВТОМАТИЧНО при начало на всеки нов чат.

---

## 1. ЗАДЪЛЖИТЕЛНИ ДОКУМЕНТИ (четат се в началото)

От `/var/www/runmystore/`:
- `BIBLE_v3_0_CORE.md` — основни закони, философия
- `BIBLE_v3_0_TECH.md` — технически стандарти
- `BIBLE_v3_0_APPENDIX.md` — приложения, форми
- `DESIGN_SYSTEM.md` — Neon Glass design система (v1.1)
- `COST_PRICE_INTEGRATION.md` — cost_price интеграция между всички модули
- `SESSION_XX_HANDOFF.md` (последният) — handoff от предишна сесия

---

## 2. АБСОЛЮТНИ ЗАКОНИ

- **ЗАКОН №1:** Пешо не пише нищо. Всичко е глас / снимка / 1 tap.
- **ЗАКОН №2:** PHP смята, Gemini говори. Pills/Signals = PHP+SQL. AI само в чата.
- **ЗАКОН №3:** AI мълчи, PHP продължава. Никога не блокира на AI грешка.
- **ДИЗАЙН-ЗАКОН:** Когато Тихол иска промяна на визия — Claude НЕ пита. Чете DESIGN_SYSTEM.md + mockup-и, прилага 1:1, дава Python скрипт.

---

## 3. ЗЛАТНИ ПРАВИЛА

1. **Никога sed** — винаги Python в `/tmp/sXX_*.py` с duplicate-application guard
2. **След успешен fix** → commit + push ВЕДНАГА без да питаш
3. **Макс 2 команди на съобщение** — чакай confirmation
4. **Винаги целия файл** — никога частичен код
5. **Тихол НЕ е developer** — кратки български команди, без reasoning aloud
6. **Никога „готов ли си"** или подобни въпроси
7. **Никога промяна на логика при дизайн заявка** — САМО CSS/HTML/класове

---

## 4. ТЕХ СТЕК

- **Сървър:** DigitalOcean Frankfurt · 2GB RAM
- **Код:** `/var/www/runmystore/` · PHP 8 + MySQL
- **GitHub:** `tiholenev-tech/runmystore` (main branch)
- **Test tenant:** `tenant_id=7`, `store_id=47`
- **AI:** Gemini 2.5 Flash (2 ключа, rotation on 429) + OpenAI GPT-4o-mini fallback
- **Mobile:** Capacitor wrapper
- **Primary език UI:** Bulgarian + международен през `tenant.lang`

---

## 5. ВАЛУТА

- BG е в евро от 1.1.2026
- Двойно € + лв задължително до 8.8.2026 (курс 1.95583)
- След 8.8.2026 — само €
- Никога hardcoded "лв" / "BGN" / "€" → винаги `priceFormat($amount, $tenant)`

---

## 6. КЛЮЧОВИ ФАЙЛОВЕ

- `products.php` — wizard за артикули (7000+ реда, активно се развива)
- `sale.php` — каса
- `chat.php` — AI чат
- `compute-insights.php` — мозъкът на сигналите
- `build-prompt.php` — AI prompt builder
- `ai-topics-catalog.json` — 1000 теми за AI
- `biz-coefficients.php` — 300+ бизнес типа

---

## 7. ТЕКУЩА СЕСИЯ (S76)

- S76.1: flicker fix при validation ✅
- S76.2a: single fallback step 4 = glass + 4-btn footer ✅
- S76.2b: единичен → save на step 3 (merge step 5 → step 3) ✅
- S76.2c: Мин. количество под Брой (амбър stepper) ✅
- S76.2d: Мерна единица = dropdown + inline add ✅
- S76.2e: Доставна цена (cost_price) поле в Пожелателно ⏳
- S76.3: Neon refresh на step 4 Variants path (preview pill, tabs) ⏳

---

## 8. СЕСИЯ СТРУКТУРА

- Всяка сесия приключва с `SESSION_XX_HANDOFF.md`
- Handoff се дава като **markdown в чата** за copy/paste, НЕ през `cat`
- Нов чат → `git pull origin main` първо

---

**Последна редакция:** S76.2e · Април 2026
