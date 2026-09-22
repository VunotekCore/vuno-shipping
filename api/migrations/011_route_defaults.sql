-- Migration 011: Conductor/vehiculo por defecto en la ruta maestra.
-- La ruta maestra define la asignacion fija de conductor y vehiculo; el despacho
-- diario (route_run) los copia al crearse y puede cambiarlos por dia.
-- Compatible con MySQL 8.0 (sin ADD COLUMN IF NOT EXISTS). Idempotente.

SET @col_driver := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'delivery_route'
      AND COLUMN_NAME = 'default_driver_id'
);
SET @sql := IF(@col_driver = 0,
    'ALTER TABLE delivery_route ADD COLUMN default_driver_id INT NULL AFTER color',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_vehicle := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'delivery_route'
      AND COLUMN_NAME = 'default_vehicle_id'
);
SET @sql := IF(@col_vehicle = 0,
    'ALTER TABLE delivery_route ADD COLUMN default_vehicle_id INT NULL AFTER default_driver_id',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_driver := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'delivery_route'
      AND CONSTRAINT_NAME = 'fk_route_default_driver'
);
SET @sql := IF(@fk_driver = 0,
    'ALTER TABLE delivery_route ADD CONSTRAINT fk_route_default_driver FOREIGN KEY (default_driver_id) REFERENCES `user`(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_vehicle := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'delivery_route'
      AND CONSTRAINT_NAME = 'fk_route_default_vehicle'
);
SET @sql := IF(@fk_vehicle = 0,
    'ALTER TABLE delivery_route ADD CONSTRAINT fk_route_default_vehicle FOREIGN KEY (default_vehicle_id) REFERENCES vehicle(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_driver := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'delivery_route'
      AND INDEX_NAME = 'idx_route_default_driver'
);
SET @sql := IF(@idx_driver = 0,
    'ALTER TABLE delivery_route ADD INDEX idx_route_default_driver (default_driver_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_vehicle := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'delivery_route'
      AND INDEX_NAME = 'idx_route_default_vehicle'
);
SET @sql := IF(@idx_vehicle = 0,
    'ALTER TABLE delivery_route ADD INDEX idx_route_default_vehicle (default_vehicle_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
