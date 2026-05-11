# SIGNALS_CATALOG_v1.md

Каталог на всички 1000 типа AI сигнали + body-template за всеки.

**Source**: `ai-topics-catalog.json` (1000 topics in 67 categories) + реално implementирани pf*() в `compute-insights.php`.
**Цел**: за всеки сигнал — какво показва, какви колони в `ai_insights` попълва, и body-template който генерира **обяснителен** текст (различен от title).

---

## 0. Глобална схема: ai_insights колони

| Колона | Тип | Какво носи |
|---|---|---|
| `topic_id` | VARCHAR(80) | стабилен id за de-dup и body-routing (напр. `zero_stock_with_sales`, `payment_due_42`). |
| `category` | VARCHAR(50) | категория от каталога (`inventory`, `pricing`, `tax`, …). Used for UI grouping. |
| `module` | ENUM | `home` / `products` / `warehouse` / `stats` / `sale`. |
| `urgency` | ENUM | `critical` / `warning` / `info` / `passive`. |
| `fundamental_question` | ENUM | `loss` / `loss_cause` / `gain` / `gain_cause` / `order` / `anti_order` (6 questions). |
| `plan_gate` | ENUM | `free` / `start` / `pro`. |
| `role_gate` | varchar | csv роли. |
| `title` | varchar(255) | pill text — кратко (≤80ch). |
| `detail_text` | varchar(500) | **BODY** — какво пишем тук = output на `v2generateBody()`. |
| `value_numeric` | DECIMAL(12,2) | главното число (EUR, count, %). |
| `product_count` | INT | брой засегнати артикули. |
| `product_id` | INT | топ-1 артикул (когато сигналът има фокус). |
| `supplier_id` | INT | топ-1 доставчик (за supplier-bound сигнали). |
| `data_json` | JSON | пълни данни: `{items: [...], count, totals...}`. **Източникът на body-та.** |
| `action_label` / `action_type` / `action_url` / `action_data` | — | CTA. |
| `expires_at` | datetime | TTL: loss=3д, loss_cause=5д, gain/gain_cause=7д, order=5д, anti_order=14д. |

---

## 1. Body-шаблонна семантика

Title казва **колко/кои** — body казва **защо ти трябва това, какво да направиш, и каква е цената на бездействие**. Body НЕ повтаря title.

3 секции в body:

1. **Контекст** — конкретни числа/имена от `data_json` (топ 3 елемента, тотал, тенденция).
2. **Защо** — какво губи/печели магазинът ако нищо не направи.
3. **Какво да направиш** — конкретно действие, обвързано с `action_*`.

Дължина: 2-4 изречения. Tone: умен служител, не AI. Без emoji в body.

---

## 2. Имплементирани сигнали (compute-insights.php) — body templates

### 2.1 LOSS (Какво губиш)

#### `zero_stock_with_sales` — Бестселъри на нула
- **cat**: inventory · **urgency**: critical · **FQ**: loss
- **Fields**: `value_numeric` = EUR/ден загуба, `product_count`, `data_json.items[].{name, code, sold_30d}`, `data_json.lost_per_day`
- **Body template**:
  > `{N}` артикула не са в наличност, а продаваме ~`{daily_total}` бр/ден от тях. Топ загуба: `{top3.name}` (`{top3.sold_30d}`бр/30д). Всеки ден без поръчка губим ~`{lost_per_day} EUR`. Натисни „Поръчай" — батч поръчка към доставчиците.

#### `below_min_urgent` — Под минимум
- **cat**: inventory · **urgency**: warning · **FQ**: loss
- **Fields**: `product_count`, `data_json.items[].{name, qty, min}`
- **Body**:
  > `{N}` артикула са под зададения минимум. Най-критично: `{top3.name}` — само `{qty}` бр срещу мин `{min}`. На текущия темп ще свършат за `{est_days}` дни. Поръчай за да не падат на нула.

