> **⚠️ ТОЗИ ДОКУМЕНТ Е OVERVIEW.**
> За пълна спецификация:
> - **`INVENTORY_HIDDEN_v3.md`** — confidence model, 10 принципа, Zone setup, timeline 30 дни
> - **`BIBLE_v3_0_APPENDIX.md §7`** — Склад Hub архитектура (warehouse.php, 5 cards)
> - **`BIBLE_v3_0_TECH.md §9, §11.12`** — 12-те железни правила, Store Health формула

---

# 📘 DOC 11 — INVENTORY V4 + WAREHOUSE HUB

## Складът се изгражда сам

**Версия:** 1.0 | **Дата:** 21.04.2026 | **Част от:** Група 4: МОДУЛИ

---

## 📑 СЪДЪРЖАНИЕ

1. Философия: „Складът се изгражда сам"
2. Warehouse.php като hub
3. Confidence model
4. 10 принципа на инвентаризацията
5. Zone Walk — „Лов на скрити пари"
6. Event-sourced architecture
7. Smart Resolver
8. Progress bar — „AI знае магазина на X%"
9. Ревизия като subsection
10. 12-те правила

---

# 1. ФИЛОСОФИЯ: „СКЛАДЪТ СЕ ИЗГРАЖДА САМ"

Повечето складови програми изискват Пешо да преброи всичко в ден 1. **Това е бариерата.** 90% от потребителите се отказват тук.

**RunMyStore прави обратното:**
- Ден 1: **никаква инвентаризация**
- Пешо просто започва да продава
- AI учи през времето
- Складът расте organically
- Confidence расте от 20% (ден 1) към 100% (след 3 месеца активна употреба)

**Никога не казваме „инвентаризация". Казваме:**
- „Лов на скрити пари"
- „Провери дали AI е прав"
- „Замразени пари"
- „Скрити на рафта"

---

# 2. WAREHOUSE.PHP КАТО HUB

warehouse.php не е отделен модул — е **hub** с подмодули.

```
┌─────────────────────────────────────┐
│ Склад                               │
├─────────────────────────────────────┤
│ AI знае магазина на 78%             │
│ ████████████░░░░  [Подобри →]      │
├─────────────────────────────────────┤
│ [📦 Артикули]    [📷 Доставки]      │
│ [🔄 Трансфери]   [📋 Ревизия]       │
│ [💰 Разходи]     [🎯 Промоции]      │
└─────────────────────────────────────┘
```

Всеки бутон води към подмодул. Hub визуализира общия stock status + confidence.

---

# 3. CONFIDENCE MODEL

Всеки артикул има невидим `confidence_score` от 0% до 100%.

```sql
ALTER TABLE products
  ADD COLUMN confidence_score TINYINT DEFAULT 20,
  ADD COLUMN last_verified_at DATETIME NULL;
```

## 3.1 Как се изчислява

| Събитие | Confidence |
|---|---|
| Създаден при продажба (име + цена) | 20% |
| + баркод или артикулен номер | +10% |
| + доставна цена (от доставка/фактура) | +20% |
| + категория и доставчик | +10% |
| + доставка (количество от фактура) | +20% |
| + физическо потвърждение (преброен) | +20% = 100% |

## 3.2 Нива

| Ниво | Score | AI знае | AI НЕ знае |
|---|---|---|---|
| 🔴 Минимално | 0-30% | Име, продажна цена | Всичко останало |
| 🟡 Частично | 31-60% | + баркод, категория | Доставна цена, наличност |
| 🟠 Добро | 61-80% | + доставна цена, доставки | Физическа наличност |
| 🟢 Пълно | 81-100% | Всичко | Нищо |

## 3.3 Decay

Физическо потвърждение е валидно 30 дни. След това:
- 30+ дни → confidence пада 5%/седмица
- 60+ дни → артикулът е „стар"

## 3.4 Правило: Диапазони вместо фалшива точност

```
Confidence 30%:  „Печалба днес: между 120 и 280 лв"
Confidence 60%:  „Печалба днес: между 220 и 260 лв"
Confidence 90%:  „Печалба днес: 243 лв"
```

Диапазонът се стеснява с повече данни. Пешо **вижда прогреса**.

---

# 4. 10 ПРИНЦИПА НА ИНВЕНТАРИЗАЦИЯТА

1. **Never block a sale** — дори без име, баркод, каквото и да е
2. **Voice is primary** → photo fallback → barcode best case
3. **Stock is calculated** — доставки минус продажби, НЕ ръчно поле
4. **Negative stock is normal** — означава „продадено преди заприходено"
5. **Statistics ALWAYS work** — с диапазони, никога скрити
6. **Ranges motivate** — „180-340€" кара Пешо да иска точното число
7. **Delivery = mass boost** — една снимка = 20 продукта + triggers category count
8. **Zone Walk fills gaps** — покрива dead stock, разместени, забравени
9. **Double counting = cross-validation** — category count + zone count ловят грешки
10. **Системата работи от секунда 1** — точността расте, не се изисква

---

# 5. ZONE WALK — „ЛОВ НА СКРИТИ ПАРИ"

## 5.1 Концепция

Zone Walk е **deterministic, gamified проверка на 1 рафт/зона**.

Не е „инвентаризация на целия магазин". Е „провери дали AI знае правилно за зоната на обувките".

## 5.2 Setup

Пешо определя **зони** в магазина:
- 🟢 CUSTOMER ZONE — видими за клиенти (закачалки, витрина)
- 🟡 SHELF ZONE — под каса, резерв
- 🔴 STORAGE ZONE — задно помещение (ако има)

Всяка зона има **снимка** която Пешо прави при setup. AI помни снимката.

