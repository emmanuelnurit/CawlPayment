-- CAWL Payment Transaction Table
-- Stores all CAWL payment transactions for tracking and debugging

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `cawl_transaction`;

CREATE TABLE IF NOT EXISTS `cawl_transaction` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `order_id` INT(11) NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT NULL COMMENT 'Payment method code (visa, applepay, klarna...)',
    `hosted_checkout_id` VARCHAR(255) DEFAULT NULL COMMENT 'CAWL Hosted Checkout ID',
    `transaction_ref` VARCHAR(255) DEFAULT NULL COMMENT 'CAWL Transaction Reference',
    `amount` DECIMAL(10,2) DEFAULT NULL COMMENT 'Transaction amount',
    `currency` VARCHAR(3) DEFAULT 'EUR' COMMENT 'Currency code',
    `status` VARCHAR(50) DEFAULT 'pending' COMMENT 'Transaction status',
    `status_code` INT(11) DEFAULT NULL COMMENT 'CAWL status code',
    `raw_request` LONGTEXT DEFAULT NULL COMMENT 'JSON request sent to CAWL',
    `raw_response` LONGTEXT DEFAULT NULL COMMENT 'JSON response from CAWL',
    `error_message` VARCHAR(500) DEFAULT NULL COMMENT 'Error message if any',
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_cawl_order_id` (`order_id`),
    INDEX `idx_cawl_hosted_checkout_id` (`hosted_checkout_id`),
    INDEX `idx_cawl_transaction_ref` (`transaction_ref`),
    INDEX `idx_cawl_status` (`status`),
    CONSTRAINT `fk_cawl_transaction_order` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
