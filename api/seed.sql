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
('vehicle', 'delete', 'Eliminar vehículos'),
('delivery_type', 'read', 'Ver tipos de entrega'),
('delivery_type', 'create', 'Crear tipo de entrega'),
('delivery_type', 'edit', 'Editar tipos de entrega'),
('delivery_type', 'delete', 'Eliminar tipo de entrega'),
('shipping_service', 'read', 'Ver servicios de envío'),
('shipping_service', 'create', 'Crear servicio de envío'),
('shipping_service', 'edit', 'Editar servicios de envío'),
('shipping_service', 'delete', 'Eliminar servicio de envío'),
('delivery_option', 'read', 'Ver opciones de entrega'),
('delivery_option', 'create', 'Crear opción de entrega'),
('delivery_option', 'edit', 'Editar opciones de entrega'),
('delivery_option', 'delete', 'Eliminar opción de entrega'),
('delivery_route', 'read', 'Ver rutas de despacho'),
('delivery_route', 'create', 'Crear ruta de despacho'),
('delivery_route', 'edit', 'Editar ruta de despacho'),
('delivery_route', 'delete', 'Eliminar ruta de despacho'),
('invoice', 'read', 'Ver facturas'),
('invoice', 'create', 'Crear factura'),
('invoice', 'edit', 'Editar factura'),
('invoice', 'delete', 'Eliminar factura'),
('payment_method', 'read', 'Ver métodos de pago'),
('payment_method', 'create', 'Crear método de pago'),
('payment_method', 'edit', 'Editar método de pago'),
('payment_method', 'delete', 'Eliminar método de pago')
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

-- Operador: package (create/read/edit), client (create/read), zone (read), pricing_rule (read),
-- delivery_type (read), shipping_service (read), delivery_option (read)
INSERT INTO role_permission (role_id, permission_id)
SELECT 4, id FROM permission WHERE
  (module = 'package' AND action IN ('create', 'read', 'edit')) OR
  (module = 'client' AND action IN ('create', 'read')) OR
  (module = 'zone' AND action IN ('read')) OR
  (module = 'pricing_rule' AND action IN ('read')) OR
  (module = 'delivery_type' AND action IN ('read')) OR
  (module = 'shipping_service' AND action IN ('read')) OR
  (module = 'delivery_option' AND action IN ('read'))
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);

-- Operador Escáner: package (read/edit), delivery_type (read), shipping_service (read), delivery_option (read)
INSERT INTO role_permission (role_id, permission_id)
SELECT 5, id FROM permission WHERE
  (module = 'package' AND action IN ('read', 'edit')) OR
  (module = 'delivery_type' AND action IN ('read')) OR
  (module = 'shipping_service' AND action IN ('read')) OR
  (module = 'delivery_option' AND action IN ('read'))
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);

-- === Rol Conductor ===

INSERT INTO role (name, slug) VALUES
('Conductor', 'conductor')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Conductor: delivery_route (read/edit), vehicle (read), package (read/edit)
INSERT INTO role_permission (role_id, permission_id)
SELECT 6, id FROM permission WHERE
  (module = 'delivery_route' AND action IN ('read', 'edit')) OR
  (module = 'vehicle' AND action IN ('read')) OR
  (module = 'package' AND action IN ('read', 'edit'))
ON DUPLICATE KEY UPDATE permission_id = VALUES(permission_id);

-- Admin user default
INSERT INTO `user` (email, password, name, role_id, must_change_password) VALUES
('admin@vunotek.com', '$2y$12$SqgYTSRfcfOHNwS/hGnFTOq25Jlr5vF/W0n5IGFN9lJOARydo2M.C', 'Admin', 1, 1)
ON DUPLICATE KEY UPDATE email = email;

-- === Delivery Types ===

INSERT INTO delivery_type (name, description, category) VALUES
('door', 'Entrega a domicilio', 'local'),
('branch', 'Retiro en sucursal', 'local'),
('aereo', 'Envío aéreo internacional', 'international'),
('maritimo', 'Envío marítimo internacional', 'international'),
('express', 'Entrega express el mismo día', 'local')
ON DUPLICATE KEY UPDATE description = VALUES(description), category = VALUES(category);

