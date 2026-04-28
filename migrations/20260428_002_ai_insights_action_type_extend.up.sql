-- S88.PRODUCTS.AIBRAIN_WIRE — extend ai_insights.action_type ENUM
-- Adds: navigate_chart, navigate_product, transfer_draft, dismiss
-- Tightens: nullable → NOT NULL (verified 0 NULL rows pre-migration)
-- Default unchanged: 'none'

ALTER TABLE ai_insights
  MODIFY COLUMN action_type
    ENUM('deeplink','order_draft','chat','none',
         'navigate_chart','navigate_product','transfer_draft','dismiss')
    NOT NULL DEFAULT 'none';