#### `running_out_today` — Свършват днес
- **cat**: inventory · **urgency**: warning · **FQ**: loss
- **Fields**: `product_count`, `data_json.items[].{name, qty, avg_daily}`
- **Body**:
  > `{N}` артикула имат запас ≤ дневните продажби — днес най-вероятно ще свършат. Например `{top1.name}` — `{qty}`бр при `{avg_daily}`бр/ден. Спешна поръчка днес или ще загубим утрешните клиенти.

### 2.2 LOSS_CAUSE (От какво губиш)

#### `selling_at_loss` — Под себестойност
- **cat**: pricing · **urgency**: critical · **FQ**: loss_cause
- **Fields**: `value_numeric` = total loss/брой продаден, `data_json.items[].{name, cost, retail, loss_per_unit}`
- **Body**:
  > `{N}` артикула имат retail < cost. Топ: `{top1.name}` — губим `{loss_per_unit} EUR` на брой (cost `{cost}` / retail `{retail}`). Всяка продажба = чиста загуба. Промени цените или маркирай артикулите неактивни.

#### `no_cost_price` — Без себестойност
- **cat**: pricing · **urgency**: info · **FQ**: loss_cause
- **Fields**: `value_numeric` = брой, `data_json.with_sales`, `data_json.items[].{name, sold_30d}`
- **Body**:
  > `{N}` артикула без записана доставна цена. От тях `{with_sales}` се продават — не знаем дали печелим или губим. Топ загадка: `{top1.name}` (`{sold_30d}`бр/30д). Импортирай costs от последна доставка или въведи ръчно.

#### `margin_below_15` — Нисък марж
- **cat**: pricing · **urgency**: warning · **FQ**: loss_cause
- **Fields**: `product_count`, `data_json.items[].{name, margin_pct}`
- **Body**:
  > `{N}` артикула имат марж под 15% — тънка червена линия. Най-нисък: `{top1.name}` — `{margin_pct}%`. Под този праг един дисконт или връщане яде печалбата. Прегледай покачване на retail или смяна на доставчик.

#### `seller_discount_killer` — Продавач с отстъпки
- **cat**: staff · **urgency**: warning · **FQ**: loss_cause
- **Fields**: `value_numeric` = lost EUR, `data_json.items[].{user_id, name, avg_disc, items, lost_money}`
- **Body**:
  > `{N}` продавачи дават средно >20% отстъпка. Топ: `{top1.name}` — `{avg_disc}%` ср., `{items}` продажби за 30д, ~`{lost_money} EUR` неполучени. Прегледай разрешените limits в техния профил.

#### `delivery_anomaly_{supplier_id}` — Доставчик системно не дописва
- **cat**: delivery_anomaly_pattern · **urgency**: warning · **FQ**: loss_cause
- **Fields**: `supplier_id`, `product_count` = брой mismatches, `data_json.items[]` = списък доставки
- **Body**:
  > Доставчик `{supplier_name}` има `{mismatch_count}` доставки с разлика между поръчано и получено за последните 60 дни. Pattern-ът е стабилен — не е инцидент. Прегледай с тях писмено или сменям supplier-а.

### 2.3 GAIN (Какво печелиш)

#### `top_profit_30d` — Топ печалба
- **cat**: biz_revenue · **urgency**: info · **FQ**: gain
- **Fields**: `value_numeric` = total profit, `product_id` = топ-1, `data_json.items[].{name, profit}`
- **Body**:
  > Топ-10 артикула донесоха `{total_profit} EUR` печалба за 30 дни. Шампион: `{top1.name}` (`{top1.profit} EUR`). Заедно правят `{share}%` от цялата печалба. Внимавай да не свършат — без тях ще усетим спад.

#### `profit_growth` — Растяща печалба
- **cat**: biz_revenue · **urgency**: info · **FQ**: gain
- **Fields**: `value_numeric` = брой, `data_json.items[].{name, profit_now, profit_prev, growth_pct}`
- **Body**:
  > `{N}` артикула удвояват печалбата си спрямо предходния период. Топ: `{top1.name}` — от `{profit_prev} EUR` на `{profit_now} EUR` (+`{growth_pct}%`). Прицели се в зареждане и витрина за тях.

