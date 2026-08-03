-- Migration 005: Add optimized_geometry to delivery_route
-- Guarda la geometria de la ruta optima OSRM (polyline azul) para que
-- RouteDetailView la renderice y sobreviva a recargas.

ALTER TABLE delivery_route
ADD COLUMN optimized_geometry LONGTEXT NULL
AFTER trajectory;
