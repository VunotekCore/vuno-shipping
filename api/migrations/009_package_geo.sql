-- Migration 009: Add geolocation to packages.
-- Las coordenadas del punto exacto de entrega se capturan en recepcion
-- (Admision) con el mini modal de mapa. Si no se capturan, las paradas de la
-- ruta usan el centro de la zona como fallback.

ALTER TABLE package
    ADD COLUMN latitude DECIMAL(10,8) NULL AFTER recipient_department,
    ADD COLUMN longitude DECIMAL(11,8) NULL AFTER latitude;
