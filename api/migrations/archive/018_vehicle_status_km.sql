-- 018_vehicle_status_km.sql
-- Migrar vehicle: status ENUM + current_km + km_last_updated_* + tabla odometer log

SET @db := DATABASE();

-- Añadir columnas a vehicle si no existen
SET @has_status = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vehicle' AND COLUMN_NAME='status');
SET @sql = IF(@has_status = 0, 'ALTER TABLE vehicle ADD COLUMN status ENUM("disponible","en_ruta","en_espera","en_mantenimiento","en_reparacion","fuera_de_servicio","accidentado","dado_de_baja") NOT NULL DEFAULT "disponible" AFTER status_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_ckm = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vehicle' AND COLUMN_NAME='current_km');
SET @sql = IF(@has_ckm = 0, 'ALTER TABLE vehicle ADD COLUMN current_km DECIMAL(10,1) NOT NULL DEFAULT 0.00 AFTER plate', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_kmu = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vehicle' AND COLUMN_NAME='km_last_updated_at');
SET @sql = IF(@has_kmu = 0, 'ALTER TABLE vehicle ADD COLUMN km_last_updated_at DATETIME NULL AFTER current_km', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_kmub = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vehicle' AND COLUMN_NAME='km_last_updated_by');
SET @sql = IF(@has_kmub = 0, 'ALTER TABLE vehicle ADD COLUMN km_last_updated_by INT NULL AFTER km_last_updated_at', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK para km_last_updated_by si no existe
SET @fk_km = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vehicle' AND CONSTRAINT_NAME='fk_vehicle_km_user');
SET @sql = IF(@fk_km = 0, 'ALTER TABLE vehicle ADD CONSTRAINT fk_vehicle_km_user FOREIGN KEY (km_last_updated_by) REFERENCES `user`(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Crear índice para status (ENUM) si no existe
SET @idx_s = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vehicle' AND INDEX_NAME='idx_vehicle_status');
SET @sql = IF(@idx_s = 0, 'ALTER TABLE vehicle ADD INDEX idx_vehicle_status (status)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Migrar datos desde vehicle_status si existe (best-effort)
UPDATE vehicle v LEFT JOIN vehicle_status vs ON v.status_id = vs.id SET v.status = COALESCE(NULLIF(LOWER(REPLACE(REPLACE(vs.name,' ','_'),'-','_')),''),'disponible') WHERE v.status IS NULL OR v.status='';

-- Drop viejo FK/col status_id si existen (opcional, no rompe)
SET @fk_sid = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vehicle' AND CONSTRAINT_NAME='vehicle_ibfk_2');
SET @sql = IF(@fk_sid > 0, 'ALTER TABLE vehicle DROP FOREIGN KEY vehicle_ibfk_2', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_sid = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='vehicle' AND COLUMN_NAME='status_id');
SET @sql = IF(@has_sid > 0, 'ALTER TABLE vehicle DROP COLUMN status_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tabla vehicle_odometer_log
CREATE TABLE IF NOT EXISTS vehicle_odometer_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vehicle_id INT NOT NULL,
  km_reading DECIMAL(10,1) NOT NULL,
  previous_km DECIMAL(10,1) NOT NULL,
  delta_km DECIMAL(10,1) NOT NULL DEFAULT 0.00,
  reading_type ENUM('manual','inicio_ruta','fin_ruta','entrega_llaves','devolucion_llaves','inspeccion','mantenimiento','reparacion','otro') NOT NULL DEFAULT 'manual',
  route_run_id INT NULL,
  user_id INT NOT NULL,
  notes VARCHAR(500) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (vehicle_id) REFERENCES vehicle(id) ON DELETE CASCADE,
  FOREIGN KEY (route_run_id) REFERENCES route_run(id) ON DELETE SET NULL,
  FOREIGN KEY (user_id) REFERENCES `user`(id) ON DELETE RESTRICT,
  INDEX idx_vehicle_odometer_log_vehicle (vehicle_id),
  INDEX idx_vehicle_odometer_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
