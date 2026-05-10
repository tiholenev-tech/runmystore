# DOCUMENT_INDEX — Каталог на всички документи в repo

**Цел:** Шеф-чатът знае какви документи има, без да ги чете bulk при boot. Чете САМО когато конкретна задача го изисква.

---

## CRITICAL (винаги при boot)
- SHEF_HANDOFF_<latest>_EOD.md — вчерашен handoff (~200 реда)
- STATE_OF_THE_PROJECT.md — live P0 list (~250 реда)
- DOCUMENT_INDEX.md — този файл (~80 реда)

## ON-DEMAND (чети само когато задачата го изисква)

### Стратегия и архитектура
- MASTER_COMPASS.md — orchestrator, LOGIC LOG history. Чети при: deep context recovery, history audit, dispute resolution.
- DESIGN_REFACTOR_STRATEGY.md (TBD) — component library plan. Чети при: визуален rewrite задача.
- TECHNICAL_REFERENCE_v1.md — DB schema canonical. Чети при: миграции, нови таблици, schema въпроси.
- TECHNICAL_ARCHITECTURE_v1.md — overall stack. Чети при: infra решения, scaling.

### Визуален дизайн
- DESIGN_SYSTEM_v4.0_BICHROMATIC.md (Bible v4.1, 2748 реда) — tokens, components, sacred patterns. Чети при: визуален rewrite, mockup verification.
- DESIGN_SYSTEM.md / DESIGN_SYSTEM_v1.md / v3 — DEPRECATED.
- VISUAL_GATE_SPEC.md v1.3 — visual validation infrastructure. Чети при: design rewrite задача.
- CLAUDE_CODE_DESIGN_PROMPT.md — wrapper за CC дизайн сесии. Чети при: пускане на CC за визуална работа.

### Модули — спецификации
- DELIVERIES_FINAL_v3_COMPLETE.md (10552 реда) — модул "Доставки" full spec. Чети при: имплементация на deliveries.
- DOCUMENTS_LOGIC.md v1.1 — 16 типа документи. Чети при: ЗДДС, фактури, sale.php B2B.
- ORDERS_DESIGN_LOGIC.md — модул "Поръчки". Чети при: orders.php имплементация.
- PRODUCTS_DESIGN_LOGIC.md — products.php логика. Чети при: products rewrite.
- INVENTORY_v4.md / INVENTORY_HIDDEN_v3.md — inventory + Закон №6. Чети при: inventory работа.
- AUTO_PRICING_DESIGN_LOGIC.md — pricing rules. Чети при: ценообразуване задача.
- SIMPLE_MODE_BIBLE.md — Лесен режим закони. Чети при: life-board.php, "Лесен режим" работа.
- AI_STUDIO_LOGIC.md / AI_STUDIO_LOGIC_DELTA.md — AI Studio. Чети при: ai-studio.php работа.
- LOYALTY_BIBLE.md — лоялност (post-beta). Чети при: loyalty имплементация.
- COST_PRICE_INTEGRATION.md — себестойност. Чети при: cost-related задача.

### Процес
- DAILY_RHYTHM.md — daily flow. Чети при: workflow въпроси.
- DOCUMENT_PROTOCOL.md — document creation rules. Чети при: писане на нов spec.
- END_OF_DAY_PROTOCOL.md — EOD ritual. Чети при: Тихол каже "изпълни протокол за приключване".
- BOOT_TEST_FOR_SHEF.md (DEPRECATED v3) — заменено от IQ test в SHEF_RESTORE_PROMPT v4.

### Маркетинг (post-beta)
- docs/marketing/MARKETING_BIBLE_LOGIC_v1.md
- docs/marketing/MARKETING_BIBLE_TECHNICAL_v1.md
- BUSINESS_STRATEGY_v2.md
- PARTNERSHIP_SCALING_MODEL_v1.md
- RUNMYSTORE_PARTNER_PRESENTATION.md

### Интеграции / external
- STRIPE_CONNECT_AUTOMATION.md — payments
- WEATHER_INTEGRATION_v1.md — weather widget
- GEMINI_SEASONALITY.md — Gemini AI seasonal logic

### Архив (deprecated, чети само за history audit)
- SHEF_RESTORE_PROMPT_v3.md
- DESIGN_LAW.md (in docs/archived/)
- DESIGN_SYSTEM_v3_archived.md
- S95_DESIGN_KIT_HANDOFF.md
- BIBLE_v3_0_APPENDIX.md
- DOCUMENT_1_LOGIC_PART_1/2/3.md (legacy три-part split)

---

**Правило:** Преди да прочетеш ON-DEMAND документ, питай се: "Тази задача наистина ли изисква този файл?" Ако не — НЕ ЧЕТИ. Documentation overload = катастрофа на 10.05.2026 урок.

---
END
