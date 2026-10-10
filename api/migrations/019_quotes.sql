-- 019_quotes.sql
-- Módulo de Cotizaciones: cotización multi-ítem con validez, agente vendedor,
-- conversión a paquete(s) y comisiones por agente.
--
-- Decisiones de modelo:
-- - Una cotización con N ítems se convierte en N paquetes (1 ítem = 1 paquete).
--   El vínculo es package.quote_id (1:N), no una FK en quote.
-- - La comisión del agente se configura en la tabla `user` (commission_type /
--   commission_value) y se snapshotea en la cotización al convertir.

-- === Comisión por agente (sobre `user`) ===
SET @col_user_comm_type := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user'
      AND COLUMN_NAME = 'commission_type'
);
SET @sql := IF(@col_user_comm_type = 0,
    "ALTER TABLE `user` ADD COLUMN commission_type ENUM('percent','fixed') NULL AFTER role_id",
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_user_comm_value := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user'
      AND COLUMN_NAME = 'commission_value'
);
SET @sql := IF(@col_user_comm_value = 0,
    'ALTER TABLE `user` ADD COLUMN commission_value DECIMAL(10,2) NULL AFTER commission_type',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- === Agente asignado al cliente ===
SET @col_client_agent := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'client'
      AND COLUMN_NAME = 'agent_id'
);
SET @sql := IF(@col_client_agent = 0,
    'ALTER TABLE client ADD COLUMN agent_id INT NULL AFTER notes',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_client_agent := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'client'
      AND CONSTRAINT_NAME = 'fk_client_agent'
);
SET @sql := IF(@fk_client_agent = 0,
    'ALTER TABLE client ADD CONSTRAINT fk_client_agent FOREIGN KEY (agent_id) REFERENCES `user`(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- === Tabla de cotizaciones (cabecera) ===
CREATE TABLE IF NOT EXISTS quote (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_number VARCHAR(30) NOT NULL,
    client_id INT NULL,
    recipient_name VARCHAR(255) NOT NULL,
    recipient_phone VARCHAR(50) NULL,
    recipient_email VARCHAR(255) NULL,
    recipient_address VARCHAR(500) NULL,
    recipient_city VARCHAR(100) NULL,
    recipient_department VARCHAR(100) NULL,
    recipient_ruc VARCHAR(30) NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    agent_id INT NULL,
    status ENUM('draft','sent','accepted','rejected','expired','converted','cancelled')
        NOT NULL DEFAULT 'draft',
    origin ENUM('online','local') NOT NULL DEFAULT 'online',
    zone_id INT NULL,
    delivery_type_id INT NULL,
    shipping_service_id INT NULL,
    delivery_option VARCHAR(50) NULL,
    delivery_option_id INT NULL,
    pickup_required TINYINT(1) NOT NULL DEFAULT 0,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    delivery_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    base_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    currency ENUM('USD','CORD') NOT NULL DEFAULT 'USD',
    commission_type ENUM('percent','fixed') NULL,
    commission_value DECIMAL(10,2) NULL,
    commission_amount DECIMAL(12,2) NULL,
    valid_until DATE NULL,
    converted_at DATETIME NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_quote_number (quote_number),
    KEY idx_quote_client (client_id),
    KEY idx_quote_agent (agent_id),
    KEY idx_quote_status (status),
    KEY idx_quote_created (created_at),
    FOREIGN KEY (client_id) REFERENCES client(id) ON DELETE SET NULL,
    FOREIGN KEY (agent_id) REFERENCES `user`(id) ON DELETE SET NULL,
    FOREIGN KEY (zone_id) REFERENCES zone(id) ON DELETE SET NULL,
    FOREIGN KEY (delivery_type_id) REFERENCES delivery_type(id) ON DELETE SET NULL,
    FOREIGN KEY (shipping_service_id) REFERENCES shipping_service(id) ON DELETE SET NULL,
    FOREIGN KEY (delivery_option_id) REFERENCES delivery_option(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES `user`(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- === Ítems de la cotización (1 ítem = 1 paquete al convertir) ===
CREATE TABLE IF NOT EXISTS quote_item (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    position INT NOT NULL DEFAULT 0,
    description VARCHAR(500) NULL,
    quantity INT NOT NULL DEFAULT 1,
    weight DECIMAL(10,2) NULL,
    length DECIMAL(10,2) NULL,
    width DECIMAL(10,2) NULL,
    height DECIMAL(10,2) NULL,
    declared_value DECIMAL(12,2) NULL,
    volumetric_weight DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    chargeable_weight DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    flete_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    delivery_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quote_id) REFERENCES quote(id) ON DELETE CASCADE,
    INDEX idx_quote_item_quote (quote_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- === Paquete: vínculo a cotización + recolección ===
SET @col_pkg_quote := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'package'
      AND COLUMN_NAME = 'quote_id'
);
SET @sql := IF(@col_pkg_quote = 0,
    'ALTER TABLE package ADD COLUMN quote_id INT NULL AFTER client_id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_pkg_pickup := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'package'
      AND COLUMN_NAME = 'pickup_required'
);
SET @sql := IF(@col_pkg_pickup = 0,
    'ALTER TABLE package ADD COLUMN pickup_required TINYINT(1) NOT NULL DEFAULT 0 AFTER origin',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_pkg_pickup_veh := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'package'
      AND COLUMN_NAME = 'pickup_vehicle_id'
);
SET @sql := IF(@col_pkg_pickup_veh = 0,
    'ALTER TABLE package ADD COLUMN pickup_vehicle_id INT NULL AFTER pickup_required',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_pkg_quote := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'package'
      AND CONSTRAINT_NAME = 'fk_package_quote'
);
SET @sql := IF(@fk_pkg_quote = 0,
    'ALTER TABLE package ADD CONSTRAINT fk_package_quote FOREIGN KEY (quote_id) REFERENCES quote(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_pkg_pickup_veh := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'package'
      AND CONSTRAINT_NAME = 'fk_package_pickup_vehicle'
);
SET @sql := IF(@fk_pkg_pickup_veh = 0,
    'ALTER TABLE package ADD CONSTRAINT fk_package_pickup_vehicle FOREIGN KEY (pickup_vehicle_id) REFERENCES vehicle(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- === Secuencia de cotizaciones ===
INSERT INTO document_sequence (document_code, description, prefix, current_number)
SELECT 'quote', 'Cotización', 'COT', 1
WHERE NOT EXISTS (SELECT 1 FROM document_sequence WHERE document_code = 'quote');

-- === Permisos del módulo ===
INSERT INTO permission (module, action, label) VALUES
('quote', 'read', 'Ver cotizaciones'),
('quote', 'create', 'Crear cotización'),
('quote', 'edit', 'Editar cotizaciones'),
('quote', 'delete', 'Eliminar cotizaciones')
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- Admin recibe los permisos de cotización
INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT 1, id FROM permission WHERE module = 'quote';

-- === Rol Vendedor ===
INSERT INTO role (name, slug) VALUES ('Vendedor', 'vendedor')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Vendedor: quote (create/read/edit), client (create/read/edit),
-- zone (read), delivery_type (read), shipping_service (read), delivery_option (read)
INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id
FROM role r
JOIN permission p ON (
      (p.module = 'quote' AND p.action IN ('create', 'read', 'edit'))
   OR (p.module = 'client' AND p.action IN ('create', 'read', 'edit'))
   OR (p.module = 'zone' AND p.action IN ('read'))
   OR (p.module = 'delivery_type' AND p.action IN ('read'))
   OR (p.module = 'shipping_service' AND p.action IN ('read'))
   OR (p.module = 'delivery_option' AND p.action IN ('read'))
)
WHERE r.slug = 'vendedor';
