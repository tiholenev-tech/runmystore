-- S79.SELECTION_ENGINE rollback
-- Ред: rotation първо (заради FK), после catalog
DROP TABLE IF EXISTS ai_topic_rotation;
DROP TABLE IF EXISTS ai_topics_catalog;
