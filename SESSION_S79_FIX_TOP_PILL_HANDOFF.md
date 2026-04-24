# S79.FIX — Top-pill value формат — HANDOFF

**Дата:** 24.04.2026
**Commit:** 5e3ab76
**Tag:** v0.5.5-s79-fix-top-pill-format

## Свършено

**chat.php** (+6 реда, -1 ред, около ред 1594):
- Замяна на `number_format($pp_val, 0, '.', ' ') . ' ' . $cs`
- Използва `fmtMoney($pp_val, $cs)` за money topics + `fmtQty($pp_val)` за count topics
- Whitelist money topics (loss+order): `zero_stock_with_sales`, `selling_at_loss`, `seller_discount_killer`, `zombie_45d`

## Защо

S79_CHAT_INTEGRATION handoff limitation #2: priceFormat/qtyFormat не се ползваше
в top-pill → всички values получаваха € суфикс, включително count topics
като `bestseller_low_stock` (5 артикула → показваше "5 €").

Сега:
- `zero_stock_with_sales` (value=340.50) → "340,50 €" (EU формат)
- `bestseller_low_stock` (value=5) → "5" (без грешен суфикс)

## Закон №2 запазен

Числата идват наготово от ai_insights (compute-insights.php). PHP само
ги форматира. AI не пресмята.

## Known limitations (остават за по-късно)

- Per-urgency cooldown differentiation (S84+)
- fq-badge в Signal Detail overlay — ВЕЧЕ ДОБАВЕН (CSS 1271-1284, JS 2038-2040)
  → handoff на S79_CHAT_INTEGRATION беше остарял в тази точка

## Файлове

- chat.php (модифициран)
- SESSION_S79_FIX_TOP_PILL_HANDOFF.md (нов)
- MASTER_COMPASS.md (обновен — limitations секцията)

## Tenant 7 тест

Визуален тест от браузъра препоръчителен:
1. https://runmystore.ai/chat.php (tenant 7 → owner)
2. Top-strip pills — стойности трябва да имат EU формат + правилна единица
