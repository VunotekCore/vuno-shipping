-- 016_billing.sql
-- Módulo de Cobros/Facturación: factura por paquete + métodos de pago + config.
CREATE TABLE IF NOT EXISTS payment_method (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_method_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO payment_method (name, description, is_active) VALUES
('Efectivo', 'Pago en efectivo', 1),
('Transferencia', 'Transferencia bancaria', 1),
('Tarjeta', 'Pago con tarjeta (débito/crédito)', 1);

CREATE TABLE IF NOT EXISTS invoice (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(20) NOT NULL,
    package_id INT NOT NULL,
    client_id INT NULL,
    client_name VARCHAR(255) NULL,
    client_ruc VARCHAR(30) NULL,
    base_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency ENUM('USD','CORD') NOT NULL DEFAULT 'USD',
    exchange_rate DECIMAL(10,4) NULL,
    status ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
    paid_amount DECIMAL(10,2) NULL,
    paid_currency ENUM('USD','CORD') NULL,
    payment_method_id INT NULL,
    payment_reference VARCHAR(100) NULL,
    notes TEXT NULL,
    paid_at DATETIME NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoice_number (invoice_number),
    KEY idx_invoice_package (package_id),
    KEY idx_invoice_client (client_id),
    KEY idx_invoice_status (status),
    KEY idx_invoice_created (created_at),
    FOREIGN KEY (package_id) REFERENCES package(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES client(id) ON DELETE SET NULL,
    FOREIGN KEY (payment_method_id) REFERENCES payment_method(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES `user`(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS billing_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tax_enabled TINYINT(1) DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 15.00,
    show_client_ruc TINYINT(1) DEFAULT 0,
    exchange_rate_usd DECIMAL(10,4) DEFAULT 36.00,
    invoice_prefix VARCHAR(10) DEFAULT 'FAC',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO billing_config (id, tax_enabled, tax_rate, show_client_ruc, exchange_rate_usd, invoice_prefix)
VALUES (1, 0, 15.00, 0, 36.00, 'FAC');

SET @col_ruc := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'client'
      AND COLUMN_NAME = 'ruc'
);
SET @sql := IF(@col_ruc = 0,
    'ALTER TABLE client ADD COLUMN ruc VARCHAR(30) NULL AFTER phone',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO document_sequence (document_code, description, prefix, current_number)
SELECT 'invoice', 'Factura de cobro', 'FAC', 1
WHERE NOT EXISTS (SELECT 1 FROM document_sequence WHERE document_code = 'invoice');