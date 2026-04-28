# SESSION S88 — AIBRAIN.PUMP HANDOFF (Code Code #2)

**Date:** 2026-04-28
**Author:** Claude Code (Code #2)
**Lock:** DISJOINT с Code #1 + Opus
**Туитнат scope:** compute-insights.php, selection-engine.php, build-prompt.php (минимални),
tools/seed/insights_populate.py, ai_insights DML.

---

## ✅ DOD STATUS

| Изискване | Статус |
|---|---|
| pfHighestMargin Cat A=100%, Cat D=100% (52/52) | ✅ Run #22 |
| 30+ live ai_insights tenant=7 (5 на FQ) | ✅ 39 живи · loss=5, loss_cause=8, gain=5, gain_cause=8, order=7, anti_order=6 |
| action_label + action_type + action_data NOT NULL за всички 39 | ✅ |
| Bug #2 (q1-q6 празни) verified | ✅ — q1=20, q2=24, q3=20, q4=26, q5=24, q6=22 items в loadSections |
| 3+ commits pushed | ✅ (виж по-долу) |
| 0 file conflicts с Code #1 | ✅ — само compute-insights.php / tools/* / ai_insights DML |

---

## 📦 COMMITS (full hashes)

1. `5bafbe4` — `S88.AIBRAIN.PFFIX: pfHighestMargin requires recent completed sale`
   - REAL_BUGS_FOUND §1 → Option B (EXISTS sale в последните 30 дни).
   - `seed_oracle.highest_margin_d_no_sales` reactivated (is_active=0 → 1).
   - 51/51 → 52/52 PASS.

2. `516b88c` — `S88.AIBRAIN.PUMP: pfUpsert auto-fills action fields + items normalization`
   - `pfDefaultAction()` per-FQ "умен служител" mapping.
   - `pfNormalizeItems()` дублира `product_id` → `id` в data_json items
     (products.php loadSections чете `it['id']`).
   - `pfUpsert` винаги пише action_label/action_type/action_data.
   - `tools/seed/insights_populate.py`: gain_cause темплейти 1 → 4, единен FQ tone,
     action_data всеки запис.
   - `tools/diagnostic/core/gap_detector.py`: pfDefaultAction/pfNormalizeItems
     добавени в NON_INSIGHT_PF.

3. `<тоя commit>` — `S88.AIBRAIN.HANDOFF: handoff doc + tenant=7 backfill notes`

---

## 🔍 ANTI-PATTERN FINDINGS (counter-intuitive)

### A. Bug #2 (q1-q6 празни) НЕ беше action-fields проблем

Hypothesis в task brief: "ти го решаваш implicitly чрез pump на ai_insights.action_label/action_type/action_data".

**Реалност:** products.php loadSections (line ~99) **не SELECT-ва action_*** въобще.
Проблемът беше в `data_json` items — компютърът пишеше `product_id`, а
loadSections чете `it['id']`:

```php
// products.php line 117
foreach (array_slice($items, 0, 4) as $it) {
    if (!isset($it['id']) || !isset($it['name'])) continue;  // ← всички items skip-ват
    ...
}
```

Решение: `pfNormalizeItems()` дублира `product_id` → `id`. Backfill skript за live
tenant=7 rows (6 rows updated). Сега q1-q6 показват реални items.

### B. action_type ENUM ограничение

Тон-mapping в task spec искаше:
- `navigate_chart`, `navigate_product`, `transfer_draft`, `dismiss`

Но `ai_insights.action_type` е ENUM('deeplink','order_draft','chat','none') — DDL,
извън scope-а ми. Решение: запазваме semantic intent в `action_data.intent`,
а ENUM маппваме към най-близката стойност:

| User intent | DB ENUM | Semantic в action_data.intent |
|---|---|---|
| order_draft | order_draft | order_draft |
| navigate_chart | deeplink | navigate_chart |
| navigate_product | deeplink | navigate_product |
| transfer_draft | order_draft | transfer_draft |
| dismiss | none | dismiss |

Ако Тихол реши че ENUM трябва да се разшири — Code #1 трябва да направи
ALTER TABLE и да обнови products.php консумера.

---

## ⚠️ FLAGS ЗА CODE #1 (products.php)

`loadSections()` в `products.php` **НЕ SELECT-ва action_label / action_type / action_data**.
Pump-ът ги напълни в DB, но мобилният UI няма да ги покаже под всеки item, докато
loadSections не патчне SELECT-а и не прехвърли данните в JSON response.

**Patch препоръка (минимален):**
```php
"SELECT topic_id, fundamental_question, title, detail_text, data_json,
        value_numeric, product_count,
        action_label, action_type, action_data        -- S88 NEW
 FROM ai_insights ..."
```

И в section building:
```php
$sections[$qkey]['action'] = [
    'label' => $ins['action_label'],
    'type'  => $ins['action_type'],
    'intent'=> json_decode($ins['action_data'], true)['intent'] ?? null,
];
```

Frontend renderer (Action button) трябва да използва `intent` (не `type`) за UI
диспетчер, защото DB ENUM е стеснен.

---

## 📊 FINAL DIAGNOSTIC (Run #22)

```
═══ DIAGNOSTIC RUN #22 — tenant=99 ═══
Total: 52 | PASS: 52 | FAIL: 0
Категория A: 100.0%   ✅
Категория B: 100.0%
Категория C: 100.0%
Категория D: 100.0%   ✅
```

---

## 📝 NOTES ЗА ТИХОЛ

- Cron `*/15` ще пуска `cron-insights.php` всеки 15 минути — pfUpsert вече попълва
  action_data автоматично. Постепенно ще се "оздравят" всички organic insights.
- Backfill SQL за tenant=7 е ad-hoc (S88.AIBRAIN.PUMP backfilled marker в action_data).
- Ако решиш че ENUM трябва extension — кажи на Code #1 да направи migration.
