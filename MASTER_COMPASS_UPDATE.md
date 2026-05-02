# 📍 MASTER_COMPASS_UPDATE — 01.05.2026 EOD

**Прилагане:** Append съдържанието на този файл в края на `MASTER_COMPASS.md`.

---

## 📅 LOGIC CHANGE LOG — 01.05.2026

### S89.HOTFIX → S91.MIGRATE → S91.INSIGHTS_HEALTH (12 commits, 1 ден)

**Сутрин — S89 финализиране:**
- DELIVERY+ORDERS пакет: 13 файла, 4931 реда (4 services + 5 frontend + 3 интеграции)
- 500 ERROR fix: fmtMoney() дублирана в 5 нови файла → премахнати, helpers.php е truth
- DESIGN-KIT v1.1: theme-toggle.js нов помощен файл (Opus 4.7 spec) + wired в 5 S89 модула
- Resolved S89 GAP: rmsToggleTheme() липсваше в design-kit v1.0

**Следобед — beta blockers:**
- S90.RACE: sale.php inventory atomicity — premahnah `GREATEST()` race condition, добавих `quantity >= ?` conditional + `rowCount() === 0 → throw → rollback`. Beta-safe.
- S90.PRODUCTS.SPRINT_B: 8 P0 bugs claim 8/8 done, **post-migration discovery: 6/8 реално** (C1 'Добави размер' липсва, C5 ChevronLeft без CSS class)
- INVESTIGATION_REPORT.md: discovery че 33/41 ai_insights са в module='products' (невидими в life-board)

**Вечер — Design-kit migration ВСИЧКИ модули:**
- S91.MIGRATE.CHAT: 3329 → 1638 реда (-51%, изтрити 1700 inline CSS дубликати)
- S91.MIGRATE.SALE: visual migration, race fix preserved, 46 backdrop-filter blur effects изгубени
- S91.MIGRATE.PRODUCTS: visual migration, Sprint B logic preserved
- S91.INSIGHTS_HEALTH: compute-insights.php default 'products' → 'home', + admin/insights-health.php monitor

**Финален статус:**
- Phase A1 ~75% (продължава с inventory + S92 Sprint B follow-up)
- DESIGN-KIT v1.1 stable (theme-toggle.js wired в всички modules)
- ENI beta deadline: 14.05.2026 = **13 дни остават**

---

## 🔧 REWORK QUEUE — НОВИ ENTRIES (от 01.05.2026)

| # | Заглавие | Open from | Priority | Описание |
|---|---|---|---|---|
| 53 | Sprint B C1 'Добави размер' missing | S90 (01.05) | P1 (post-beta) | Продава потвърди в Sprint B handoff (commit c0146c6) че 8/8 bugs done. Code Code за migration откри че бутон "+ Добави размер" не съществува нито в новия, нито в backup-а на products.php. Имплементиран C1 е невярна claim. |
| 54 | Sprint B C5 ChevronLeft без CSS class | S90 (01.05) | P2 | Back arrow icon добавен като inline polyline SVG (15 18 9 12 15 6) без съответен CSS class. Compliance check грешка. Поправяне с правилен .mod-prod-back или lucide.ChevronLeft class. |
| 55 | Mirror cron auto-sync hijack pattern | Recurring (S82+, escalated 01.05) | P1 (post-beta) | 7 incident-а на 01.05.2026 (commits 9862b04, ee20fc3, 3cd6ce9, 3ce8ce2, c4b07d2, e7309a0, 59bf479, bc8232f, 87ff1d8). Auto-sync mirror cron commit-ва staged changes преди ръчния git commit, заменя descriptive message с "mirrors: auto-sync PHP→MD". Решение: добави `--only=mirrors/` flag или skip pattern за `*.php`/`*.md` извън `mirrors/` папка. |
| 56 | 46 backdrop-filter blur effects изгубени в migration | S91 (01.05) | P3 (visual polish) | Migration на sale.php + products.php към design-kit v1.1 — compliance изисква backdrop-filter само в design-kit-а. ~46 overlay/sticky bar blur effects (rec-ov, rec-box, payment sheet, ws-sheet, search overlay) сега flat-translucent. Backgrounds стоят, само blur липсва. Решение: добави .mod-*-glass mod-specific class в design-kit за местата нуждаещи blur. |
| 57 | partial-header.html hardcoded "PRO" plan badge | S91 (01.05) | P2 | design-kit/partial-header.html има hardcoded plan badge "PRO". Не reflects user-овия actual plan (FREE/START/PRO/BIZ). Bug в самия партиал, не в migration-а. |
| 58 | partial-bottom-nav.html всички 4 таба active | S91 (01.05) | P2 | design-kit/partial-bottom-nav.html маркира всички 4 таба като active едновременно. Bug в самия партиал. Може да добави PHP за active state на base от $_SERVER['REQUEST_URI'] или class на body. |
| 59 | Insights routing 19 стари pf функции | S91 (01.05) | P0 (utre verify) | Default fix 'products' → 'home' applied. Code Code откри че 33-те products insights идват от 19 ПО-СТАРИ pf функции (pfZombie45d, pfDecliningTrend, pfBasketDriver, pfTopProfit30d, pfHighestMargin, pfHighReturnRate, pfBelowMinUrgent, pfRunningOutToday, pfSellingAtLoss, pfNoCostPrice, pfMarginBelow15, pfSellerDiscountKiller, pfProfitGrowth, pfTrendingUp, pfLoyalCustomers, pfSizeLeader, pfBestsellerLowStock, pfLostDemandMatch, pfZeroStockWithSales). Те са продукти-related — може да наводнят life-board сутрин 02.05. Browser test required. Ако > 30 сигнала на life-board → rollback default или explicit module='home' само за 6-те S89 функции (Path A от INVESTIGATION_REPORT.md). |
| 60 | tihol git credential helper | S91 (01.05) | P3 | Tihol user няма git credentials (push иска username/password). Сега push-овете идват от root. Post-beta: PAT + git credential.helper store за tihol. |

