-- Migration 007: Drop scheduled_date from delivery_route
-- Las rutas son permanentes en la agencia (no se generan por fecha);
-- el ciclo operativo se maneja por estado (draft -> active -> completed).
-- MySQL elimina automaticamente el indice idx_route_date junto a la columna.

ALTER TABLE delivery_route DROP COLUMN scheduled_date;
