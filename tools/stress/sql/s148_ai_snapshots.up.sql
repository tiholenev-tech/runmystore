-- S148 rich-seed schema migration: ai_snapshots
-- Per AI_AUTOFILL_SOURCE_OF_TRUTH.md — съхранява Gemini Vision wizard outputs.
-- Идемпотентна: CREATE TABLE IF NOT EXISTS.

CREATE TABLE IF NOT EXISTS `ai_snapshots` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `product_id` int unsigned DEFAULT NULL COMMENT 'NULL ако snapshot е pre-save от wizard',
  `image_url` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gemini_response` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` enum('male','female','kids','unisex') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `season` enum('all_year','spring_summer','autumn_winter','summer','winter') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colors` json DEFAULT NULL COMMENT '[{name, hex, confidence}, ...]',
  `description` text COLLATE utf8mb4_unicode_ci,
  `confidence_overall` decimal(3,2) DEFAULT NULL,
  `confidence_category` decimal(3,2) DEFAULT NULL,
  `confidence_gender` decimal(3,2) DEFAULT NULL,
  `confidence_brand` decimal(3,2) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_product` (`tenant_id`,`product_id`),
  KEY `idx_tenant_created` (`tenant_id`,`created_at`),
  CONSTRAINT `ai_snapshots_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_snapshots_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
