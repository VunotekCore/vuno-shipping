-- Migration 008: Add position to route_zone for ordered zone selection.
-- El orden de seleccion de zonas define la trayectoria de la ruta
-- (primera zona = inicio, ultima = fin).

ALTER TABLE route_zone
    ADD COLUMN position INT NOT NULL DEFAULT 0;
