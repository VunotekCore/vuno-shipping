-- vuno_db_shipping — Seed Data

-- === RBAC Permissions ===

INSERT INTO permission (module, action, label) VALUES
('user', 'read', 'Ver usuarios'),
('user', 'create', 'Crear usuario'),
('user', 'edit', 'Editar usuario'),
('user', 'delete', 'Eliminar usuario'),
('role', 'read', 'Ver roles'),
('role', 'create', 'Crear rol'),
('role', 'edit', 'Editar rol'),
('role', 'delete', 'Eliminar rol'),
('permission', 'read', 'Ver permisos'),
('permission', 'create', 'Crear permiso'),
('permission', 'edit', 'Editar permiso'),
('permission', 'delete', 'Eliminar permiso'),
('company', 'read', 'Ver empresa'),
('company', 'edit', 'Editar empresa'),
('document_sequence', 'read', 'Ver secuencias'),
('document_sequence', 'create', 'Crear secuencia'),
('document_sequence', 'edit', 'Editar secuencia'),
('document_sequence', 'delete', 'Eliminar secuencia'),
('area', 'read', 'Ver áreas'),
('area', 'create', 'Crear área'),
('area', 'edit', 'Editar áreas'),
('area', 'delete', 'Eliminar áreas'),
('puesto', 'read', 'Ver puestos'),
('puesto', 'create', 'Crear puesto'),
('puesto', 'edit', 'Editar puestos'),
('puesto', 'delete', 'Eliminar puestos'),
('empleado', 'read', 'Ver empleados'),
('empleado', 'create', 'Crear empleado'),
('empleado', 'edit', 'Editar empleados'),
('empleado', 'delete', 'Eliminar empleados'),
('client', 'read', 'Ver clientes'),
('client', 'create', 'Crear cliente'),
('client', 'edit', 'Editar clientes'),
('client', 'delete', 'Eliminar clientes'),
('zone', 'read', 'Ver zonas'),
('zone', 'create', 'Crear zona'),
('zone', 'edit', 'Editar zonas'),
('zone', 'delete', 'Eliminar zonas'),
('pricing_rule', 'read', 'Ver reglas de pricing'),
('pricing_rule', 'create', 'Crear regla de pricing'),
('pricing_rule', 'edit', 'Editar reglas de pricing'),
('pricing_rule', 'delete', 'Eliminar reglas de pricing'),
('package', 'read', 'Ver paquetes'),
('package', 'create', 'Crear paquete'),
('package', 'edit', 'Editar paquetes'),
('package', 'delete', 'Eliminar paquetes'),
('vehicle', 'read', 'Ver vehículos'),
('vehicle', 'create', 'Crear vehículo'),
('vehicle', 'edit', 'Editar vehículos'),
('vehicle', 'delete', 'Eliminar vehículos')
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- === Roles ===

INSERT INTO role (name, slug) VALUES
('Admin', 'admin'),
('Editor', 'editor'),
('Viewer', 'viewer')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Admin: todos los permisos
INSERT INTO role_permission (role_id, permission_id)
SELECT 1, id FROM permission
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);

-- Editor: read + edit
INSERT INTO role_permission (role_id, permission_id)
SELECT 2, id FROM permission WHERE action IN ('read', 'edit')
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);

-- Viewer: solo read
INSERT INTO role_permission (role_id, permission_id)
SELECT 3, id FROM permission WHERE action = 'read'
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);

-- === Roles Operativos ===

INSERT INTO role (name, slug) VALUES
('Operador', 'operador'),
('Operador Escáner', 'operador_escaner')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Operador: package (create/read/edit), client (create/read), zone (read), pricing_rule (read)
INSERT INTO role_permission (role_id, permission_id)
SELECT 4, id FROM permission WHERE
  (module = 'package' AND action IN ('create', 'read', 'edit')) OR
  (module = 'client' AND action IN ('create', 'read')) OR
  (module = 'zone' AND action IN ('read')) OR
  (module = 'pricing_rule' AND action IN ('read'))
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);

-- Operador Escáner: package (read/edit only)
INSERT INTO role_permission (role_id, permission_id)
SELECT 5, id FROM permission WHERE
  (module = 'package' AND action IN ('read', 'edit'))
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);

-- Admin user default
INSERT INTO `user` (email, password, name, role_id, must_change_password) VALUES
('admin@vunotek.com', '$2y$12$SqgYTSRfcfOHNwS/hGnFTOq25Jlr5vF/W0n5IGFN9lJOARydo2M.C', 'Admin', 1, 1)
ON DUPLICATE KEY UPDATE email = email;

-- === Delivery Types ===

