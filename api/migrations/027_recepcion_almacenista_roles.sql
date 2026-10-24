-- Separar admision/recepcion de almacenaje
-- Agrega roles recepcion y almacenista, ajusta permisos

SET NAMES utf8mb4;

INSERT IGNORE INTO role (name, slug) VALUES
('Recepción / Admisión', 'recepcion'),
('Almacenista', 'almacenista');

-- Recepcion: crear/leer/editar paquetes, clientes, zonas, precios, servicios/opciones, facturas (lectura)
DELETE rp FROM role_permission rp
JOIN role r ON r.id = rp.role_id
WHERE r.slug = 'recepcion';

INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id
FROM role r
JOIN permission p ON (
  (p.module = 'package' AND p.action IN ('create','read','edit')) OR
  (p.module = 'client' AND p.action IN ('create','read')) OR
  (p.module = 'zone' AND p.action IN ('read')) OR
  (p.module = 'pricing_rule' AND p.action IN ('read')) OR
  (p.module = 'delivery_type' AND p.action IN ('read')) OR
  (p.module = 'shipping_service' AND p.action IN ('read')) OR
  (p.module = 'delivery_option' AND p.action IN ('read')) OR
  (p.module = 'invoice' AND p.action IN ('read'))
)
WHERE r.slug = 'recepcion';

-- Almacenista: bodegas, clasificaciones, ubicaciones y gestion de paquetes
DELETE rp FROM role_permission rp
JOIN role r ON r.id = rp.role_id
WHERE r.slug = 'almacenista';

INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id
FROM role r
JOIN permission p ON (
  (p.module IN ('warehouse','storage_category','storage_location') AND p.action IN ('create','read','edit','delete')) OR
  (p.module = 'package' AND p.action IN ('read','edit'))
)
WHERE r.slug = 'almacenista';

-- Quitar permisos de almacenaje al rol Operador para evitar ver /almacenaje
DELETE rp FROM role_permission rp
JOIN role r ON r.id = rp.role_id
JOIN permission p ON p.id = rp.permission_id
WHERE r.slug = 'operador'
  AND p.module IN ('warehouse','storage_category','storage_location');
