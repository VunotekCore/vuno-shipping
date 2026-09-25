-- Migration 013: Flujo internacional Miami -> Nicaragua.
-- 1) Nuevo estado package 'in_nicaragua' + transiciones de la cadena internacional.
-- 2) Tablas de embarque (lotes de consolidacion): shipment + shipment_package.
-- 3) Columna package.origin (online|local) para distinguir el origen de la admision.
-- Compatible con MySQL 8.0 (sin ADD COLUMN IF NOT EXISTS). Idempotente.

-- === 1. Estado in_nicaragua ===
INSERT IGNORE INTO package_status (name, description)
VALUES ('in_nicaragua', 'Recibido en Nicaragua, listo para entrega local');

INSERT IGNORE INTO package_status_transition (from_status, to_status) VALUES
('received', 'in_transit'),
('in_transit', 'in_nicaragua'),
('in_nicaragua', 'out_for_delivery'),
('in_nicaragua', 'cancelled');

-- El flujo internacional debe pasar por in_nicaragua al llegar; se elimina el salto
-- directo in_transit -> out_for_delivery de instalaciones previas.
DELETE FROM package_status_transition
WHERE from_status = 'in_transit' AND to_status = 'out_for_delivery';

-- === 3. package.origin (online|local) ===
SET @col_origin := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'package'
      AND COLUMN_NAME = 'origin'
);
SET @sql_origin := IF(@col_origin = 0,
    'ALTER TABLE package ADD COLUMN origin ENUM(''online'',''local'') DEFAULT ''online'' AFTER notes',
    'SELECT 1');
PREPARE stmt_origin FROM @sql_origin;
EXECUTE stmt_origin;
DEALLOCATE PREPARE stmt_origin;

-- === 2. Embargue (lote de consolidacion) ===
CREATE TABLE IF NOT EXISTS shipment (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    mode ENUM('air','sea') NOT NULL DEFAULT 'air',
    status ENUM('draft','in_transit','arrived','cancelled') NOT NULL DEFAULT 'draft',
    departure_date DATE NULL,
    arrival_date DATE NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_shipment_code (code),
    FOREIGN KEY (created_by) REFERENCES `user`(id) ON DELETE SET NULL,
    INDEX idx_shipment_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipment_package (
    shipment_id INT NOT NULL,
    package_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (shipment_id, package_id),
    FOREIGN KEY (shipment_id) REFERENCES shipment(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES package(id) ON DELETE CASCADE,
    INDEX idx_shipment_package_package (package_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;