# MASTER_COMPASS — UPDATE 03.05.2026

**Append-to:** /var/www/runmystore/MASTER_COMPASS.md  
**Insertion point:** Top of LOGIC CHANGE LOG section (newest entries first)  
**Author:** Шеф-чат X (TAKEOVER session)

---

## ⭐ LOGIC CHANGE LOG — 03.05.2026 (Sunday)

### Entry: MARKETING AI v1.0 INTEGRATION + ROADMAP REVISION 2

**Triggered by:** Marketing Bible v1.0 (706 + 1733 = 2,439 реда, push commit 54c4e79)  
**Decision-maker:** Тихол  
**Source:** docs/marketing/MARKETING_BIBLE_LOGIC_v1.md + MARKETING_BIBLE_TECHNICAL_v1.md

#### The Discovery
Май 2026 — Meta Marketing API MCP, TikTok Symphony Symphony API, Google PMax MCP станали публични в production (не beta). Това отключва **directly machine-callable** ad campaign creation/optimization without humans-in-the-loop.

**Преди (планирано):** Marketing AI = Phase 6/7 = 2028 release  
**Сега:** Marketing AI = Phase 1-5 = Q4 2026 — Q2 2027

#### Финално позициониране
**RunMyStore = "Inventory-aware revenue engine"**  
Не е CRM. Не е E-commerce. Не е Marketing tool. Е **all three unified чрез inventory truth** — единствено Marketing AI което знае real-time stock + prevents over-promise + auto-routes orders към store с capacity.

#### Service-as-a-Software философия
Не "AI помага на маркетолог". Е "AI **Е** маркетологът, Пешо просто approve-ва бюджет и стратегия."

#### Двата нови модула (interdependent)
1. **Marketing AI** — 6 prompt-маркетолога (от 15 → 6 след 5-AI consensus)
2. **Online Store** — Ecwid by Lightspeed integration

**Защо interdependent:** Online Store е **захранващ канал** за Marketing AI (където кампаниите landing-ват). Marketing AI без Online Store = реклама в празно. Online Store без Marketing AI = сам shop без traffic.

#### 6-те Промпт-Маркетолога
1. **Stock Hero** — кампании на high-stock items
2. **Profit Maximizer** — cross-sell/upsell на high-margin items
3. **Reactivator** — winback кампании за churned customers
4. **Trend Hunter** — seasonal/trending product promotion
5. **Local Whisperer** — geo-targeted кампании per store
6. **Vault Guard** — anti-cannibalization (защита на профитни SKU-та)

(От 15 первоначално — 9 dropped per 5-AI consensus като low-ROI или duplicate.)

#### Партньори ✅ Locked
- **Ecwid by Lightspeed** — Online Store engine (REPLACES WooCommerce + Shopify)
- **Stripe Primary** — payments + Stripe Connect for tenant routing
- **Meta Marketing API** — campaign creation + optimization
- **TikTok Symphony API** — creative auto-generation
- **Google PMax MCP** — Performance Max campaigns
- **Speedy + Econt** — CoD (cash-on-delivery) shipping

#### Partners ❌ REJECTED
- **WooCommerce** — heavy maintenance overhead, plugin hell
- **Shopify** — closed ecosystem, expensive transaction fees
- **Duda / Wix / Webflow / WordPress-based** — limited API surface, не подходят за inventory-driven Marketing AI

#### 8-слойна Architecture
1. Inventory Truth Layer (existing — sale.php + warehouse.php + deliveries.php)
2. Customer Intent Layer (existing — sales history + customer segments)
3. Routing Layer (NEW — multi-store base warehouse + 30-min lock)
4. Marketing AI Engine (NEW — 6 prompt-маркетолога)
5. Channel Adapters (NEW — Meta/TikTok/Google MCP wrappers)
6. Online Store (NEW — Ecwid integration)
7. Payment Layer (NEW — Stripe Connect)
8. Cost Control Layer (NEW — hard spend caps + tenant budgets)

#### Schema Migration Plan
- **25 нови таблици:** mkt_* (Marketing AI: campaigns, audiences, attributions, costs) + online_* (Ecwid: orders, products_sync, webhooks)
- **9 ALTER:** tenants, customers, sales, products, users, inventory, stores, promotions, loyalty_points_log
- **Migration window:** Тихол confirm 03.05 — **POST-BETA**, не pre-beta. Schema layout е готов, но няма пускане докато ENI launch не приключи.
- **REWORK QUEUE #61** added (P2 priority, post-15.05).

#### Cost Model
- AI cost target: **€0.24 / tenant / месец** (gross)
- Pricing: Lite €99-149, Standard €149-249, Pro €249-399, Enterprise €499-799
- Online Store add-on: €19-119/месец
- **Gross margin target:** 96-99%
- ARPU expected: 2.4-4× current (~€39 → €99-159)
- Year 5 target: €10M ARR / €14-17M revenue

