-- DOWN: ai_insights.action_type — revert ENUM to original 4 values + nullable
-- WARNING: rows с навigate_chart/navigate_product/transfer_draft/dismiss
-- ще paднат (MySQL: ERROR 1265 Data truncated). Преди DOWN run:
--   UPDATE ai_insights SET action_type='none'
--   WHERE action_type IN ('navigate_chart','navigate_product','transfer_draft','dismiss');

UPDATE ai_insights SET action_type='none'
WHERE action_type IN ('navigate_chart','navigate_product','transfer_draft','dismiss');

ALTER TABLE ai_insights
  MODIFY COLUMN action_type
    ENUM('deeplink','order_draft','chat','none')
    DEFAULT 'none';
