-- Migration 003: Add delivery_option column to package table
-- Separates shipping method from delivery option (domicilio vs sucursal)

ALTER TABLE package
ADD COLUMN delivery_option VARCHAR(20) DEFAULT 'sucursal'
AFTER delivery_type_id;

-- For existing packages with door/branch, map delivery_option accordingly
UPDATE package SET delivery_option = 'domicilio'
WHERE delivery_type_id IN (SELECT id FROM delivery_type WHERE name = 'door');

UPDATE package SET delivery_option = 'sucursal'
WHERE delivery_type_id IN (SELECT id FROM delivery_type WHERE name = 'branch');

-- For existing packages with shipping methods (aereo/maritimo/express), default to sucursal
UPDATE package SET delivery_option = 'sucursal'
WHERE delivery_option IS NULL;

-- Update pricing rules 4 and 5: they no longer depend on delivery_type
-- (delivery cost is now automatic based on delivery_option + zone surcharge)
UPDATE pricing_rule SET delivery_type_id = NULL WHERE id IN (4, 5);
