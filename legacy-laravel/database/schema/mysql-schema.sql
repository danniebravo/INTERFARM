/*M!999999\- enable the sandbox mode */ 
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `animal_lot_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `animal_lot_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned DEFAULT NULL,
  `animal_id` bigint(20) unsigned NOT NULL,
  `lot_id` bigint(20) unsigned NOT NULL,
  `entered_at` timestamp NULL DEFAULT NULL,
  `exited_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `animal_lot_history_lot_id_index` (`lot_id`),
  KEY `animal_lot_history_animal_id_index` (`animal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `animal_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `animal_photos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `animal_id` bigint(20) unsigned NOT NULL,
  `path` varchar(191) NOT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `animal_photos_animal_id_foreign` (`animal_id`),
  CONSTRAINT `animal_photos_animal_id_foreign` FOREIGN KEY (`animal_id`) REFERENCES `animals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `animals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `animals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned NOT NULL,
  `lot_id` bigint(20) unsigned DEFAULT NULL,
  `lot_assigned_at` timestamp NULL DEFAULT NULL,
  `internal_code` varchar(191) DEFAULT NULL,
  `ear_tag` varchar(191) DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL,
  `species` varchar(191) NOT NULL DEFAULT 'bovino',
  `breed` varchar(191) DEFAULT NULL,
  `sex` enum('macho','hembra') NOT NULL,
  `category` varchar(191) DEFAULT NULL,
  `purpose` enum('carne','leche','doble_proposito','crianza') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `dam_id` bigint(20) unsigned DEFAULT NULL,
  `sire_id` bigint(20) unsigned DEFAULT NULL,
  `weight_birth` decimal(8,2) DEFAULT NULL,
  `weight_current` decimal(8,2) DEFAULT NULL,
  `last_weight_date` date DEFAULT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'activo',
  `status_date` date DEFAULT NULL,
  `status_notes` text DEFAULT NULL,
  `location` varchar(191) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `photo` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `dam_name_manual` varchar(191) DEFAULT NULL,
  `sire_name_manual` varchar(191) DEFAULT NULL,
  `has_calved_before` varchar(191) DEFAULT NULL,
  `is_pregnant` varchar(191) DEFAULT NULL,
  `pregnancy_date` date DEFAULT NULL,
  `pregnancy_sire_id` bigint(20) unsigned DEFAULT NULL,
  `pregnancy_sire_name_manual` varchar(191) DEFAULT NULL,
  `service_type` varchar(30) DEFAULT NULL,
  `last_calving_date` date DEFAULT NULL,
  `dry_off_date` date DEFAULT NULL,
  `calving_count` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `animals_farm_id_foreign` (`farm_id`),
  KEY `animals_dam_id_foreign` (`dam_id`),
  KEY `animals_sire_id_foreign` (`sire_id`),
  KEY `animals_lot_id_foreign` (`lot_id`),
  KEY `animals_pregnancy_sire_id_foreign` (`pregnancy_sire_id`),
  CONSTRAINT `animals_dam_id_foreign` FOREIGN KEY (`dam_id`) REFERENCES `animals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `animals_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `animals_lot_id_foreign` FOREIGN KEY (`lot_id`) REFERENCES `lots` (`id`) ON DELETE SET NULL,
  CONSTRAINT `animals_pregnancy_sire_id_foreign` FOREIGN KEY (`pregnancy_sire_id`) REFERENCES `animals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `animals_sire_id_foreign` FOREIGN KEY (`sire_id`) REFERENCES `animals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(191) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(191) NOT NULL,
  `owner` varchar(191) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `daily_milk_productions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_milk_productions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned NOT NULL,
  `production_date` date NOT NULL,
  `liters` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_per_liter` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_income` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `daily_milk_productions_farm_date_unique` (`farm_id`,`production_date`),
  KEY `daily_milk_productions_farm_id_production_date_index` (`farm_id`,`production_date`),
  CONSTRAINT `daily_milk_productions_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned NOT NULL,
  `animal_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'general',
  `event_date` date DEFAULT NULL,
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `all_day` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `priority` varchar(30) NOT NULL DEFAULT 'medium',
  `lot_name` varchar(191) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `events_animal_id_foreign` (`animal_id`),
  KEY `events_farm_id_event_date_index` (`farm_id`,`event_date`),
  KEY `events_farm_id_start_datetime_index` (`farm_id`,`start_datetime`),
  KEY `events_type_index` (`type`),
  KEY `events_event_date_index` (`event_date`),
  KEY `events_start_datetime_index` (`start_datetime`),
  KEY `events_status_index` (`status`),
  KEY `events_priority_index` (`priority`),
  CONSTRAINT `events_animal_id_foreign` FOREIGN KEY (`animal_id`) REFERENCES `animals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `events_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `farm_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `farm_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `event_id` bigint(20) unsigned DEFAULT NULL,
  `source_type` varchar(80) DEFAULT NULL,
  `source_key` varchar(120) DEFAULT NULL,
  `level` varchar(30) NOT NULL DEFAULT 'medium',
  `title` varchar(191) NOT NULL,
  `message` text NOT NULL,
  `event_date` date DEFAULT NULL,
  `lot_name` varchar(191) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `scheduled_for` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `dismissed_at` timestamp NULL DEFAULT NULL,
  `dismissed_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `farm_notifications_unique_source` (`user_id`,`source_type`,`source_key`),
  KEY `farm_notifications_event_id_foreign` (`event_id`),
  KEY `farm_notifications_user_id_read_at_index` (`user_id`,`read_at`),
  KEY `farm_notifications_farm_id_scheduled_for_index` (`farm_id`,`scheduled_for`),
  CONSTRAINT `farm_notifications_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `farm_notifications_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `farm_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `farm_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `farm_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned NOT NULL,
  `key` varchar(191) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `farm_settings_farm_id_key_unique` (`farm_id`,`key`),
  CONSTRAINT `farm_settings_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `farm_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `farm_user` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `farm_id` bigint(20) unsigned NOT NULL,
  `role` varchar(191) NOT NULL DEFAULT 'owner',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `farm_user_user_id_foreign` (`user_id`),
  KEY `farm_user_farm_id_foreign` (`farm_id`),
  CONSTRAINT `farm_user_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `farm_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `farms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `farms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `location` varchar(191) DEFAULT NULL,
  `hectares` decimal(10,2) DEFAULT NULL,
  `production_type` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `financial_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `title` varchar(191) NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `milk_liters_sold` decimal(10,2) DEFAULT NULL,
  `milk_price_per_liter` decimal(12,2) DEFAULT NULL,
  `milk_sale_start_date` date DEFAULT NULL,
  `milk_sale_end_date` date DEFAULT NULL,
  `transaction_date` date NOT NULL,
  `category` varchar(191) DEFAULT NULL,
  `payment_method` varchar(191) DEFAULT NULL,
  `reference` varchar(191) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `financial_transactions_farm_id_transaction_date_index` (`farm_id`,`transaction_date`),
  KEY `financial_transactions_farm_id_type_index` (`farm_id`,`type`),
  KEY `financial_transactions_farm_id_category_index` (`farm_id`,`category`),
  CONSTRAINT `financial_transactions_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `lots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `code` varchar(191) DEFAULT NULL,
  `type` varchar(191) DEFAULT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'activo',
  `area_manual` decimal(12,2) DEFAULT NULL,
  `area_calculated` decimal(12,2) DEFAULT NULL,
  `polygon` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`polygon`)),
  `center_lat` decimal(10,7) DEFAULT NULL,
  `center_lng` decimal(10,7) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lots_farm_id_code_unique` (`farm_id`,`code`),
  CONSTRAINT `lots_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `meat_productions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `meat_productions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned NOT NULL,
  `animal_id` bigint(20) unsigned NOT NULL,
  `production_date` date NOT NULL,
  `weight_kg` decimal(10,2) DEFAULT NULL,
  `weight_gain_kg` decimal(10,2) DEFAULT NULL,
  `price_per_kg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estimated_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `meat_productions_farm_id_production_date_index` (`farm_id`,`production_date`),
  KEY `meat_productions_animal_id_production_date_index` (`animal_id`,`production_date`),
  KEY `meat_productions_farm_id_animal_id_index` (`farm_id`,`animal_id`),
  CONSTRAINT `meat_productions_animal_id_foreign` FOREIGN KEY (`animal_id`) REFERENCES `animals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `meat_productions_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `milk_productions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `milk_productions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned NOT NULL,
  `animal_id` bigint(20) unsigned DEFAULT NULL,
  `production_date` date NOT NULL,
  `period` enum('mañana','tarde') DEFAULT NULL,
  `liters` decimal(10,2) NOT NULL DEFAULT 0.00,
  `price_per_liter` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_income` decimal(14,2) NOT NULL DEFAULT 0.00,
  `weight_kg` decimal(10,2) DEFAULT NULL,
  `feeding_type` varchar(191) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `milk_productions_farm_id_production_date_index` (`farm_id`,`production_date`),
  KEY `milk_productions_animal_id_production_date_index` (`animal_id`,`production_date`),
  CONSTRAINT `milk_productions_animal_id_foreign` FOREIGN KEY (`animal_id`) REFERENCES `animals` (`id`) ON DELETE SET NULL,
  CONSTRAINT `milk_productions_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `milk_usages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `milk_usages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farm_id` bigint(20) unsigned NOT NULL,
  `usage_date` date NOT NULL,
  `calf_liters` decimal(10,2) NOT NULL DEFAULT 0.00,
  `consumed_liters` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `milk_usages_farm_id_usage_date_unique` (`farm_id`,`usage_date`),
  KEY `milk_usages_farm_id_usage_date_index` (`farm_id`,`usage_date`),
  CONSTRAINT `milk_usages_farm_id_foreign` FOREIGN KEY (`farm_id`) REFERENCES `farms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(191) NOT NULL,
  `token` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `platform_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `platform_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(191) NOT NULL,
  `value` text DEFAULT NULL,
  `type` varchar(30) NOT NULL DEFAULT 'string',
  `group` varchar(60) NOT NULL DEFAULT 'general',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(191) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `subscription_plan_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_number` varchar(191) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'COP',
  `billing_period` varchar(30) DEFAULT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_payment_methods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `provider` varchar(191) NOT NULL DEFAULT 'wompi',
  `provider_token` varchar(191) DEFAULT NULL,
  `holder_name` varchar(191) DEFAULT NULL,
  `brand` varchar(40) DEFAULT NULL,
  `last_four` varchar(4) NOT NULL,
  `expiry_month` tinyint(3) unsigned DEFAULT NULL,
  `expiry_year` smallint(5) unsigned DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscription_payment_methods_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `subscription_payment_methods_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `subscription_plan_id` bigint(20) unsigned DEFAULT NULL,
  `subscription_invoice_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'COP',
  `billing_period` varchar(30) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `status` varchar(40) NOT NULL DEFAULT 'paid',
  `payment_method` varchar(120) DEFAULT NULL,
  `provider` varchar(80) DEFAULT NULL,
  `provider_transaction_id` varchar(180) DEFAULT NULL,
  `provider_status` varchar(80) DEFAULT NULL,
  `reference` varchar(180) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `provider_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`provider_payload`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscription_payments_subscription_plan_id_foreign` (`subscription_plan_id`),
  KEY `subscription_payments_user_id_paid_at_index` (`user_id`,`paid_at`),
  KEY `subscription_payments_status_paid_at_index` (`status`,`paid_at`),
  KEY `subscription_payments_subscription_invoice_id_foreign` (`subscription_invoice_id`),
  CONSTRAINT `subscription_payments_subscription_invoice_id_foreign` FOREIGN KEY (`subscription_invoice_id`) REFERENCES `subscription_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `subscription_payments_subscription_plan_id_foreign` FOREIGN KEY (`subscription_plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `subscription_payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `billing_period` varchar(30) NOT NULL DEFAULT 'monthly',
  `max_farms` int(10) unsigned DEFAULT NULL,
  `max_users` int(10) unsigned DEFAULT NULL,
  `max_animals` int(10) unsigned DEFAULT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_plans_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `action` varchar(120) NOT NULL,
  `description` varchar(255) NOT NULL,
  `route_name` varchar(160) DEFAULT NULL,
  `method` varchar(10) DEFAULT NULL,
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_activity_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `user_activity_logs_action_created_at_index` (`action`,`created_at`),
  CONSTRAINT `user_activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(191) NOT NULL,
  `last_name` varchar(191) NOT NULL,
  `document_type` varchar(191) NOT NULL,
  `name` varchar(191) DEFAULT NULL,
  `document` varchar(191) NOT NULL,
  `phone` varchar(191) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) NOT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `status` varchar(191) NOT NULL DEFAULT 'active',
  `role` varchar(40) NOT NULL DEFAULT 'user',
  `admin_permissions` text DEFAULT NULL,
  `subscription_plan_id` bigint(20) unsigned DEFAULT NULL,
  `billing_status` varchar(40) NOT NULL DEFAULT 'trial',
  `next_billing_date` date DEFAULT NULL,
  `has_completed_onboarding` tinyint(1) NOT NULL DEFAULT 1,
  `remember_token` varchar(100) DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_farm_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_document_unique` (`document`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_subscription_plan_id_foreign` (`subscription_plan_id`),
  KEY `users_last_farm_id_foreign` (`last_farm_id`),
  CONSTRAINT `users_last_farm_id_foreign` FOREIGN KEY (`last_farm_id`) REFERENCES `farms` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_subscription_plan_id_foreign` FOREIGN KEY (`subscription_plan_id`) REFERENCES `subscription_plans` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `web_push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `web_push_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `endpoint` text NOT NULL,
  `endpoint_hash` varchar(64) NOT NULL,
  `public_key` text DEFAULT NULL,
  `auth_token` text DEFAULT NULL,
  `content_encoding` varchar(32) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `web_push_subscriptions_endpoint_hash_unique` (`endpoint_hash`),
  KEY `web_push_subscriptions_user_id_index` (`user_id`),
  CONSTRAINT `web_push_subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

