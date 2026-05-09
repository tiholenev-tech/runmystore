# tools/stress/ecwid_simulator/

**Phase L (S130 extension) — Online order simulator (Ecwid-style).**

Симулатор за онлайн магазин, който генерира fake поръчки и ги синкронизира към `sales` таблицата на STRESS Lab tenant. Това позволява нощният робот да тества целия online flow без да зависи от реален Ecwid акаунт.

---

## 📂 Файлове

| Файл | Роля |
|---|---|
| `ecwid_simulator.py` | Генерира JSON spool файлове в `tools/stress/data/ecwid_orders/`. Дефинира статуси, типове поръчки, distribution. |
| `ecwid_to_runmystore_sync.py` | Чете spool, конвертира в `sales` + `sale_items` + `inventory_events`. |
| `__init__.py` | Маркира директорията като пакет. |
| `README.md` | Този файл. |

---

## 🚦 Поведение по подразбиране

- **`--dry-run`** — нищо не се пише в DB или spool, само JSON план.
- **`--apply`** — записва spool / DB записи.
- **Random seed** = 42 (всички генератори).
- **Tenant guard** — `assert_stress_tenant`. ENI tenant_id=7 → REFUSE.

---

## 🛠 Workflow

```bash
# 1. Генерирай 30 поръчки за днес (dry-run)
python3 ecwid_simulator.py --dry-run

# 2. Прилагане — записва в data/ecwid_orders/orders_YYYYMMDD.json
python3 ecwid_simulator.py --apply --orders 30

# 3. Black Friday spike (5x volume)
python3 ecwid_simulator.py --apply --mode blackfriday --date 2026-11-29

# 4. Sync → sales + inventory_events
python3 ecwid_to_runmystore_sync.py --apply

# 5. Sync + симулирай 15-20% returns
python3 ecwid_to_runmystore_sync.py --apply --returns
```

---

## 📊 Distribution

| Параметър | Стойност | Бележка |
|---|---|---|
| Поръчки/ден | 20-40 | normal range |
| Black Friday multiplier | 5x | `--mode blackfriday` |
| Night-time concentration | 50% | hours 22-01 |
| Return rate | 15-20% | `--returns` flag |
| GDPR consent | 95% | fake customer.gdpr_consent |

### Status weights (Ecwid → runmystore)

| Ecwid status | runmystore status | Weight | Сценарий |
|---|---|---|---|
| `PAID` | `completed` | 70% | S001, S061 |
| `PROCESSING` | `pending` | 15% | — |
| `CANCELLED` | (skipped) | 5% | S070 |
| `PAYMENT_FAIL` | `payment_failed` | 5% | S065 |
| `PARTIALLY_FULFILLED` | `partial` | 3% | S066 |
| `AWAITING_PICKUP` | `awaiting_pickup` | 2% | S069 |

### Order type weights

| Type | Weight | Сценарий |
|---|---|---|
| `REGULAR` | 60% | S061 |
| `PICKUP` | 15% | S069 |
| `ABANDONED` | 10% | S070 |
| `GIFT` | 8% | S067 |
| `B2B` | 7% | S068 |

---

## 🔗 Свързани сценарии

- **S061** — Online night order (22:00-02:00 distribution)
- **S062** — Black Friday spike (5x volume)
- **S063** — Return after 7 days
- **S064** — GDPR delete request (consent + redaction)
- **S065** — Payment fail (no inventory decrement)
- **S066** — Partial fulfillment (back-order)
- **S067** — Gift order (different recipient)
- **S068** — B2B online wholesale (volume discount)
- **S069** — Cross-store pickup (awaiting_pickup status)
- **S070** — Abandoned cart (skipped from sync)

---

## ⚠️ Известни ограничения

1. **Catalog discovery** — `fetch_catalog` пробва три варианта на `products` колоните (`price`, `sale_price`, `retail_price`). Ако нито един не работи → fallback към synthetic каталог от 50 артикула.

2. **GDPR redaction** — за S064 е placeholder. Реален GDPR job-а трябва да се напише отделно (заявка от админа, не fully автоматичен).

3. **Cross-store pickup** — `fulfillment_store_id` все още е `None` от симулатора. Реалният assignment изисква достъп до `stores` таблицата + наличности per store. **TODO** в Phase R.

4. **inventory_events колонна структура** — sync скриптът използва `discover_columns` и пропуска липсващи полета без грешка. Ако `inventory_events` няма колоните `delta_quantity`, `type`, `source`, `ref_id`, `notes` → записът ще е празен.

5. **Refund flow (S063)** — UPDATE-ва `sales.status='refunded'` директно. Бъдеща версия трябва да използва отделна `refunds` таблица (ако се добави).

---

## 🛡 Iron Law

- **Никога не пише извън `tools/stress/`** освен JSON spool в `tools/stress/data/ecwid_orders/`.
- **Никога не пише в production DB** — `assert_stress_tenant` отказва ако tenant != STRESS Lab.
- **Никога не модифицира ENI tenant_id=7**.
