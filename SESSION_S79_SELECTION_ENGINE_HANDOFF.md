# SESSION S79.SELECTION_ENGINE — HANDOFF

**Дата:** 24.04.2026
**Модел:** Opus 4.7, Chat 2 (паралелно с Chat 1.3 products.php)
**Статус:** ЗАВЪРШЕНА
**Нулев overlap с други chats** (различни файлове, различни DB таблици)
**Commit:** c0a4540
**Tag:** v0.6.0-s79-selection-engine

---

## Какво е направено (DONE)

### 1. DB миграция 20260424_001_ai_topics — APPLIED

Две нови таблици, idempotent, с FK cascades.

**ai_topics_catalog** — master каталог на AI темите
- PK: id VARCHAR(50) (напр. tax_001, price_027, floor_835)
- Колони: category, name, what, trigger_condition, data_source, topic_type (ENUM fact/reminder/discovery/comparison), country_codes, roles, plan, priority (1-8), module, embedding JSON, is_active
- 5 индекса (category, module, priority, plan, is_active)

**ai_topic_rotation** — per-tenant rotation history
- Compound UNIQUE (tenant_id, topic_id) за UPSERT идемпотентност
- FK tenant_id -> tenants(id) ON DELETE CASCADE
- FK topic_id -> ai_topics_catalog(id) ON DELETE CASCADE
- Колони: last_shown_at, shown_count, last_module, suppressed_until

### 2. Bootstrap 1000 теми от JSON — DONE

**config/ai_topics.php** (99 реда) — idempotent UPSERT, substr() guards за VARCHAR колони, ENUM safety, error list (първите 10).

3 функции:
- bootstrapTopicsFromJson($path)
- getTopicById($id)
- getTopicsByCategory($cat, $active_only)

**Резултат:** 1000/1000 inserted, 0 skipped, 0 errors.
**Разпределение (top 15):** promo 63, delivery 50, order 50, floor 43, biz 40, fashion 30, shoes 30, hol 30, acc 25, anomaly 25, aw 25, pos 25, sup 25, cust 25, ss 25.

### 3. MMR Selection Engine — DONE

**selection-engine.php** (157 реда) — 4 функции:

**selectTopicsForTenant($tenant_id, $module, $max, $lambda)**
- Чете tenants.plan_effective, country, language (schema diff fix спрямо prompt)
- Candidate filter: module IN (?, home) + country match + plan match
- Suppression check (suppressed_until > NOW())
- Score formula: relevance * 0.4 + freshness * 0.3 + trigger_match * 0.3
  - relevance = (9 - priority) / 8 (p1=1.0, p8=0.125)
  - freshness = min(hours_since/24, 3) / 3
  - trigger_match = 1.0 placeholder (compute-insights ще оценява реалните условия)
- MMR greedy: diversity penalty 0.4 при повторна категория, lambda=0.75 default

**recordTopicShown($tenant_id, $topic_id, $module, $suppress_hours=6)** — UPSERT
**getTopicStats($tenant_id)** — unique_shown, total_impressions, currently_suppressed, last_activity
**resetTenantRotation($tenant_id)** — debug helper

### 4. Тестове (tenant 7) — ALL GREEN

| Test | Резултат |
|---|---|
| Selection 5 теми | 5 върнати, 5 различни категории OK |
| Suppression работи | Записана тема не се връща в следващ call OK |
| Stats correctly aggregated | unique_shown=1, total_impressions=1 OK |
| module=products scope | 5 теми OK |
| Reset rotation | 1 deleted, stats zero OK |
| 10 теми от 1000 | 10 различни категории OK |

---

## Discrepancies спрямо prompt-а (FIXED в кода)

| Prompt предполагаше | Реалност в DB | Fix |
|---|---|---|
| tenants.plan | tenants.plan_effective съществува | Ползвам plan_effective (trial-aware) |
| tenants.country_code | tenants.country | Fix |
| tenants.lang | tenants.language | Fix |
| plan=business в каталога | plan ENUM е free/start/pro | Логика: plan IN (business,free) = универсален |

**FK upgrade:** prompt нямаше FK към ai_topics_catalog, добавих CASCADE — orphan prevention.
**UNIQUE upgrade:** prompt имаше INDEX но UPSERT изисква UNIQUE — без нея counter винаги щеше да е 1.

---

## REWORK QUEUE (нови entries)

1. **S94+** — selectTopicsForTenant приема $strict_module=false flag. Когато true -> филтрира САМО темите с module=?, без home fallback.
2. **S94+** — реални trigger evaluation. trigger_condition колона има стрингове като rev12m>80pct_threshold. Нужен evaluator.
3. **S97+** — embedding JSON колона празна. MMR би бил по-добър със semantic similarity. Ако бюджетът позволи — Gemini embeddings per topic.what.
4. **S100+** — Monitoring. Ако tenant изчерпи всички теми в 1 категория -> fallback: когато suppressed_count/total > 0.8 -> reset най-старите.

---

## Integration points за следващи сесии

- **S95 Simple Mode (life-board.php)** — Пешо AI chat ще извиква selectTopicsForTenant($tid, home, 3, 0.8) за 3 теми
- **S96 Life Board визуализация** — Neon Glass карти per тема
- **S94 /ai-action.php** — може да използва getTopicById() за tap->detail overlay
- **compute-insights.php** — ще запълни placeholder trigger_match=1.0 с реален signal scoring

---

## Файлове (нови — commit c0a4540)

- migrations/20260424_001_ai_topics.up.sql (41 реда)
- migrations/20260424_001_ai_topics.down.sql (4 реда)
- config/ai_topics.php (99 реда)
- selection-engine.php (157 реда)
- SESSION_S79_SELECTION_ENGINE_HANDOFF.md (този файл)

**Не пипани:** products.php, chat.php, compute-insights.php, config/database.php, config/helpers.php, lib/Migrator.php, android/, capacitor*.
