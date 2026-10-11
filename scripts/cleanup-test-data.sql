-- scripts/cleanup-test-data.sql
-- SOLO DESARROLLO. Limpia los artefactos que genera scripts/test-api.sh.
--
-- Pensado como "self-cleanup": test-api.sh lo ejecuta automaticamente al final
-- (salvo SKIP_CLEANUP=1). Idempotente: se puede correr las veces que sea sin error.
--
-- Marcadores (documentados):
--   - Clientes de prueba: id 2 y 6. Se borran sus paquetes/cotizaciones creados HOY.
--   - Despachos de prueba: run_date >= '2027-01-01' (fecha futura que usan las suites).
--   - Embarques de prueba: code con prefijo 'QATEST-'.
--   - Odometro del vehiculo 2 y notificaciones in-app creadas HOY.
--   - Se restauran los contadores de document_sequence a partir de las filas restantes.
--
-- Uso manual:
--   mysql -h<host> -u<user> -p<pass> <db> < scripts/cleanup-test-data.sql

SET @today = CURDATE();

-- ---------------------------------------------------------------------------
-- Dry-run: cuantas filas se van a borrar (informativo)
-- ---------------------------------------------------------------------------
SELECT 'packages' AS entidad, COUNT(*) AS filas
  FROM package WHERE client_id IN (2, 6) AND DATE(created_at) = @today
UNION ALL
SELECT 'quotes', COUNT(*)
  FROM quote WHERE DATE(created_at) = @today AND (client_id IN (2, 6) OR agent_id IN (1, 6))
UNION ALL
SELECT 'route_run', COUNT(*)
  FROM route_run WHERE run_date >= '2027-01-01'
UNION ALL
SELECT 'shipments', COUNT(*)
  FROM shipment WHERE code LIKE 'QATEST-%'
UNION ALL
SELECT 'vehicle_odometer_log', COUNT(*)
  FROM vehicle_odometer_log WHERE vehicle_id = 2 AND DATE(created_at) = @today
UNION ALL
SELECT 'storage_locations_QA', COUNT(*)
  FROM storage_location WHERE warehouse_id IN (SELECT id FROM warehouse WHERE code LIKE 'QATEST-%')
UNION ALL
SELECT 'warehouse_qa', COUNT(*)
  FROM warehouse WHERE code LIKE 'QATEST-%'
UNION ALL
SELECT 'storage_category QA', COUNT(*)
  FROM storage_category WHERE name LIKE 'QATEST%'
UNION ALL
SELECT 'notification', COUNT(*)
  FROM notification WHERE DATE(created_at) = @today;
-- ---------------------------------------------------------------------------
-- Borrado (cascadas: package -> invoice/tracking_event/shipment_package)
-- ---------------------------------------------------------------------------
START TRANSACTION;

DELETE FROM package   WHERE client_id IN (2, 6) AND DATE(created_at) = @today;
DELETE FROM quote     WHERE DATE(created_at) = @today AND (client_id IN (2, 6) OR agent_id IN (1, 6));
DELETE FROM shipment  WHERE code LIKE 'QATEST-%';
DELETE FROM route_run WHERE run_date >= '2027-01-01';
DELETE FROM vehicle_odometer_log WHERE vehicle_id = 2 AND DATE(created_at) = @today;
DELETE FROM notification WHERE DATE(created_at) = @today;

-- Artefactos de almacenaje (Suite I). package.storage_location_id -> SET NULL.
DELETE FROM storage_location WHERE warehouse_id IN (SELECT id FROM warehouse WHERE code LIKE 'QATEST-%');
DELETE FROM warehouse WHERE code LIKE 'QATEST-%';
DELETE FROM storage_category WHERE name LIKE 'QATEST%';

-- Restaurar estado base del vehiculo de prueba
UPDATE vehicle
   SET current_km = 45000, km_last_updated_at = NULL, km_last_updated_by = NULL,
       status = 'disponible'
 WHERE id = 2;

-- Re-sincronizar contadores con las filas que quedan
UPDATE document_sequence
   SET current_number = COALESCE((SELECT MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED)) FROM invoice), 0)
 WHERE document_code = 'invoice';

UPDATE document_sequence
   SET current_number = COALESCE((SELECT MAX(CAST(SUBSTRING_INDEX(quote_number, '-', -1) AS UNSIGNED)) FROM quote), 0)
 WHERE document_code = 'quote';

-- NOTA: el codigo de rastreo (tracking) es un token aleatorio (EV-XXXXXXXX),
-- no un contador, por lo que no se re-sincroniza document_sequence.tracking.

COMMIT;