-- === Shipping Services ===

INSERT INTO shipping_service (name, description, delivery_type_id) VALUES
('Aéreo', 'Envío aéreo internacional desde Miami', 3),
('Marítimo', 'Envío marítimo internacional desde Miami', 4)
ON DUPLICATE KEY UPDATE description = VALUES(description), delivery_type_id = VALUES(delivery_type_id);

-- === Delivery Options ===

INSERT INTO delivery_option (name, description) VALUES
('door', 'Entrega a domicilio'),
('branch', 'Retiro en sucursal'),
('express', 'Entrega express el mismo día'),
('third_party', 'Retiro por tercero')
ON DUPLICATE KEY UPDATE description = VALUES(description);

-- === Service → Delivery Option mapping ===

INSERT INTO service_delivery_option (shipping_service_id, delivery_option_id, is_default) VALUES
(1, 1, 1),
(1, 2, 0),
(1, 3, 0),
(2, 1, 1),
(2, 2, 0),
(2, 3, 0)
ON DUPLICATE KEY UPDATE is_default = VALUES(is_default);

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
  base_price = VALUES(base_price), price_per_kg = VALUES(price_per_kg),
  price_per_volume = VALUES(price_per_volume), percentage_of_value = VALUES(percentage_of_value),
  min_price = VALUES(min_price), volumetric_divisor = VALUES(volumetric_divisor);

-- === Package Statuses ===

INSERT INTO package_status (name, description) VALUES
('pending', 'Paquete registrado, pendiente de recepción'),
('received', 'Recibido en almacén Miami'),
('in_transit', 'En tránsito a Nicaragua (aduana incluida)'),
('in_nicaragua', 'Recibido en Nicaragua, listo para entrega local'),
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
('received', 'cancelled'),
('in_transit', 'in_nicaragua'),
('in_nicaragua', 'out_for_delivery'),
('in_nicaragua', 'cancelled'),
('out_for_delivery', 'delivered'),
('out_for_delivery', 'returned'),
('delivered', 'disputed'),
('returned', 'disputed'),
('pending', 'cancelled')
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

-- === Subzones (153 municipios oficiales, mapeados por zone.name) ===

INSERT IGNORE INTO subzone (zone_id, name)
SELECT id, 'Boaco' FROM zone WHERE name = 'Boaco' UNION ALL
SELECT id, 'Camoapa' FROM zone WHERE name = 'Boaco' UNION ALL
SELECT id, 'San José de los Remates' FROM zone WHERE name = 'Boaco' UNION ALL
SELECT id, 'San Lorenzo' FROM zone WHERE name = 'Boaco' UNION ALL
SELECT id, 'Santa Lucía' FROM zone WHERE name = 'Boaco' UNION ALL
SELECT id, 'Teustepe' FROM zone WHERE name = 'Boaco' UNION ALL

SELECT id, 'Diriamba' FROM zone WHERE name = 'Carazo' UNION ALL
SELECT id, 'Dolores' FROM zone WHERE name = 'Carazo' UNION ALL
SELECT id, 'El Rosario' FROM zone WHERE name = 'Carazo' UNION ALL
SELECT id, 'Jinotepe' FROM zone WHERE name = 'Carazo' UNION ALL
SELECT id, 'La Conquista' FROM zone WHERE name = 'Carazo' UNION ALL
SELECT id, 'La Paz de Carazo' FROM zone WHERE name = 'Carazo' UNION ALL
SELECT id, 'San Marcos' FROM zone WHERE name = 'Carazo' UNION ALL
SELECT id, 'Santa Teresa' FROM zone WHERE name = 'Carazo' UNION ALL