/*M!999999\- enable the sandbox mode */ 
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_03_01_095910_add_role_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_03_03_094036_update_users_add_first_last_name',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_03_03_133531_make_users_name_nullable',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_03_05_110154_add_document_type_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_03_05_200330_create_farms_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_03_07_105604_create_animals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_03_07_113654_create_animal_photos_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_03_07_160637_add_purpose_to_animals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_03_08_175420_add_parents_manual_to_animals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_03_15_211429_add_reproductive_fields_to_animals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_04_14_000001_create_events_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_04_16_203900_create_lots_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_04_16_204400_add_lot_id_to_animals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_04_17_004744_add_interfarm_fields_to_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_04_17_012921_create_farm_user_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_04_18_120000_add_has_completed_onboarding_to_users_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_04_19_202652_add_status_fields_to_animals_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_04_20_000002_create_meat_productions_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_04_20_103400_create_milk_productions_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_04_20_120000_create_farm_settings_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_04_20_124005_rename_shift_to_period_in_milk_productions_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_04_20_125158_fix_period_column_in_milk_productions_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_04_30_000001_create_financial_transactions_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_05_01_000001_create_subscription_plans_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_05_01_000002_create_platform_settings_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_05_01_000003_add_subscription_fields_to_users_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_05_02_000001_ensure_role_column_on_users_table',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_05_02_000002_create_subscription_payments_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_05_02_000004_create_subscription_payment_methods_table',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_05_02_165704_create_subscription_invoices_table',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_05_02_000006_add_next_billing_date_to_users_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_05_02_000007_ensure_subscription_payment_methods_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_05_03_000001_add_last_login_at_to_users_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_05_03_000002_create_user_activity_logs_table',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_05_03_000003_create_farm_notifications_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_05_05_000001_add_pregnancy_sire_fields_to_animals_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_05_05_000002_create_web_push_subscriptions_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_05_15_000001_add_admin_permissions_to_users_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_05_15_000002_add_wompi_fields_to_subscription_payments_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_05_20_000001_add_unique_code_index_to_lots_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_05_25_000001_add_calving_count_to_animals_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_05_26_000001_create_milk_usages_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_05_26_000002_add_milk_liters_sold_to_financial_transactions_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_05_26_000003_add_dismiss_fields_to_farm_notifications_table',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_06_03_000001_create_daily_milk_productions_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_06_03_000002_add_last_farm_id_to_users_table',27);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_06_21_000001_add_milk_price_per_liter_to_financial_transactions_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_06_22_000001_add_milk_sale_date_range_to_financial_transactions_table',28);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_06_22_000002_backfill_legacy_milk_sale_ranges_from_titles',29);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_06_22_000003_backfill_more_legacy_milk_sale_range_formats',30);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_07_28_000001_add_service_type_to_animals_table',31);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_07_28_000002_add_dry_off_date_to_animals_table',32);