#### `volume_discount_{sid}_{pid}` — Доставчик дава отстъпка
- **cat**: volume_discount_detected · **urgency**: info · **FQ**: gain
- **Fields**: `value_numeric` = pct, `supplier_id`, `product_id`
- **Body**:
  > `{supplier_name}` ни даде `{pct}%` по-добра цена за `{product_name}` спрямо средната от последните 90 дни. Зареди повече или преразгледай ценообразуването — има място за по-висок марж.

#### `stockout_risk_reduced` — Отново в наличност
- **cat**: stockout_risk_reduction · **urgency**: passive · **FQ**: gain
- **Fields**: `product_count`, `data_json.items[].{name, in_stock, sold_30d}`
- **Body**:
  > `{N}` бестселъра, които бяха на нула, вече са попълнени. Топ: `{top1.name}` — `{in_stock}` в наличност, `{sold_30d}`бр/30д. Възобнови витрина/проактивни препоръки за тях.

### 2.4 GAIN_CAUSE (От какво печелиш)

#### `highest_margin` — Топ марж
- **cat**: pricing · **urgency**: info · **FQ**: gain_cause
- **Fields**: `value_numeric` = top margin pct, `product_id`, `data_json.items[].{name, margin_pct}`
- **Body**:
  > Топ-10 артикула с марж между `{min_margin}%` и `{max_margin}%`. Шампион: `{top1.name}` (`{margin_pct}%`). Това е „златен резерв" — продажба тук компенсира 3 продажби с нисък марж. Сложи ги на видно място.

#### `trending_up` — В ръст
- **cat**: trend · **urgency**: info · **FQ**: gain_cause
- **Fields**: `value_numeric` = брой, `data_json.items[].{name, avg_7d, avg_30d, growth_pct}`
- **Body**:
  > `{N}` артикула продават `{growth_avg}%` повече през последните 7 дни спрямо 30-дневната си средна. Топ: `{top1.name}` — от `{avg_30d}`бр/ден на `{avg_7d}`бр/ден (+`{growth_pct}%`). Зареди преди да свършат.

#### `loyal_customers` — Лоялни клиенти
- **cat**: customer · **urgency**: info · **FQ**: gain_cause
- **Fields**: `value_numeric` = total EUR, `data_json.items[].{customer_id, name, purchases, total}`
- **Body**:
  > `{N}` клиенти направиха ≥3 покупки за 60 дни — общо `{total} EUR`. Топ: `{top1.name}` (`{purchases}` покупки, `{total}` EUR). Това е ~`{share}%` от оборота. Помисли за лоялна оферта/SMS.

#### `basket_driver` — Теглят кошницата
- **cat**: product_mix · **urgency**: info · **FQ**: gain_cause
- **Fields**: `value_numeric` = basket_count, `product_id`, `data_json.items[].{name, basket_count}`
- **Body**:
  > `{N}` артикула често пътуват в комплект — присъстват в ≥3 multi-item покупки. Топ: `{top1.name}` — в `{basket_count}` различни сметки. Сложи ги до касата или предложи комплект.

#### `size_leader` — Лидер-вариация
- **cat**: product_mix · **urgency**: info · **FQ**: gain_cause
- **Fields**: `value_numeric` = брой, `product_id`, `data_json.items[].{parent_name, variation, qty_sold}`
- **Body**:
  > За `{N}` артикула има 1 размер/цвят, който продава ≥3 пъти повече от останалите. Пример: `{top1.parent_name}` — вариация „`{top1.variation}`" с `{qty_sold}`бр. Зареди именно тази вариация, не „по равно".

#### `new_supplier_first_{id}` — Първа доставка от нов доставчик
- **cat**: new_supplier_first_delivery · **urgency**: passive · **FQ**: gain_cause
- **Fields**: `supplier_id`, без `value_numeric`
- **Body**:
  > Първа доставка от `{supplier_name}`. Прегледай качество, lead time, и pricing — има още малко база за оценка. След 3-та доставка системата ще даде reliability score.

### 2.5 ORDER (Поръчай!)

