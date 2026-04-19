# COST_PRICE (Доставна цена) — Интеграция между модули

**Създадено:** S76.2e · Април 2026  
**DB поле:** `products.cost_price DECIMAL(10,2) DEFAULT 0`

---

## 1. КАКВО Е

Доставна цена = колко ПЛАЩА Пешо на доставчика за артикула (цена на придобиване, без ДДС или с ДДС — зависи от tenant settings).

**НЕ Е:**
- Wholesale price (едро цена към клиент)
- Retail price (дребно цена)

**Е:**
- Базата за марж (`margin = retail_price - cost_price`)
- Базата за печалба (`profit = (retail - cost) * qty_sold`)

---

## 2. ВЪВЕЖДАНЕ

### 2.1 products.php wizard (S76.2e)
- Поле „Доставна цена" в секция „Пожелателно" на step 3
- `S.wizData.cost_price` → payload в product-save.php
- Mic бутон за глас
- inputmode=decimal

### 2.2 deliveries.php (бъдещо)
- OCR фактура → за всеки ред cost_price се попълва автоматично
- При приемане доставка → `UPDATE products SET cost_price=? WHERE id=?`
- **ВАЖНО:** cost_price може да се променя при всяка доставка (средно претеглено или last-in)

### 2.3 Инвентаризация
- При сканиране на нов артикул без cost_price — AI пита по глас

---

## 3. ИЗПОЛЗВАНЕ В МОДУЛИ

### 3.1 sale.php
- При продажба: записва `sale_items.unit_price` (продажна) + `sale_items.cost_at_sale` (cost_price по време на продажба)
- **МИГРАЦИЯ:** добави колона `sale_items.cost_at_sale` (историческа стойност, замразена)

### 3.2 stats.php
- Печалба = SUM((unit_price - cost_at_sale) * qty)
- Марж % = AVG((unit_price - cost_at_sale) / unit_price * 100)
- Топ печеливши артикули (не топ продавани)

### 3.3 compute-insights.php (сигнали)
- **Нисък марж:** ако `margin < 15%` → pill „⚠ нисък марж"
- **Продажба на загуба:** ако `unit_price < cost_price` (при отстъпка) → signal
- **Нереалистична цена:** ако `cost_price == 0` и `retail_price > 0` → „липсва доставна цена"
- **Цена на дребно под едро:** ако `retail_price < wholesale_price` → error signal

### 3.4 chat.php (AI)
- AI анализи ползват cost_price за:
  - „Ако намалиш цената с 10%, маржът става 5% — на загуба"
  - „Общата печалба за месеца е X лв от Y оборот (марж Z%)"
  - „Продукт ABC има марж 3% — помисли за повишаване на цената"

### 3.5 orders.php (бъдещо)
- При предложения за поръчка: показва очаквана инвестиция (qty * cost_price)
- Предложения от AI: „Поръчай 20 бр × 5 лв = 100 лв инвестиция"

### 3.6 finance.php (бъдещо)
- Отчет печалба/загуба
- Cashflow анализ (разходи = cost_price * qty_bought, приходи = sale_price * qty_sold)

---

## 4. МИГРАЦИИ ЗАДАЧИ

| Модул | Задача | Приоритет |
|---|---|---|
| products.php | Добави UI поле | ✅ S76.2e |
| product-save.php | Приема `cost_price` от payload | ⚠ провери |
| sale_items | Добави `cost_at_sale DECIMAL(10,2)` | 🔴 Фаза A |
| sale.php | При продажба snapshot cost_price | 🔴 Фаза A |
| stats.php | Печалба/марж отчети | 🟡 Фаза B |
| compute-insights.php | Добави low_margin, loss_sale, missing_cost signals | 🟡 Фаза B |
| deliveries.php | OCR → cost_price update | 🟢 Фаза C |
| orders.php | Показва инвестиция | 🟢 Фаза C |
| chat.php / build-prompt.php | AI ползва cost_price в анализи | 🟢 Фаза C |
| CSV import | Нова колона cost_price | 🟡 Фаза B |

---

## 5. ПРАВИЛА

1. **cost_price винаги optional** — не блокира save на артикул
2. **При cost_price=0** — AI и сигнали го третират като „неизвестно", НЕ като 0 лв
3. **sale_items.cost_at_sale е ЗАМРАЗЕНА** (не се обновява при промяна на products.cost_price) — нужно за историческа печалба
4. **При OCR доставка** — нова cost_price презаписва старата (освен ако има флаг за претеглено средно в бъдеще)
5. **Валута** — cost_price е в tenant.currency (не двойно BGN/EUR, само в primary валутата)
6. **UI** — `priceFormat($cost_price, $tenant)` навсякъде, никога hardcoded

---

## 6. БЪДЕЩИ ВЪПРОСИ

- **Претеглена средна доставна цена** (weighted average) — надграждане при множество доставки на различни цени
- **Доставна цена с ДДС vs без** — конфигурация на tenant ниво
- **Валутни разлики** — ако доставчик е в друга валута
- **История на cost_price** — отделна таблица `cost_price_history` за тренд анализи

---

**При работа по всеки от изброените модули — обновявай този документ.**
