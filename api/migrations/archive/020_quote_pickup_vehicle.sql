-- 020_quote_pickup_vehicle.sql
-- Fase A recolección: vehículo asignado a la recolección de una cotización.
-- Al convertir la cotización, el vehículo se copia a package.pickup_vehicle_id.
-- Idempotente (guardas information_schema + PREPARE/EXECUTE).

SET @col_quote_pickup_veh := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'quote'
      AND COLUMN_NAME = 'pickup_vehicle_id'
);
SET @sql := IF(@col_quote_pickup_veh = 0,
    'ALTER TABLE quote ADD COLUMN pickup_vehicle_id INT NULL AFTER pickup_required',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_quote_pickup_veh := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'quote'
      AND CONSTRAINT_NAME = 'fk_quote_pickup_vehicle'
);
SET @sql := IF(@fk_quote_pickup_veh = 0,
    'ALTER TABLE quote ADD CONSTRAINT fk_quote_pickup_vehicle FOREIGN KEY (pickup_vehicle_id) REFERENCES vehicle(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- El vendedor necesita ver la flota para asignar el vehículo de recolección.
INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id
FROM role r
JOIN permission p ON p.module = 'vehicle' AND p.action = 'read'
WHERE r.slug = 'vendedor';