SELECT id, 'Chichigalpa' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Chinandega' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Cinco Pinos' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Corinto' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'El Realejo' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'El Viejo' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Posoltega' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Puerto Morazán' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'San Francisco del Norte' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'San Pedro del Norte' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Somotillo' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Santo Tomás del Norte' FROM zone WHERE name = 'Chinandega' UNION ALL
SELECT id, 'Villanueva' FROM zone WHERE name = 'Chinandega' UNION ALL

SELECT id, 'Acoyapa' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'Comalapa' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'El Coral' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'Juigalpa' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'La Libertad' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'San Francisco de Cuapa' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'San Pedro de Lóvago' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'Santo Domingo' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'Santo Tomás' FROM zone WHERE name = 'Chontales' UNION ALL
SELECT id, 'Villa Sandino' FROM zone WHERE name = 'Chontales' UNION ALL

SELECT id, 'Condega' FROM zone WHERE name = 'Estelí' UNION ALL
SELECT id, 'Estelí' FROM zone WHERE name = 'Estelí' UNION ALL
SELECT id, 'La Trinidad' FROM zone WHERE name = 'Estelí' UNION ALL
SELECT id, 'Pueblo Nuevo' FROM zone WHERE name = 'Estelí' UNION ALL
SELECT id, 'San Juan de Limay' FROM zone WHERE name = 'Estelí' UNION ALL
SELECT id, 'San Nicolás' FROM zone WHERE name = 'Estelí' UNION ALL

SELECT id, 'Diriá' FROM zone WHERE name = 'Granada' UNION ALL
SELECT id, 'Diriomo' FROM zone WHERE name = 'Granada' UNION ALL
SELECT id, 'Granada' FROM zone WHERE name = 'Granada' UNION ALL
SELECT id, 'Nandaime' FROM zone WHERE name = 'Granada' UNION ALL

SELECT id, 'El Cuá' FROM zone WHERE name = 'Jinotega' UNION ALL
SELECT id, 'Jinotega' FROM zone WHERE name = 'Jinotega' UNION ALL
SELECT id, 'La Concordia' FROM zone WHERE name = 'Jinotega' UNION ALL
SELECT id, 'San José de Bocay' FROM zone WHERE name = 'Jinotega' UNION ALL
SELECT id, 'San Rafael del Norte' FROM zone WHERE name = 'Jinotega' UNION ALL
SELECT id, 'San Sebastián de Yalí' FROM zone WHERE name = 'Jinotega' UNION ALL
SELECT id, 'Santa María de Pantasma' FROM zone WHERE name = 'Jinotega' UNION ALL
SELECT id, 'Wiwilí de Jinotega' FROM zone WHERE name = 'Jinotega' UNION ALL

SELECT id, 'Achuapa' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'El Jicaral' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'El Sauce' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'La Paz Centro' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'Larreynaga' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'León' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'Nagarote' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'Quezalguaque' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'Santa Rosa del Peñón' FROM zone WHERE name = 'León' UNION ALL
SELECT id, 'Telica' FROM zone WHERE name = 'León' UNION ALL

SELECT id, 'Las Sabanas' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'Palacagüina' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'San José de Cusmapa' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'San Juan de Río Coco' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'San Lucas' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'Somoto' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'Telpaneca' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'Totogalpa' FROM zone WHERE name = 'Madriz' UNION ALL
SELECT id, 'Yalagüina' FROM zone WHERE name = 'Madriz' UNION ALL

SELECT id, 'Ciudad Sandino' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'El Crucero' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'Managua' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'Mateare' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'San Francisco Libre' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'San Rafael del Sur' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'Ticuantepe' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'Tipitapa' FROM zone WHERE name = 'Managua' UNION ALL
SELECT id, 'Villa El Carmen' FROM zone WHERE name = 'Managua' UNION ALL

SELECT id, 'Catarina' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'La Concepción' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'Masatepe' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'Masaya' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'Nandasmo' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'Nindirí' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'Niquinohomo' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'San Juan de Oriente' FROM zone WHERE name = 'Masaya' UNION ALL
SELECT id, 'Tisma' FROM zone WHERE name = 'Masaya' UNION ALL

