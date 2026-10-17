-- Migration 004: Add category to delivery_type + local_delivery_type_id to package
-- Categories: 'international' (aereo, maritimo) vs 'local' (door, branch, express)

ALTER TABLE delivery_type
ADD COLUMN category VARCHAR(20) DEFAULT 'international'
AFTER description;

UPDATE delivery_type SET category = 'local' WHERE name IN ('door', 'branch', 'express');
UPDATE delivery_type SET category = 'international' WHERE name IN ('aereo', 'maritimo');

ALTER TABLE package
ADD COLUMN local_delivery_type_id INT NULL
AFTER delivery_option;

-- Map existing packages: domicilio→door(1), sucursal→branch(2)
UPDATE package SET local_delivery_type_id = (SELECT id FROM delivery_type WHERE name = 'door')
WHERE delivery_option = 'domicilio';

UPDATE package SET local_delivery_type_id = (SELECT id FROM delivery_type WHERE name = 'branch')
WHERE delivery_option = 'sucursal';

-- Map existing with door/branch/express directly via delivery_type_id
UPDATE package p
JOIN delivery_type dt ON dt.id = p.delivery_type_id
SET p.local_delivery_type_id = p.delivery_type_id
WHERE dt.category = 'local' AND p.local_delivery_type_id IS NULL;
