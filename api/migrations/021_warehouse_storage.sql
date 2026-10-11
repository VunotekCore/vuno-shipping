-- 021_warehouse_storage.sql
-- Almacenaje en bodega: bodegas (Miami/Nicaragua), clasificaciones de paquete,
-- ubicaciones estructuradas (pasillo/estante/nivel/posicion) e historial de
-- movimientos. Asociacion opcional a package + snapshot en route_stop.
-- Idempotente: CREATE IF NOT EXISTS + information_schema para ALTER.

-- === Bodegas (fisicas) ===
CREATE TABLE IF NOT EXISTS warehouse (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(20) NOT NULL,
    origin ENUM('online','local') NOT NULL DEFAULT 'online',
    address VARCHAR(500) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_warehouse_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- === Clasificaciones de paquete ===
CREATE TABLE IF NOT EXISTS storage_category (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(7) NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_storage_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- === Ubicaciones estructuradas (pasillo/estante/nivel/posicion) ===
CREATE TABLE IF NOT EXISTS storage_location (
    id INT AUTO_INCREMENT PRIMARY KEY,
    warehouse_id INT NOT NULL,
    category_id INT NULL,
    aisle VARCHAR(20) NULL,
    rack VARCHAR(20) NULL,
    level VARCHAR(20) NULL,
    position VARCHAR(20) NULL,
    code VARCHAR(50) NOT NULL,
    capacity INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_storage_location_code (warehouse_id, code),
    FOREIGN KEY (warehouse_id) REFERENCES warehouse(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES storage_category(id) ON DELETE SET NULL,
    INDEX idx_storage_location_warehouse (warehouse_id),
    INDEX idx_storage_location_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- === Historial de movimientos de almacen ===
CREATE TABLE IF NOT EXISTS package_storage_movement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL,
    warehouse_id INT NULL,
    location_id INT NULL,
    category_id INT NULL,
    action ENUM('stored','moved','released','restored') NOT NULL,
    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES package(id) ON DELETE CASCADE,
    FOREIGN KEY (warehouse_id) REFERENCES warehouse(id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES storage_location(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES storage_category(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES `user`(id) ON DELETE SET NULL,
    INDEX idx_psm_package (package_id),
    INDEX idx_psm_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- === ALTER package / route_stop (idempotente) ===
SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'package' AND COLUMN_NAME = 'storage_category_id'
);
SET @sql := IF(@col = 0,
    'ALTER TABLE package ADD COLUMN storage_category_id INT NULL, ADD FOREIGN KEY (storage_category_id) REFERENCES storage_category(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'package' AND COLUMN_NAME = 'storage_location_id'
);
SET @sql := IF(@col = 0,
    'ALTER TABLE package ADD COLUMN storage_location_id INT NULL, ADD FOREIGN KEY (storage_location_id) REFERENCES storage_location(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'package' AND COLUMN_NAME = 'stored_at'
);
SET @sql := IF(@col = 0,
    'ALTER TABLE package ADD COLUMN stored_at TIMESTAMP NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'route_stop' AND COLUMN_NAME = 'storage_location_id'
);
SET @sql := IF(@col = 0,
    'ALTER TABLE route_stop ADD COLUMN storage_location_id INT NULL, ADD FOREIGN KEY (storage_location_id) REFERENCES storage_location(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- === Clasificaciones iniciales ===
INSERT IGNORE INTO storage_category (name, color, description) VALUES
    ('Normal', '#3b82f6', 'Paquete estandar, sin manejo especial'),
    ('Frágil', '#f59e0b', 'Requiere manejo cuidadoso'),
    ('Alto valor', '#ef4444', 'Paquete de alto valor, resguardo');

-- === Bodegas iniciales ===
INSERT INTO warehouse (name, code, origin, address)
SELECT 'Bodega Miami', 'MIAMI', 'online', 'Miami, FL, USA'
WHERE NOT EXISTS (SELECT 1 FROM warehouse WHERE code = 'MIAMI');

INSERT INTO warehouse (name, code, origin, address)
SELECT 'Bodega Managua', 'MGUA', 'local', 'Managua, Nicaragua'
WHERE NOT EXISTS (SELECT 1 FROM warehouse WHERE code = 'MGUA');

-- === Ubicaciones de ejemplo (genericas, capacidad amplia) ===
INSERT INTO storage_location (warehouse_id, aisle, rack, level, position, code, capacity)
SELECT w.id, 'A', '01', '01', '01', 'A-01-01-01', 1000
FROM warehouse w WHERE w.code = 'MIAMI'
  AND NOT EXISTS (SELECT 1 FROM storage_location sl WHERE sl.warehouse_id = w.id AND sl.code = 'A-01-01-01');

INSERT INTO storage_location (warehouse_id, aisle, rack, level, position, code, capacity)
SELECT w.id, 'A', '01', '02', '01', 'A-01-02-01', 1000
FROM warehouse w WHERE w.code = 'MIAMI'
  AND NOT EXISTS (SELECT 1 FROM storage_location sl WHERE sl.warehouse_id = w.id AND sl.code = 'A-01-02-01');

INSERT INTO storage_location (warehouse_id, aisle, rack, level, position, code, capacity)
SELECT w.id, 'A', '01', '01', '01', 'A-01-01-01', 1000
FROM warehouse w WHERE w.code = 'MGUA'
  AND NOT EXISTS (SELECT 1 FROM storage_location sl WHERE sl.warehouse_id = w.id AND sl.code = 'A-01-01-01');

INSERT INTO storage_location (warehouse_id, aisle, rack, level, position, code, capacity)
SELECT w.id, 'A', '01', '02', '01', 'A-01-02-01', 1000
FROM warehouse w WHERE w.code = 'MGUA'
  AND NOT EXISTS (SELECT 1 FROM storage_location sl WHERE sl.warehouse_id = w.id AND sl.code = 'A-01-02-01');

-- === Permisos ===
INSERT INTO permission (module, action, label) VALUES
('warehouse', 'read', 'Ver bodegas'),
('warehouse', 'create', 'Crear bodega'),
('warehouse', 'edit', 'Editar bodegas'),
('warehouse', 'delete', 'Eliminar bodegas'),
('storage_category', 'read', 'Ver clasificaciones de almacen'),
('storage_category', 'create', 'Crear clasificacion de almacen'),
('storage_category', 'edit', 'Editar clasificaciones de almacen'),
('storage_category', 'delete', 'Eliminar clasificaciones de almacen'),
('storage_location', 'read', 'Ver ubicaciones de almacen'),
('storage_location', 'create', 'Crear ubicacion de almacen'),
('storage_location', 'edit', 'Editar ubicaciones de almacen'),
('storage_location', 'delete', 'Eliminar ubicaciones de almacen')
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- Admin: todos los permisos nuevos
INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id
FROM role r
JOIN permission p ON p.module IN ('warehouse', 'storage_category', 'storage_location')
WHERE r.slug = 'admin';

-- Operador / Operador Escáner: solo lectura
INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id
FROM role r
JOIN permission p ON p.module IN ('warehouse', 'storage_category', 'storage_location') AND p.action = 'read'
WHERE r.slug IN ('operador', 'operador_escaner');