SELECT id, 'Ciudad Darío' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'El Tuma - La Dalia' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Esquipulas' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Matagalpa' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Matiguás' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Muy Muy' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Rancho Grande' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Río Blanco' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'San Dionisio' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'San Isidro' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'San Ramón' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Sébaco' FROM zone WHERE name = 'Matagalpa' UNION ALL
SELECT id, 'Terrabona' FROM zone WHERE name = 'Matagalpa' UNION ALL

SELECT id, 'Ciudad Antigua' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Dipilto' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'El Jícaro' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Jalapa' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Macuelizo' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Mozonte' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Murra' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Ocotal' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Quilalí' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'San Fernando' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Santa María' FROM zone WHERE name = 'Nueva Segovia' UNION ALL
SELECT id, 'Wiwilí de Nueva Segovia' FROM zone WHERE name = 'Nueva Segovia' UNION ALL

SELECT id, 'El Almendro' FROM zone WHERE name = 'Río San Juan' UNION ALL
SELECT id, 'El Castillo' FROM zone WHERE name = 'Río San Juan' UNION ALL
SELECT id, 'Morrito' FROM zone WHERE name = 'Río San Juan' UNION ALL
SELECT id, 'San Carlos' FROM zone WHERE name = 'Río San Juan' UNION ALL
SELECT id, 'San Juan del Norte' FROM zone WHERE name = 'Río San Juan' UNION ALL
SELECT id, 'San Miguelito' FROM zone WHERE name = 'Río San Juan' UNION ALL

SELECT id, 'Altagracia' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'Belén' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'Buenos Aires' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'Cárdenas' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'Moyogalpa' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'Potosí' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'Rivas' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'San Jorge' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'San Juan del Sur' FROM zone WHERE name = 'Rivas' UNION ALL
SELECT id, 'Tola' FROM zone WHERE name = 'Rivas' UNION ALL

SELECT id, 'Bonanza' FROM zone WHERE name = 'Atlántico Norte' UNION ALL
SELECT id, 'Mulukukú' FROM zone WHERE name = 'Atlántico Norte' UNION ALL
SELECT id, 'Prinzapolka' FROM zone WHERE name = 'Atlántico Norte' UNION ALL
SELECT id, 'Puerto Cabezas' FROM zone WHERE name = 'Atlántico Norte' UNION ALL
SELECT id, 'Rosita' FROM zone WHERE name = 'Atlántico Norte' UNION ALL
SELECT id, 'Siuna' FROM zone WHERE name = 'Atlántico Norte' UNION ALL
SELECT id, 'Waslala' FROM zone WHERE name = 'Atlántico Norte' UNION ALL
SELECT id, 'Waspán' FROM zone WHERE name = 'Atlántico Norte' UNION ALL

SELECT id, 'Bluefields' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'Corn Island' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'Desembocadura de Río Grande' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'El Ayote' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'El Rama' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'El Tortuguero' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'Kukra Hill' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'La Cruz de Río Grande' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'Laguna de Perlas' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'Muelle de los Bueyes' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'Nueva Guinea' FROM zone WHERE name = 'Atlántico Sur' UNION ALL
SELECT id, 'Paiwas' FROM zone WHERE name = 'Atlántico Sur';

-- === Document Sequences ===

INSERT INTO document_sequence (document_code, description, prefix, current_number) VALUES
('tracking', 'Código de rastreo', 'EV', 0),
('invoice', 'Factura de cobro', 'FAC', 0)
ON DUPLICATE KEY UPDATE current_number = current_number;

-- === Billing Module ===

INSERT IGNORE INTO payment_method (name, description, is_active) VALUES
('Efectivo', 'Pago en efectivo', 1),
('Transferencia', 'Transferencia bancaria', 1),
('Tarjeta', 'Pago con tarjeta (débito/crédito)', 1);

INSERT INTO billing_config (id, tax_enabled, tax_rate, show_client_ruc, exchange_rate_usd, invoice_prefix)
VALUES (1, 0, 15.00, 0, 36.00, 'FAC')
ON DUPLICATE KEY UPDATE id = id;