#### `bestseller_low_stock` — Бестселъри с ниска наличност
- **cat**: inventory · **urgency**: warning · **FQ**: order
- **Fields**: `product_count`, `data_json.items[].{name, code, qty, sold_30d}`
- **Body**:
  > `{N}` бестселъра с наличност ≤ 1.5× минимум, но с продажби ≥5/30д. Топ: `{top1.name}` — `{qty}`бр в наличност, `{sold_30d}`бр/30д темп. Подготвена е батч поръчка с всички ID-та — натисни и изпрати.

#### `lost_demand_match` — Артикули, които клиенти питат
- **cat**: demand · **urgency**: warning · **FQ**: order
- **Fields**: `value_numeric` = total_asks, `product_count`, `data_json.items[].{query, name, times}`
- **Body**:
  > Клиенти питаха за `{N}` артикула общо `{total_asks}` пъти за последните 14д — нямахме ги в наличност. Топ заявка: „`{top1.query}`" (`{times}` пъти, мач: `{top1.name}`). Поръчай или предложи аналог.

#### `order_stale_{po_id}` — Поръчка без доставка
- **cat**: order_stale_no_delivery · **urgency**: warning · **FQ**: order
- **Fields**: `supplier_id`
- **Body**:
  > Поръчка към `{supplier_name}` стои `{days_old}` дни без получена доставка. Системата я маркира stale. Обади се за статус — може да е изгубена или забавена.

### 2.6 ANTI_ORDER (НЕ поръчвай)

#### `zombie_45d` — Замразен капитал
- **cat**: cash · **urgency**: info · **FQ**: anti_order
- **Fields**: `value_numeric` = total frozen EUR, `data_json.items[].{name, qty, days_stale, frozen_money}`
- **Body**:
  > `{N}` артикула не са продавани повече от 45 дни. Замразен капитал: `{total_frozen} EUR`. Топ „мъртвец": `{top1.name}` — `{qty}`бр × `{days_stale}` дни. Промо -20% освобождава касата, връща оборот.

#### `declining_trend` — В спад
- **cat**: trend · **urgency**: info · **FQ**: anti_order
- **Fields**: `value_numeric` = брой, `data_json.items[].{name, avg_7d, avg_30d, down_pct}`
- **Body**:
  > `{N}` артикула продават `{down_avg}%` по-малко през последните 7 дни спрямо 30-дневната средна. Топ спад: `{top1.name}` — от `{avg_30d}`бр/ден на `{avg_7d}`бр/ден (-`{down_pct}%`). НЕ зареждай — може да е сезонен край.

#### `high_return_rate` — Висок процент връщания
- **cat**: quality · **urgency**: warning · **FQ**: anti_order
- **Fields**: `product_count`, `product_id`, `data_json.items[].{name, sold, returned, rate}`
- **Body**:
  > `{N}` артикула имат >15% връщания за 30д. Топ: `{top1.name}` — `{returned}`/`{sold}` връщания (`{rate}%`). Преди да поръчаш още — провери защо (размер, качество, описание). Възможна е грешка в каталога.

### 2.7 LOSS — Reminder (платежни)

#### `payment_due_{delivery_id}` — Плащане наближава
- **cat**: payment_due_reminder · **urgency**: warning|critical · **FQ**: loss
- **Fields**: `value_numeric` = сума, `supplier_id`
- **Body**:
  > Плащане към `{supplier_name}` за `{total} {currency}` — `{days_left}` дни {до|просрочено}. Без плащане доставчикът може да забави нови доставки или да добави такса. Прехвърли сега и маркирай платена.

---

## 3. Каталожни сигнали (1000) — generic body-templates per category

За всеки `cat` от каталога (когато се имплементира в бъдеще), `v2generateBody()` ще използва **generic template** базиран на категорията. Тук са изчерпателните 67 категории.

### 3.1 Финанси / счетоводство
| cat | броя | какво | body-template-генерик |
|---|---|---|---|
| `tax` | 23 | VAT, фискални дни, тримесечия | "До `{days}` дни — `{event}`. Текуща база: `{value} EUR` оборот. Подготви документите или прехвърли с акаунтанта." |
| `acc` | 25 | bookkeeping, dom. fakturi | "За `{period}` имаш `{count}` фактури/документа в `{status}`. Топ доставчик: `{supplier_name}`. Прегледай преди затварянето на периода." |
| `cash` | 24 | кеш, gap, run-rate | "Кешов баланс: `{balance} EUR`. Burn rate `{burn}`/ден ⇒ ~`{days_left}` дни run-way. Прехвърли от друга сметка или ускори събиране." |