## 5.3 Trigger

AI предлага Zone Walk когато:
- Delivery пристига (категория count)
- 30+ дни без проверка на зона
- Петък ритуал (седмичен)
- След Sale с невероятен stock discrepancy

## 5.4 UI

```
┌─────────────────────────────────────┐
│ Лов на скрити пари                  │
│ Зона: Рафт обувки                   │
├─────────────────────────────────────┤
│ [снимка на зоната от setup]         │
│                                     │
│ AI казва: 12 Nike общо              │
│                                     │
│ ⟶ Преброй:                          │
│                                     │
│ Nike 41: System=3  [─3+]  Преброих  │
│ Nike 42: System=2  [─2+]  Преброих  │
│ Nike 43: System=5  [─5+]  Преброих  │
│ Nike 44: System=2  [─2+]  Преброих  │
│                                     │
│ [Готово]                            │
└─────────────────────────────────────┘
```

Зелено = съвпадение. Червено = разлика (Пешо броил различно).

## 5.5 Езикът на парите

AI **не казва** „преброй рафта". Казва:

```
„Пешо, маратонките Nike се продават по 3/седмица.
 По мои данни остава 1 чифт. Но може да бъркам —
 виж рафта и кажи колко виждаш."
```

Пешо отива → брои 4 → AI:

```
„Значи имаш 4, не 1. Обновявам.
 4 чифта × €55 = €220 на рафта.
 Благодаря, Пешо. Сега числата са по-точни."
```

**Трикът:** Пешо не „прави инвентаризация". Проверява дали AI е прав. Его-ангажиране — „аз знам по-добре от AI".

---

# 6. EVENT-SOURCED ARCHITECTURE

Stock не е една колона `quantity`. Е **проекция от stock_movements ledger** (DOC 05 § 15).

```
inventory.quantity_millis  = SUM(stock_movements.quantity_millis)
                             WHERE product_id=X AND store_id=Y
```

Това дава:
- **History:** „колко имах на 15.04?" = sum до 15.04
- **Correction:** event за adjustment, не UPDATE
- **Audit:** всяка промяна track-вана

---

# 7. SMART RESOLVER

## 7.1 Deduplication

„Nike 42 черни" и „Найки 42 чрн" → AI fuzzy match 85%+ → merge автоматично. Под 85% → пита **веднъж** → помни завинаги.

```php
class SmartResolver {
    public static function matchOrCreate($voice_text, $tenant_id) {
        $products = Products::getAll($tenant_id);
        $best_match = null;
        $best_score = 0;

        foreach ($products as $p) {
            $score = self::similarity($voice_text, $p['name']);
            if ($score > $best_score) {
                $best_score = $score;
                $best_match = $p;
            }
        }

        if ($best_score > 0.85) {
            return ['action' => 'matched', 'product' => $best_match];
        }
        if ($best_score > 0.65) {
            return ['action' => 'confirm', 'product' => $best_match, 'input' => $voice_text];
        }
        return ['action' => 'create', 'input' => $voice_text];
    }
}
```

## 7.2 Speed detection

`<3 сек/артикул` = подозрително бързо. AI алертва:

```
AI: Броеш много бързо. Сигурен ли си че виждаш наистина?
```

---

# 8. PROGRESS BAR — „AI ЗНАЕ МАГАЗИНА НА X%"

Store Health се изчислява:

```php
function storeHealth($tenant_id, $store_id) {
    $stock_accuracy = getConfidenceAvg($tenant_id, $store_id) * 0.4;
    $data_freshness = getDataFreshness($tenant_id, $store_id) * 0.3;
    $ai_confidence = getAiConfidence($tenant_id, $store_id) * 0.3;
    return round($stock_accuracy + $data_freshness + $ai_confidence);
}
```

| Score | Label |
|---|---|
| 95-100 | 🟢 Перфектна форма |
| 80-94 | 🟡 Добре, AI гадае за някои |
| 60-79 | 🟠 AI не е сигурен |
| <60 | 🔴 AI гадае |

**Винаги видим** в header на warehouse.php и simple.php. Пешо иска да го вижда расте.

---

# 9. РЕВИЗИЯ КАТО SUBSECTION

„Ревизия" е за owner/accountant — официална снимка на склада в даден момент.

```
┌─────────────────────────────────────┐
│ Ревизия — 30.04.2026                │
├─────────────────────────────────────┤
│ Общо артикули: 487                  │
│ Обща стойност (retail): €24,680     │
│ Обща стойност (cost): €12,340       │
│                                     │
│ [Експорт Excel] [Експорт PDF]       │
│                                     │
│ По категория:                       │
│ • Дрехи: 234 бр, €11,240            │
│ • Обувки: 145 бр, €8,690            │
│ • Аксесоари: 108 бр, €4,750         │
│                                     │
│ Минали ревизии:                     │
│ • 31.03 — €22,340                   │
│ • 28.02 — €20,120                   │
└─────────────────────────────────────┘
```

**В Simple Mode:** `AI: „Искаш ли ревизия на склада за счетоводителя?"` → генерира PDF → изпраща на email.

---

# 10. 12-ТЕ ПРАВИЛА (INVENTORY)

1. Never block a sale
2. Voice primary
3. Stock calculated, not manual
4. Negative stock OK
5. Statistics always work (ranges)
6. Ranges motivate precision
7. Delivery = mass boost
8. Zone Walk fills gaps
9. Double counting = cross-validation
10. Works from second 1
11. AI never says „inventory" — says „treasure hunt"
12. Ego engaged — Пешо проверява AI, не обратното

---

**КРАЙ НА DOC 11**
