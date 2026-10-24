-- Agregar número de guía al paquete
SET NAMES utf8mb4;

ALTER TABLE package
ADD COLUMN guide_number VARCHAR(100) NULL AFTER description;