### 3.2 Бизнес-здраве
| cat | броя | body-template |
|---|---|---|
| `biz` | 40 | "Дневни/седмични продажби: `{value} EUR` (`{trend}` спрямо `{prev}`). `{interpretation}`." |
| `biz_health` | 4 | "Сравнение спрямо миналата година: `{value_now}` vs `{value_yoy}` (`{pct}%`). `{interpretation}`." |
| `biz_revenue` | 2 | "Milestone: `{event}` на `{date}`. До сега: `{count}` продажби, `{revenue} EUR`." |

### 3.3 Запаси / магазин
| cat | броя | body-template |
|---|---|---|
| `wh` | 19 | "В склада: `{count}` артикула × `{value} EUR` обща стойност. `{interpretation}` (turnover, негативни, expiring)." |
| `xfer` | 20 | "Магазин A: нула `{product}`. Магазин B: `{qty}` бр. Прехвърли `{suggest_qty}` бр — `{est_lost_sales}` потенциални продажби." |
| `stock` | 1 | "Преглед на запас за `{focus}`. `{count}` бр × `{value}`." |

### 3.4 Цени
| cat | броя | body-template |
|---|---|---|
| `price` | 24 | "Цена/себестойност alert: `{detail}`. Засегнати `{count}` артикула." |
| `price_change` | 1 | "Промяна на цена преди `{days}` дни — `{old} → {new} EUR`. Ефект: `{sales_change}` продажби." |

### 3.5 Доставчици / поръчки / доставки
| cat | броя | body-template |
|---|---|---|
| `sup` | 25 | "`{supplier_name}` profile: lead time `{lead}` дни, reliability `{score}`. `{interpretation}`." |
| `supplier` | 1 | "Реален lead time от `{supplier_name}`: `{lead_actual}` дни (vs `{lead_expected}` договорен)." |
| `delivery` | 50 | "Доставка #`{id}` от `{supplier}` — `{status}`. Дължи се на `{date}`. Очаквана разлика: `{est_diff}`." |
| `order` | 50 | "Поръчка #`{id}` — `{status}`. `{interpretation}` спрямо daily темп." |
| `ops` | 22 | "Опа: `{operational_event}`. Топ ефект: `{detail}`." |

### 3.6 Клиенти / лоялност
| cat | броя | body-template |
|---|---|---|
| `cust` | 25 | "Клиент `{name}` — `{event}`. История: `{purchases}` покупки, `{total} EUR`. Препоръка: `{action_hint}`." |
| `loyalty_repeat` | 8 | "`{N}` клиенти направиха `{n}`-та покупка за `{period}` — milestone." |
| `loyalty_churn` | 5 | "`{name}` не пазарува `{days}` дни (норма `{avg_days}`). Изпрати SMS или промо." |
| `loyalty_program` | 5 | "Лоялна програма: `{N}` клиенти близо до VIP праг. Топ: `{name}` — `{spent}/{threshold}`." |
| `loyalty_basket` | 7 | "Лоялни клиенти купуват средно `{items}` артикула — `{N}` пъти повече от нови. Топ комплект: `{pair}`." |
| `feedback` | 1 | "AI съвет от `{days}` дни преди: `{advice}`. Резултат: `{measured_outcome}`." |

### 3.7 Продукти — категории мода
| cat | броя | body-template |
|---|---|---|
| `new` | 20 | "Нов артикул „`{name}`" — `{days}` дни в каталога, `{sales}` продажби. `{interpretation}` спрямо новите средно." |
| `new_product` | 1 | "Първа седмица тишина: `{name}` — без продажби 7-14д при категориен среден `{cat_avg}`." |
| `fashion` | 30 | "Колекция: `{collection}` — `{age_days}` дни на пазара, `{sellthrough}%` sell-through." |
| `shoes` | 30 | "Обувки: размер `{size}` — `{share}%` от продажбите. Имбаланс: `{detail}`." |
| `lingerie` | 15 | "Бельо: чашка `{size}` — `{share}%`. Балансиране на наличности: `{interpretation}`." |
| `sport` | 15 | "Спорт: `{subcat}` — `{trend}` за `{period}`. `{interpretation}`." |
| `acc` | 25 | "Аксесоар: `{name}` — често impulse buy. Средно `{share}%` от чек." |
| `size` | 20 | "Размер `{size}` — `{share}%` от продажбите за `{cat}`. `{interpretation}`." |