---

## 🏗️ PHASE PROGRESS UPDATE

### Phase A1 — Beta Foundation (~75%)

**Завършено в S91:**
- ✅ Race condition в sale.php (P0 beta blocker)
- ✅ Theme toggle на 5-те S89 модула (P1)
- ✅ Visual migration: chat.php + sale.php + products.php към design-kit v1.1 (P1)
- ✅ Insights routing fix (P0)
- ✅ admin/insights-health.php monitor (P1)
- ✅ Design-kit v1.1 stable

**Остава за Phase A1 (P0):**
- ⏳ Browser test verify (02.05 сутрин — Тихол)
- ⏳ Sprint B C1+C5 follow-up (S92.PRODUCTS)
- ⏳ Inventory module (~80% built per memory, започваме S92)
- ⏳ Insights routing rollback decision (зависи от browser test)

### DESIGN-KIT v1.1 (stable since 01.05)
- 5 CSS файла (tokens, components-base, components, light-theme, header-palette)
- 2 JS файла (palette.js, theme-toggle.js)
- 2 partials (header, bottom-nav)
- compliance check 8/8 за всички мигрирани модули
- Used in: chat.php, sale.php, products.php, delivery.php, deliveries.php, orders.php, order.php, defectives.php

---

## 📊 НОВИ DB DIAGNOSTICS (01.05.2026)

```sql
SELECT module, COUNT(*) FROM ai_insights 
WHERE tenant_id=7 AND created_at > NOW() - INTERVAL 7 DAY 
GROUP BY module;
```

Резултат:
- module='products': 33 (80% — НЕВИДИМИ преди S91 fix)
- module='home': 8 (20% — visible)

**След S91.INSIGHTS_HEALTH fix:** новите insights ще влизат в 'home' default. Стари 33 в 'products' — НЕ migrate-нати (Code Code препоръка).

**Verify утре сутрин:** /admin/insights-health.php или ръчна SQL заявка.

---

## 🚪 НОВИ КОНВЕНЦИИ

### Tihol user setup (01.05.2026)
Claude Code 2.1.126+ забранява root user. Решение:
- User: `tihol` (UID 1000)
- Home: `/home/tihol/`
- Sudo: yes (без парола disabled)
- Owner: `/var/www/runmystore` group=tihol
- Apache uploads: chown www-data:tihol with g+rwX

### Стартов протокол за всеки tmux/claude
```bash
su - tihol
cd /var/www/runmystore
tmux new -s [име]
claude
# Trust folder = 1, Shift+Tab за auto-accept
```

### END_OF_DAY_PROTOCOL.md (нов файл в repo)
Тихол казва "край на деня" или "приключи" → шефът автоматично:
1. Document Protocol (3 четения)
2. Генерира PRIORITY_TODAY.md + MASTER_COMPASS_UPDATE.md
3. 3-стъпкова инструкция за качване
4. Чака "качено" → "лек край"

---

ПРОТОКОЛ ИЗПЪЛНЕН.