INSERT INTO delivery_type (name, description) VALUES
('door', 'Entrega a domicilio'),
('branch', 'Entrega en sucursal'),
('aereo', 'Envío aéreo internacional'),
('maritimo', 'Envío marítimo internacional'),
('express', 'Entrega express el mismo día')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- === Pricing Rule Types ===

INSERT INTO pricing_rule_type (name, description) VALUES
('weight', 'Por peso del paquete'),
('volume', 'Por volumen del paquete'),
('distance', 'Por distancia en kilómetros'),
('zone', 'Por zona de entrega'),
('fixed', 'Tarifa fija'),
('percentage value', 'Porcentaje del valor declarado')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- === Pricing Rules (Default) ===

INSERT INTO pricing_rule (id, name, type_id, delivery_type_id, base_price, price_per_kg, price_per_volume, percentage_of_value, min_price, volumetric_divisor, is_active) VALUES
(1, 'Aéreo por Peso',        1, 3, 0.00,  7.00, NULL, 0.00,  5.00, 166, 1),
(2, 'Marítimo por Peso',     1, 4, 0.00,  3.00, NULL, 0.00, 10.00, 166, 1),
(3, 'Aéreo Electrónicos',    6, 3, 5.00,  0.00, NULL, 10.00, 15.00, 166, 1),
(4, 'Delivery a Domicilio',  4, NULL, 0.00,  0.00, NULL, 0.00,  0.00, 166, 1),
(5, 'Retiro en Sucursal',    5, NULL, 0.00,  0.00, NULL, 0.00,  0.00, 166, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name), type_id = VALUES(type_id), delivery_type_id = VALUES(delivery_type_id),
  base_price = VALUES(base_price), price_per_kg = VALUES(price_per_kg), price_per_volume = VALUES(price_per_volume),
  percentage_of_value = VALUES(percentage_of_value), min_price = VALUES(min_price),
  volumetric_divisor = VALUES(volumetric_divisor);

-- === Package Statuses ===

INSERT INTO package_status (name, description) VALUES
('pending', 'Paquete registrado, pendiente de recepción'),
('received', 'Recibido en bodega'),
('in_transit', 'En tránsito al destino'),
('out_for_delivery', 'En ruta de entrega'),
('delivered', 'Entregado al destinatario'),
('returned', 'Devuelto al remitente'),
('cancelled', 'Cancelado'),
('disputed', 'En disputa')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- === Status Transitions ===

INSERT INTO package_status_transition (from_status, to_status) VALUES
('pending', 'received'),
('received', 'in_transit'),
('in_transit', 'out_for_delivery'),
('out_for_delivery', 'delivered'),
('out_for_delivery', 'returned'),
('delivered', 'disputed'),
('returned', 'disputed'),
('pending', 'cancelled'),
('received', 'cancelled')
ON DUPLICATE KEY UPDATE to_status = VALUES(to_status);

-- === Vehicle Statuses ===

INSERT INTO vehicle_status (name, description) VALUES
('active', 'Vehículo activo y operativo'),
('inactive', 'Vehículo inactivo o dado de baja'),
('maintenance', 'Vehículo en mantenimiento')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- === Zones (17 departamentos de Nicaragua) ===

INSERT INTO zone (name, lat, lng, surcharge) VALUES
('Managua', 12.13640000, -86.25140000, 0.00),
('León', 12.43790000, -86.87840000, 2.00),
('Chinandega', 12.62860000, -87.13120000, 3.00),
('Granada', 11.92920000, -85.95400000, 1.50),
('Masaya', 11.97440000, -86.09420000, 0.00),
('Rivas', 11.43910000, -85.44940000, 3.00),
('Carazo', 11.65240000, -86.20170000, 1.00),
('Boaco', 12.47360000, -85.66050000, 4.00),
('Chontales', 12.21000000, -85.18770000, 4.00),
('Matagalpa', 12.92540000, -85.91740000, 5.00),
('Jinotega', 13.08870000, -86.00240000, 6.00),
('Estelí', 13.09190000, -86.35330000, 5.00),
('Madriz', 13.47310000, -86.51990000, 7.00),
('Nueva Segovia', 13.78030000, -86.45870000, 8.00),
('Río San Juan', 11.00380000, -84.77850000, 8.00),
('Atlántico Norte', 13.89900000, -84.78540000, 9.00),
('Atlántico Sur', 12.20000000, -84.20000000, 9.00)
ON DUPLICATE KEY UPDATE surcharge = VALUES(surcharge);

-- === Document Sequences ===

INSERT INTO document_sequence (document_code, description, prefix, current_number) VALUES
('tracking', 'Código de rastreo', 'EV', 0)
ON DUPLICATE KEY UPDATE current_number = current_number;