#### Multi-store Routing Algorithm
```
1. Order arrives (online or POS)
2. Lookup base warehouse за tenant (configured store_id)
3. Check stock в base warehouse first
4. If stock = 0 → check store with most quantity
5. Lock product+qty for 30 минути (prevents double-sell)
6. If lock expires unfulfilled → release + retry routing
```

#### 10 Risks + Mitigations (документирани в Marketing Bible §10)
1. **Pesho-in-the-Middle** — POS user accidentally creates inventory drift → MITIGATION: sale.php hardening (RWQ-64) + audit trail
2. **Inventory accuracy below 95%** — Marketing AI лъже клиенти → MITIGATION: passive scoring (RWQ-63) + activation gate
3. **Spend caps breach** — campaign overspend → MITIGATION: hard caps non-negotiable (Standing Rule #27)
4. **Channel API outage** — campaigns down → MITIGATION: multi-channel fallback
5. **Attribution fraud** — fake conversions → MITIGATION: server-side validation
6. **Inventory race conditions** — multi-store double-sell → MITIGATION: 30-min locks + atomicity
7. **EU AI Act compliance Q3 2026** → MITIGATION: prep plan (RWQ-70)
8. **Tenant churn from cost** → MITIGATION: 30-day Marketing trial period
9. **Gemini API rate limits** → MITIGATION: 2-key rotation (existing)
10. **Cost overruns AI ops** → MITIGATION: confidence routing layer (Standing Rule #28)

#### Beta Plan
- **Phase 0 (май-юли 2026):** ENI launch + observe — schema migration после ENI stable
- **Phase 1 Shadow (юли-септ):** Marketing AI runs read-only, no campaigns activated
- **Phase 2 Live Тихол (окт-дек):** Marketing AI runs за самия Тихол (донела.bg), production validation
- **Phase 3 Closed Beta (Q1 2027):** 5-10 selected tenants
- **Phase 4 Public (Q2 2027):** general availability

#### Year 5 Vision
- 5,000+ Marketing AI active клиенти
- €10M ARR
- 6 markets (BG → RO → GR → EU)
- Mature 8-layer architecture
- EU AI Act compliant

---

## 🗺️ ROADMAP REVISION 2 — Replaces ROADMAP REVISION 1 (25.04.2026)

### Стара структура (REVISION 1)
- Phase A1 Foundation → A2 Operations Core → A3 Loyalty/Promotions → B Polish → C Scale → D Marketing (2028) → E Online Store (2028)

### Нова структура (REVISION 2)
- Phase A1 Foundation (current) → A2 ENI Critical 4 → BETA → A3 Promotions/Финанси → A4 Loyalty → B Marketing AI Phase 1-5 → C Online Store Phase 1-5

### Reorder rationale (Тихол explicit override 03.05.2026)
**Pre-beta priority:** products → sale → склад → доставки → поръчки → трансфери  
**Post-beta priority:** промоции → финанси → loyalty → Marketing AI → Online Store

### S78-S110 ROADMAP TABLE Revised

| Sprint | Module | Date | Status |
|---|---|---|---|
| S78-S88 | Foundation (chat, life-board, AI Studio backend, sale.php S87, products.php Sprint B/C) | mid-04 | DONE |
| S92 | STRESS deploy + AIBRAIN Phase 1 + INSIGHTS WRITE FIX | 02.05 | DONE |
| S94 | Wizard restructure indicator + autogen + zone | 02.05 | DONE |
| S95 | Wizard restructure HTML re-order + voice-first + AI Studio | 03.05-04.05 | IN PROGRESS (3 commits done, 4-5 remaining) |
| S96 | Sale.php S87E (8 bugs) + Pesho-in-the-Middle hardening | 04-05.05 | PENDING |
| S97 | warehouse.php finalize | 05.05 | PENDING |
| S98 | deliveries.php (ENI critical) | 06-08.05 | PENDING |
| S99 | orders.php (ENI critical) | 08-09.05 | PENDING |
| S100 | transfers.php (ENI critical) | 09-10.05 | PENDING |
| S101 | inventory accuracy passive scoring | 10-11.05 | PENDING |
| S102 | Beta launch prep (smoke tests + tenant ENI setup) | 11-13.05 | PENDING |
| S103 | **ENI BETA LAUNCH** | 14-15.05 | LOCKED |
| S104 | promotions.php (post-beta) | 16-22.05 | PENDING |
| S105 | финанси модул (post-beta) | 23-30.05 | PENDING |
| S106 | loyalty migration от donela.bg | юни | PENDING |
| S107-S110 | Marketing AI Phase 0 prep (schema migration empty tables) | юни-юли | PENDING |
| S111+ | Marketing AI Phase 1 Shadow → Phase 4 Public | юли 2026 — Q2 2027 | LOCKED roadmap |

### What Pulled Up (по-рано от REVISION 1)
- ENI critical 4 модула (deliveries, orders, transfers, inventory) от A2 → pre-beta
- Inventory accuracy gate (S101) от A3 → pre-beta

### What Pushed Down (по-късно от REVISION 1)
- WooCommerce/Shopify integration → REJECTED (заменено с Ecwid post-beta)
- Custom marketing creative engine → REJECTED (заменено с TikTok Symphony API)
- 15 промпт-маркетолога → REDUCED to 6
- Loyalty модул → след промоции/финанси (Тихол confirm)
- Marketing schema migration → post-beta (Тихол confirm)

---

## 🔄 REWORK QUEUE — NEW ENTRIES (03.05.2026)

### Existing closed today
- **#47** S82.DIAG.FIX — STATE wins per Rule #3 → CLOSED
- **#24a** FAL_API_KEY add → DONE (key configured)

### NEW entries

| RQ# | Описание | Priority | Target |
|---|---|---|---|
| **#61** | Marketing schema migration (25 mkt_* + 9 ALTER) — POST-BETA empty tables window | P2 | S107 |
| **#62** | WooCommerce/Shopify integration code archive (rejected) | P2 | post-beta cleanup |
| **#63** | Inventory accuracy passive scoring (gate за Marketing AI activation) | P0 | S101 |
| **#64** | Sale.php hardening (Pesho-in-the-Middle, audit trail) | P0 | S96 |
| **#65** | Ecwid partner contract signing | P1 | post-beta admin |
| **#66** | Stripe Connect setup multi-tenant | P1 | post-beta admin |
| **#67** | Loyalty модул migration от donela.bg | P0 | S106 |
| **#68** | Promotions модул build (за Пешо отстъпки + attribution) | P0 | S104 |
| **#69** | ROADMAP_v2.md documentation push | P1 | tonight overnight |
| **#70** | EU AI Act compliance prep (Q3 2026) | P2 | Q3 2026 |
| **#71** | AI Studio rewire с нов дизайн (post-beta) | P1 | S107+ |
| **#72** | Voice-First Wizard Navigation (Whisper + trigger words) | P0 | S95 ЧАСТ 1.2 (04.05) |
| **#73** | AI Studio entry inline (e1 design, conditional на снимка) | P0 | S95 ЧАСТ 1.3 (04.05) |
| **#74** | Multi-printer support (D520BT) — currently breaks DTM stability | P1 | post-beta |
| **#75** | Printer reliability — diagnose intermittent BLE | P1 | post-beta |
| **#76** | Printer health indicator UI (🟢/🟡/🔴 в header) | P1 | post-beta |
| **#77** | AI Studio mockups upload + commit (Тихол manual) | P0 | 04.05 преди ЧАСТ 1.3 |
| **#78** | AI Studio production wire (try-on €0.30 + SEO €0.02 endpoints) | P1 | post-beta (S107+) |
| **#79** | RWQ-24b — fal.ai integration end-to-end не production-wired | P1 | post-beta |

---

## 📜 STANDING RULES — NEW ENTRIES

### Rule #26 — Marketing AI Activation Gate (NEW)
Marketing AI се активира за tenant ONLY когато:
1. Inventory accuracy ≥ 95% за 30 последователни дни (passive scoring от RWQ-63)
2. Sale.php hardened (Pesho-in-the-Middle protected, RWQ-64)
3. Promotions модул работещ (RWQ-68 done)
4. Tenant explicitly opts-in в Settings → Marketing AI
5. Hard spend cap configured (€)

### Rule #27 — Hard Spend Caps Non-Negotiable (NEW)
- Marketing AI campaigns има per-tenant monthly hard cap (€)
- При reach на cap: ALL active campaigns auto-pause
- Tenant получава notification 80% / 95% / 100% reach
- Re-activation only manual от tenant (не auto)
- Audit log на всеки spend > €1

### Rule #28 — Confidence Routing extended за Marketing (NEW)
- Marketing AI decisions с confidence > 0.85 → auto-execute
- Confidence 0.5-0.85 → tenant confirmation required (24h max wait, after fall back to safe default)
- Confidence < 0.5 → blocked, escalate to human Тихол review

---

## 📊 STATUS UPDATE (top section на COMPASS)

**Last LOGIC LOG entry:** 03.05.2026 — Marketing AI v1.0 INTEGRATION + ROADMAP REVISION 2  
**REWORK QUEUE pending P0:** 16 entries  
**REWORK QUEUE pending P1:** 12 entries  
**Standing Rules:** 28 (was 25, +3 new)  
**Compass health:** ✅ Single source of truth maintained