### 3.8 Сезонност / празници / време
| cat | броя | body-template |
|---|---|---|
| `ss` | 25 | "Сезон: `{season}` — `{days_to_peak}` дни до пик. Готовност `{readiness}%`." |
| `aw` | 25 | "Есен/Зима: `{event}` идва. Подготвен инвентар: `{prep_score}%`." |
| `hol` | 30 | "Празник `{holiday}` на `{date}` (`{days_left}` дни). Исторически spike: `{historical_uplift}%`." |
| `season_calendar` | 5 | "Календарен pattern: `{pattern_name}` — обикновено `{trend}` през `{month}`." |
| `season_holiday` | 5 | "Празник prep: `{holiday}` — препоръка `{action}` `{days_before}` дни преди." |
| `season_transition` | 5 | "Преход колекция: `{from} → {to}` на `{date}`. Сегашен summer/winter mix: `{mix}%`." |

### 3.9 Време
| cat | броя | body-template |
|---|---|---|
| `weather_temp` | 10 | "Температурно събитие: `{event}` (макс `{tmax}°C`). Препоръка: `{action}` за `{category}` артикули." |
| `weather_rain` | 8 | "Дъжд `{rain_prob}%` за `{date}`. Очаквай `{traffic_change}` трафик. Витрина: `{suggestion}`." |
| `weather_event` | 5 | "Време: `{event_type}` (`{magnitude}`). Препоръка: `{action}`." |
| `weather_season_shift` | 7 | "Промяна сезон след `{days}` дни — `{from}°C → {to}°C`. Подготви: `{prep_action}`." |
| `time` | 19 | "Часови pattern: `{hour_event}` — `{interpretation}` спрямо средния ден." |

### 3.10 Промо / маркетинг
| cat | броя | body-template |
|---|---|---|
| `promo` | 63 | "Промо `{name}` — `{phase}`. Резултат до сега: `{sales}` продажби, uplift `{uplift_pct}%` vs baseline." |
| `display_front` | 8 | "Витрина (главна): `{spot}` — препоръка „`{product}`" (`{reason}`)." |
| `display_zone` | 7 | "Зона: `{zone}` — `{status}`. Препоръка: `{action}`." |
| `display_visual` | 5 | "Визуално: `{element}` — `{interpretation}` (color, height, story)." |
| `floor` | 43 | "На пода: `{event}` — `{count}` пъти за `{period}`. Lost demand top: `{detail}`." |
| `basket` | 20 | "Кошница: `{detail}`. Топ комбо: `{pair}` (`{count}` пъти заедно)." |
| `pos` | 25 | "POS: `{event}` — пик/затишие в `{hour}` ч. Усреднено `{avg}`." |

### 3.11 Връщания
| cat | броя | body-template |
|---|---|---|
| `ret` | 20 | "Връщания: `{N}` артикула — топ `{name}` (`{rate}%`). Причина: `{reason}`." |
| `return_reason` | 7 | "Причина: `{reason_label}` — `{share}%` от връщанията. Възможна корекция: `{action}`." |
| `return_cost` | 4 | "Връщания за месеца: `{count}` × `{cost} EUR` = `{total} EUR` загуба (директна+скрита)." |
| `return_prevention` | 4 | "Превенция: `{action}` (размерна табла, описание, фото). Очакван effekt: `{est_reduction}%`." |
| `return_supplier` | 5 | "Доставчик `{supplier}` — return rate `{rate}%` (vs средно `{avg}%`). Прегледай качество." |

