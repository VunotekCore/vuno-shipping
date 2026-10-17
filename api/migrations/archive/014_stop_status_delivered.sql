-- Migration 014: Renombrar estado de parada 'visited' -> 'delivered'.
-- La parada entregada debe reflejar semànticamente la entrega del paquete,
-- para mantener consistencia con package_status 'delivered'. Idempotente.

UPDATE route_stop_status
SET name = 'delivered', description = 'Entregada al destinatario'
WHERE name = 'visited';

INSERT IGNORE INTO route_stop_status (name, description)
VALUES ('delivered', 'Entregada al destinatario');