# S79.CHAT_INTEGRATION — HANDOFF

**Дата:** 22.04.2026
**Commits:** 8a91c27 (ETAP 1 build-prompt), 33eb831 (ETAP 2+3 chat.php)
**Tag:** v0.5.4-s79-chat-integration

## Свършено

**build-prompt.php** (+70 реда):
- `getInsightsForContext($tenant_id, $store_id, $role, $module)` helper
- 6Q context block преди `return $prompt;` — grouped по loss/loss_cause/gain/gain_cause/order/anti_order
- ORDER BY narrative flow (Тихол решение 22.04.2026)
- try/catch wrapper (Закон №3 fail-safe)

**chat.php** (+201 реда, 8 patches):
- insightAction() augment — fundamental_question като 2-ри fallback
- $all_insights_for_js +fq +qClass +fqLabel
- CSS .signal-card.q1-.q6 hue borders + .top-strip + .fq-badge
- Proactive pills SQL query (loss+order, 6h cooldown, role_gate, LIMIT 3)
- Top strip HTML между точност bar и briefing
- signal-card получава q1-q6 клас по fundamental_question
- markInsightShown() JS + proactivePillTap() функции
- openSignalDetail() hook за auto-tapped запис

**mark-insight-shown.php** (нов файл, 53 реда):
- AJAX POST endpoint
- Session auth check
- INSERT в ai_shown с action ENUM

## Тестове (tenant 7)

- 43 active insights с fundamental_question
- getInsightsForContext връща 23 за owner
- Proactive pills query → 3 candidates (loss)
- UI render: top strip работи, q1-q6 цветни borders работят
- ai_shown INSERT-и потвърдени: stock_zero_bestsellers, cash_frozen_zombie, zombie_60d (всички 'tapped')

## ORDER BY решение (22.04.2026)

- BIBLE §6.5 беше: loss → loss_cause → anti_order → order → gain → gain_cause
- Сменено на: **loss → loss_cause → gain → gain_cause → order → anti_order** (narrative flow)
- Причина: разказвателен поток за AI reading — губиш → защо → печелиш → защо → action+ → action-
- selection_priority (за Selection Engine) остава старият order (anti_order 3-ти)

## Known limitations (S79.FIX или CHAT 4)

- fq-badge в Signal Detail overlay не показан (само urgency dot)
- priceFormat/qtyFormat не използван в top-pill values
- 6h cooldown е универсален (S84+ per-urgency diferenciation)

## За CHAT 4 (visual rewrite)

- Одобрен дизайн: home-neon-v2 style (glass shine/glow, --hue1/--hue2)
- PHP логика 100% запазва се
- CSS + HTML структура се сменя секция по секция
- 6 въпроса към Тихол преди код (proactive pills позиция, sig-card 3 vs 6 цвята, overlay, input tap, weather, rewrite strategy)

## Tenant 7

- Upgrade на PRO за тест (22.04 ~16:00)
- Върнат на START в края на сесията (production-like)
