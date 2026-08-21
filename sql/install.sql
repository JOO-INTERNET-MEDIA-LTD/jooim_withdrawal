CREATE TABLE IF NOT EXISTS `PREFIX_jooim_withdrawal` (
  `id_jooim_withdrawal` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_source_draft` BIGINT UNSIGNED NULL,
  `id_shop_group` INT UNSIGNED NOT NULL DEFAULT 0,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_order` INT UNSIGNED NULL,
  `id_customer` INT UNSIGNED NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `id_currency` INT UNSIGNED NULL,
  `reference` VARCHAR(64) NOT NULL,
  `order_reference` VARCHAR(64) NOT NULL,
  `source` VARCHAR(32) NOT NULL,
  `scope` VARCHAR(16) NOT NULL,
  `status_code` VARCHAR(32) NOT NULL DEFAULT 'submitted',
  `customer_firstname` VARCHAR(128) NOT NULL,
  `customer_lastname` VARCHAR(128) NOT NULL,
  `customer_email` VARCHAR(254) NOT NULL,
  `customer_phone` VARCHAR(64) NULL,
  `reason_code` VARCHAR(64) NULL,
  `customer_note` TEXT NULL,
  `statement_text` TEXT NOT NULL,
  `order_date` DATETIME NULL,
  `delivery_date` DATETIME NULL,
  `submitted_at_utc` DATETIME NOT NULL,
  `shop_timezone` VARCHAR(64) NOT NULL,
  `form_version` VARCHAR(32) NOT NULL,
  `module_version` VARCHAR(32) NOT NULL,
  `legal_text_version` VARCHAR(64) NOT NULL,
  `privacy_text_version` VARCHAR(64) NOT NULL,
  `payload_json` LONGTEXT NOT NULL,
  `payload_hash` CHAR(64) NOT NULL,
  `period_assessment` VARCHAR(32) NOT NULL DEFAULT 'unknown',
  `assigned_employee_id` INT UNSIGNED NULL,
  `acknowledgement_sent_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_jooim_withdrawal`),
  UNIQUE KEY `uniq_withdrawal_reference` (`reference`),
  UNIQUE KEY `uniq_withdrawal_source_draft` (`id_source_draft`),
  KEY `idx_withdrawal_shop_order` (`id_shop`,`id_order`),
  KEY `idx_withdrawal_customer` (`id_customer`),
  KEY `idx_withdrawal_email` (`customer_email`),
  KEY `idx_withdrawal_status` (`status_code`),
  KEY `idx_withdrawal_submitted` (`submitted_at_utc`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_jooim_withdrawal_item` (
  `id_jooim_withdrawal_item` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_jooim_withdrawal` BIGINT UNSIGNED NOT NULL,
  `id_order_detail` INT UNSIGNED NULL,
  `id_product` INT UNSIGNED NULL,
  `id_product_attribute` INT UNSIGNED NULL,
  `product_reference` VARCHAR(128) NULL,
  `product_name` VARCHAR(512) NOT NULL,
  `product_attribute_name` VARCHAR(512) NULL,
  `customization_snapshot` LONGTEXT NULL,
  `ordered_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
  `available_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
  `withdrawal_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
  `unit_price_tax_excl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `unit_price_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `total_price_tax_incl` DECIMAL(20,6) NOT NULL DEFAULT 0,
  `currency_iso` VARCHAR(3) NULL,
  `snapshot_json` LONGTEXT NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_jooim_withdrawal_item`),
  KEY `idx_item_withdrawal` (`id_jooim_withdrawal`),
  KEY `idx_item_order_detail` (`id_order_detail`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_jooim_withdrawal_access_request` (
  `id_access_request` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_order` INT UNSIGNED NULL,
  `order_reference_hmac` CHAR(64) NOT NULL,
  `token_hash` CHAR(64) NULL,
  `purpose` VARCHAR(64) NOT NULL,
  `email_source` VARCHAR(32) NULL,
  `expires_at` DATETIME NULL,
  `opened_at` DATETIME NULL,
  `used_at` DATETIME NULL,
  `revoked_at` DATETIME NULL,
  `request_ip_hash` CHAR(64) NOT NULL,
  `request_user_agent_hash` CHAR(64) NULL,
  `mail_status` VARCHAR(32) NOT NULL DEFAULT 'not_required',
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_access_request`),
  KEY `idx_access_shop_order` (`id_shop`,`id_order`),
  KEY `idx_access_ref` (`order_reference_hmac`),
  KEY `idx_access_ip` (`request_ip_hash`,`created_at`),
  KEY `idx_access_token` (`token_hash`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_jooim_withdrawal_guest_session` (
  `id_guest_session` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_order` INT UNSIGNED NOT NULL,
  `id_customer` INT UNSIGNED NOT NULL,
  `session_hash` CHAR(64) NOT NULL,
  `purpose` VARCHAR(64) NOT NULL,
  `last_activity_at` DATETIME NOT NULL,
  `absolute_expires_at` DATETIME NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `revoked_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_guest_session`),
  UNIQUE KEY `uniq_guest_session_hash` (`session_hash`),
  KEY `idx_guest_order` (`id_shop`,`id_order`),
  KEY `idx_guest_expiry` (`expires_at`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_jooim_withdrawal_draft` (
  `id_draft` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_order` INT UNSIGNED NULL,
  `id_customer` INT UNSIGNED NULL,
  `guest_session_id` BIGINT UNSIGNED NULL,
  `source` VARCHAR(32) NOT NULL DEFAULT 'guest_order_number',
  `scope` VARCHAR(16) NOT NULL,
  `draft_payload` LONGTEXT NOT NULL,
  `idempotency_key_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `submitted_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_draft`),
  UNIQUE KEY `uniq_draft_idempotency` (`idempotency_key_hash`),
  KEY `idx_draft_shop_order` (`id_shop`,`id_order`),
  KEY `idx_draft_expiry` (`expires_at`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_jooim_withdrawal_history` (
  `id_history` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_jooim_withdrawal` BIGINT UNSIGNED NOT NULL,
  `id_employee` INT UNSIGNED NULL,
  `old_status_code` VARCHAR(32) NULL,
  `new_status_code` VARCHAR(32) NULL,
  `event_code` VARCHAR(64) NOT NULL,
  `public_message` TEXT NULL,
  `private_note` TEXT NULL,
  `metadata_json` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_history`),
  KEY `idx_history_withdrawal` (`id_jooim_withdrawal`,`created_at`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_jooim_withdrawal_document` (
  `id_document` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_jooim_withdrawal` BIGINT UNSIGNED NOT NULL,
  `document_type` VARCHAR(64) NOT NULL,
  `document_reference` VARCHAR(128) NOT NULL,
  `storage_path` VARCHAR(1024) NOT NULL,
  `mime_type` VARCHAR(128) NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `file_hash` CHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_document`),
  UNIQUE KEY `uniq_document_reference` (`document_reference`),
  KEY `idx_document_withdrawal` (`id_jooim_withdrawal`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_jooim_withdrawal_mail` (
  `id_mail` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_jooim_withdrawal` BIGINT UNSIGNED NULL,
  `id_access_request` BIGINT UNSIGNED NULL,
  `template_code` VARCHAR(128) NOT NULL,
  `recipient_email` VARCHAR(254) NOT NULL,
  `subject_snapshot` VARCHAR(512) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'queued',
  `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `provider_message_id` VARCHAR(255) NULL,
  `error_message` TEXT NULL,
  `next_attempt_at` DATETIME NULL,
  `sent_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_mail`),
  KEY `idx_mail_withdrawal` (`id_jooim_withdrawal`),
  KEY `idx_mail_access` (`id_access_request`),
  KEY `idx_mail_queue` (`status`,`next_attempt_at`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_jooim_withdrawal_order_identity` (
  `id_order_identity` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_order` INT UNSIGNED NOT NULL,
  `id_customer` INT UNSIGNED NOT NULL,
  `order_reference` VARCHAR(64) NOT NULL,
  `firstname_snapshot` VARCHAR(128) NOT NULL,
  `lastname_snapshot` VARCHAR(128) NOT NULL,
  `email_snapshot` VARCHAR(254) NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_order_identity`),
  UNIQUE KEY `uniq_order_identity` (`id_shop`,`id_order`),
  KEY `idx_order_identity_ref` (`id_shop`,`order_reference`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_jooim_withdrawal_text_version` (
  `id_text_version` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_shop` INT UNSIGNED NOT NULL,
  `id_lang` INT UNSIGNED NOT NULL,
  `text_type` VARCHAR(64) NOT NULL,
  `version` VARCHAR(64) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `content_hash` CHAR(64) NOT NULL,
  `active_from` DATETIME NOT NULL,
  `active_to` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_text_version`),
  UNIQUE KEY `uniq_text_version` (`id_shop`,`id_lang`,`text_type`,`version`),
  KEY `idx_text_active` (`id_shop`,`id_lang`,`text_type`,`active_from`,`active_to`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_jooim_withdrawal_event_outbox` (
  `id_event` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_name` VARCHAR(128) NOT NULL,
  `aggregate_type` VARCHAR(64) NOT NULL,
  `aggregate_id` BIGINT UNSIGNED NOT NULL,
  `payload_json` LONGTEXT NOT NULL,
  `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `next_attempt_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `processed_at` DATETIME NULL,
  PRIMARY KEY (`id_event`),
  KEY `idx_event_queue` (`status`,`next_attempt_at`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_jooim_withdrawal_sequence` (
  `id_shop` INT UNSIGNED NOT NULL,
  `sequence_year` SMALLINT UNSIGNED NOT NULL,
  `last_value` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_shop`,`sequence_year`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `PREFIX_jooim_withdrawal_refund` (
  `id_refund` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_jooim_withdrawal` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(20,6) NOT NULL,
  `currency_iso` VARCHAR(3) NOT NULL,
  `refund_method` VARCHAR(64) NULL,
  `transaction_reference` VARCHAR(255) NULL,
  `internal_note` TEXT NULL,
  `refunded_at` DATETIME NOT NULL,
  `id_employee` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id_refund`),
  KEY `idx_refund_withdrawal` (`id_jooim_withdrawal`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