### 3.12 Персонал
| cat | броя | body-template |
|---|---|---|
| `staff` | 19 | "Продавач `{name}` — `{metric}`: `{value}` (vs средно `{avg}`). `{interpretation}`." |
| `staff_cost` | 3 | "Заплатен фонд / оборот: `{ratio}%` за `{period}`. Промяна: `{trend}`." |
| `staff_performance` | 7 | "`{name}` — `{kpi}` `{value}`. Класиране: `{rank}/{total}`." |
| `staff_schedule` | 6 | "Смяна: `{shift}` — `{coverage}` спрямо очакван трафик." |
| `staff_training` | 4 | "Обучение: `{topic}` — `{coverage}%` от екипа покрит." |
| `labor` | 20 | "Трудово право/часове: `{event}` — `{detail}` (риск на нарушение). Действие: `{action}`." |

### 3.13 Разходи
| cat | броя | body-template |
|---|---|---|
| `expense_rent` | 5 | "Наем: `{rent} EUR` × `{turnover_ratio}%` от оборот (норма `{healthy_ratio}%`)." |
| `expense_fixed` | 5 | "Фиксирани разходи: `{total} EUR`/мес. Break-even: `{daily_target} EUR`/ден." |
| `expense_per_sale` | 5 | "Cost per transaction: `{cps} EUR`. Топ компонент: `{top_cost_cat}`." |
| `expense_compare` | 5 | "Разходи vs продажби: `{exp_growth}%` срещу `{sales_growth}%`. `{interpretation}`." |

### 3.14 Веро/детайли / Cross-store / Data quality
| cat | броя | body-template |
|---|---|---|
| `ws` | 10 | "Wholesale: `{N}` поръчки × `{value} EUR` за `{period}`. `{interpretation}`." |
| `ws` (overdue) | — | "WS клиент `{name}` не е платил `{days}` дни. Просрочена сума: `{value} EUR`." |
| `cross_store` | 1 | "Магазин A: zombie на `{product}`. Магазин B: lost demand на същия. Прехвърли — `{est_value}`." |
| `data_quality` | 2 | "Качество: `{N}` артикула без `{field}` (`{share}%` от каталога). `{action}`." |
| `onboard` | 15 | "Onboarding: ден `{day}` — `{milestone_status}`. Следваща стъпка: `{next_action}`." |

### 3.15 Аномалии
| cat | броя | body-template |
|---|---|---|
| `anomaly` | 25 | "Аномалия: `{anomaly_type}` на `{when}`. Детайли: `{detail}`. Възможна причина: `{hypothesis}`." |

---

## 4. v2generateBody() — implementation contract

```php
v2generateBody(array $ins): string
```

**Input**: row from `ai_insights` (associative) with keys: `topic_id`, `category`, `fundamental_question`, `urgency`, `title`, `data_json` (raw JSON string), `value_numeric`, `product_count`, `product_id`, `supplier_id`, `action_label`.

**Routing logic**:
1. Decode `data_json` → `$d` (или null).
2. Get prefix от topic_id (`zero_stock_with_sales` → `zero_stock_with_sales`; `payment_due_42` → `payment_due`; `delivery_anomaly_3` → `delivery_anomaly`).
3. `switch ($prefix)` → специфичен body template (от §2).
4. **Fallback** → generic body по `category` (от §3) или `fundamental_question`.
5. Never return empty — винаги поне contextual sentence от title + count.

**Output**: 1-4 изречения plain text (без HTML tags; `<b>` markers заменяме с `**…**` markdown ако трябва). ≤ 500 chars (ai_insights.detail_text limit).

---

## 5. Покритие

| Категория покрита | Брой topics в каталога | Брой имплементирани в `compute-insights.php` |
|---|---|---|
| Total | 1000 | ~22 (specific topic_ids) + 5 dynamic-id types (payment_due_*, delivery_anomaly_*, volume_discount_*, order_stale_*, new_supplier_first_*) |
| Текущо в DB tenant=7 | — | 16 unique topic_ids активни |

Останалите 978 сигнала са **планирани** — каталогът ги описва; `v2generateBody()` ще ги покрие чрез generic templates по `category` когато се имплементират.
