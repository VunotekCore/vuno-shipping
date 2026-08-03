-- vuno_db_shipping
-- Shipping Agency — Backend Schema

-- === RBAC Tables ===

CREATE TABLE IF NOT EXISTS permission (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(30) NOT NULL,
    label VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_module_action (module, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permission (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    FOREIGN KEY (role_id) REFERENCES role(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permission(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id),
    INDEX idx_role_permission_role (role_id),
    INDEX idx_role_permission_permission (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    role_id INT NOT NULL,
    must_change_password TINYINT(1) DEFAULT 0,
    last_read_notification_id INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES role(id) ON DELETE RESTRICT,
    INDEX idx_user_role (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permission (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_id INT NOT NULL,
    granted TINYINT(1) DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES `user`(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permission(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_permission (user_id, permission_id),
    INDEX idx_user_permission_user (user_id),
    INDEX idx_user_permission_permission (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS token_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jti VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_blacklist_jti (jti),
    INDEX idx_token_blacklist_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS refresh_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES `user`(id) ON DELETE CASCADE,
    INDEX idx_refresh_tokens_user (user_id),
    INDEX idx_refresh_tokens_hash (token_hash),
    INDEX idx_refresh_tokens_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- === Config Tables ===

CREATE TABLE IF NOT EXISTS company (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    logo VARCHAR(255) NULL,
    ruc VARCHAR(30) NULL,
    address TEXT NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(255) NULL,
    website VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_sequence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_code VARCHAR(20) NOT NULL UNIQUE,
    description VARCHAR(100) NULL,
    prefix VARCHAR(10) NULL,
    current_number INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS area (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS puesto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    area_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id) REFERENCES area(id) ON DELETE RESTRICT,
    INDEX idx_puesto_area (area_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS empleado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(30) NULL,
    cedula VARCHAR(30) NULL UNIQUE,
    fecha_ingreso DATE NULL,
    puesto_id INT NOT NULL,
    user_id INT NULL UNIQUE,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (puesto_id) REFERENCES puesto(id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES `user`(id) ON DELETE SET NULL,
    INDEX idx_empleado_puesto (puesto_id),
    INDEX idx_empleado_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- === Phase 3: Shipping Tables ===

CREATE TABLE IF NOT EXISTS client (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    address TEXT NULL,
    department VARCHAR(100) NULL,
    notes TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS zone (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    lat DECIMAL(10,8) NULL,
    lng DECIMAL(11,8) NULL,
    surcharge DECIMAL(10,2) DEFAULT 0.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subzone (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    lat_offset DECIMAL(8,6) DEFAULT 0.000000,
    lng_offset DECIMAL(8,6) DEFAULT 0.000000,
    UNIQUE KEY unique_zone_subzone (zone_id, name),
    FOREIGN KEY (zone_id) REFERENCES zone(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_type (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    category VARCHAR(20) DEFAULT 'international',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pricing_rule_type (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pricing_rule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type_id INT NULL,
    delivery_type_id INT NULL,
    base_price DECIMAL(10,2) DEFAULT 0.00,
    min_weight DECIMAL(10,2) NULL,
    max_weight DECIMAL(10,2) NULL,
    price_per_kg DECIMAL(10,2) NULL,
    price_per_volume DECIMAL(10,2) NULL,
    price_per_km DECIMAL(10,2) NULL,
    price_per_zone DECIMAL(10,2) NULL,
    percentage_of_value DECIMAL(5,2) DEFAULT 0.00,
    min_price DECIMAL(10,2) NULL,
    volumetric_divisor INT DEFAULT 166,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (type_id) REFERENCES pricing_rule_type(id) ON DELETE SET NULL,
    FOREIGN KEY (delivery_type_id) REFERENCES delivery_type(id) ON DELETE SET NULL,
    INDEX idx_pricing_type (type_id),
    INDEX idx_pricing_delivery_type (delivery_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pricing_rule_zone (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pricing_rule_id INT NOT NULL,
    zone_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pricing_rule_zone (pricing_rule_id, zone_id),
    FOREIGN KEY (pricing_rule_id) REFERENCES pricing_rule(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES zone(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shipping_service (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    delivery_type_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY name (name),
    FOREIGN KEY (delivery_type_id) REFERENCES delivery_type(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_option (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_delivery_option (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipping_service_id INT NOT NULL,
    delivery_option_id INT NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_service_delivery_option (shipping_service_id, delivery_option_id),
    FOREIGN KEY (shipping_service_id) REFERENCES shipping_service(id) ON DELETE CASCADE,
    FOREIGN KEY (delivery_option_id) REFERENCES delivery_option(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS package_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS package (
    id INT AUTO_INCREMENT,
    tracking_number VARCHAR(50) NOT NULL,
    tracking_token VARCHAR(64) NULL,
    client_id INT NULL,
    sender_name VARCHAR(255) NOT NULL,
    sender_phone VARCHAR(50) NULL,
    recipient_name VARCHAR(255) NOT NULL,
    recipient_phone VARCHAR(50) NOT NULL,
    recipient_address TEXT NOT NULL,
    recipient_city VARCHAR(100) NULL,
    recipient_department VARCHAR(100) NULL,
    zone_id INT NULL,
    description TEXT NULL,
    weight DECIMAL(10,2) NULL,
    length DECIMAL(10,2) NULL,
    width DECIMAL(10,2) NULL,
    height DECIMAL(10,2) NULL,
    volumetric_weight DECIMAL(10,2) DEFAULT NULL,
    chargeable_weight DECIMAL(10,2) DEFAULT NULL,
    declared_value DECIMAL(10,2) NULL,
    flete_cost DECIMAL(10,2) DEFAULT 0.00,
    delivery_cost DECIMAL(10,2) DEFAULT 0.00,
    shipping_cost DECIMAL(10,2) NULL,
    pricing_rule_id INT NULL,
    delivery_type_id INT NULL,
    shipping_service_id INT NULL,
    delivery_option VARCHAR(20) DEFAULT 'sucursal',
    local_delivery_type_id INT NULL,
    delivery_option_id INT NULL,
    pricing_type VARCHAR(20) DEFAULT 'weight',
    status_id INT NULL,
    assigned_to INT NULL,
    route_id INT NULL,
    notes TEXT NULL,
    received_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY tracking_number (tracking_number),
    UNIQUE KEY uk_packages_tracking_token (tracking_token),
    FOREIGN KEY (client_id) REFERENCES client(id) ON DELETE SET NULL,
    FOREIGN KEY (zone_id) REFERENCES zone(id) ON DELETE SET NULL,
    FOREIGN KEY (pricing_rule_id) REFERENCES pricing_rule(id) ON DELETE SET NULL,
    FOREIGN KEY (delivery_type_id) REFERENCES delivery_type(id) ON DELETE SET NULL,
    FOREIGN KEY (shipping_service_id) REFERENCES shipping_service(id) ON DELETE SET NULL,
    FOREIGN KEY (local_delivery_type_id) REFERENCES delivery_type(id) ON DELETE SET NULL,
    FOREIGN KEY (delivery_option_id) REFERENCES delivery_option(id) ON DELETE SET NULL,
    FOREIGN KEY (status_id) REFERENCES package_status(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES `user`(id) ON DELETE SET NULL,
    INDEX idx_package_status (status_id),
    INDEX idx_package_client (client_id),
    INDEX idx_package_zone (zone_id),
    INDEX idx_package_delivery_type (delivery_type_id),
    INDEX idx_package_shipping_service (shipping_service_id),
    INDEX idx_package_assigned (assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tracking_event (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NOT NULL,
    status VARCHAR(50) NOT NULL,
    description TEXT NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    location_name VARCHAR(255) NULL,
    photo_url VARCHAR(500) NULL,
    recorded_at DATETIME NOT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES package(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES `user`(id) ON DELETE SET NULL,
    INDEX idx_tracking_package (package_id),
    INDEX idx_tracking_recorded (recorded_at),
    INDEX idx_tracking_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS package_status_transition (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_status VARCHAR(50) NOT NULL,
    to_status VARCHAR(50) NOT NULL,
    UNIQUE KEY unique_transition (from_status, to_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- === Notifications ===

CREATE TABLE IF NOT EXISTS notification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    package_id INT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'status_change',
    title VARCHAR(255) NOT NULL,
    message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (package_id) REFERENCES package(id) ON DELETE SET NULL,
    INDEX idx_notification_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate VARCHAR(20) NOT NULL,
    brand VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    year INT NULL,
    color VARCHAR(50) NULL,
    fuel_type VARCHAR(20) NULL,
    capacity_kg DECIMAL(10,2) NULL,
    capacity_m3 DECIMAL(10,2) NULL,
    current_driver_id INT NULL,
    status_id INT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_vehicles_plate (plate),
    FOREIGN KEY (current_driver_id) REFERENCES `user`(id) ON DELETE SET NULL,
    FOREIGN KEY (status_id) REFERENCES vehicle_status(id) ON DELETE SET NULL,
    INDEX idx_vehicle_status (status_id),
    INDEX idx_vehicle_driver (current_driver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- === Phase 4: Dispatch & Driver Tables ===

CREATE TABLE IF NOT EXISTS route_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO route_status (name, description) VALUES
    ('draft', 'Ruta en planificacion'),
    ('active', 'Ruta en curso'),
    ('completed', 'Ruta finalizada'),
    ('cancelled', 'Ruta cancelada');

CREATE TABLE IF NOT EXISTS delivery_route (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    assigned_to INT NULL,
    vehicle_id INT NULL,
    scheduled_date DATE NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    status_id INT NULL,
    trajectory JSON NULL,
    optimized_geometry LONGTEXT NULL,
    color VARCHAR(7) DEFAULT '#22c55e',
    start_latitude DECIMAL(10,8) NULL,
    start_longitude DECIMAL(11,8) NULL,
    end_latitude DECIMAL(10,8) NULL,
    end_longitude DECIMAL(11,8) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES `user`(id) ON DELETE SET NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicle(id) ON DELETE SET NULL,
    FOREIGN KEY (status_id) REFERENCES route_status(id) ON DELETE SET NULL,
    INDEX idx_route_driver (assigned_to),
    INDEX idx_route_vehicle (vehicle_id),
    INDEX idx_route_status (status_id),
    INDEX idx_route_date (scheduled_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS route_zone (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    zone_id INT NOT NULL,
    UNIQUE KEY unique_route_zone (route_id, zone_id),
    FOREIGN KEY (route_id) REFERENCES delivery_route(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES zone(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS route_stop_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO route_stop_status (name, description) VALUES
    ('pending', 'Pendiente de visitar'),
    ('visited', 'Visitada'),
    ('skipped', 'Omitida'),
    ('failed', 'Fallo la entrega');

CREATE TABLE IF NOT EXISTS route_stop (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    package_id INT NULL,
    stop_order INT NOT NULL,
    client_name VARCHAR(255) NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    estimated_arrival TIME NULL,
    actual_arrival TIME NULL,
    notes TEXT NULL,
    status_id INT NULL,
    signature_data TEXT NULL,
    photo_url VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES delivery_route(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES package(id) ON DELETE SET NULL,
    FOREIGN KEY (status_id) REFERENCES route_stop_status(id) ON DELETE SET NULL,
    INDEX idx_stop_route (route_id),
    INDEX idx_stop_package (package_id),
    INDEX idx_stop_status (status_id),
    INDEX idx_stop_order (route_id, stop_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS driver_location (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    accuracy DECIMAL(8,2) NULL,
    speed DECIMAL(8,2) NULL,
    heading DECIMAL(8,2) NULL,
    battery_level INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES `user`(id) ON DELETE CASCADE,
    INDEX idx_dloc_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS location_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_id INT NULL,
    route_id INT NULL,
    latitude DECIMAL(10,8) NOT NULL,
    longitude DECIMAL(11,8) NOT NULL,
    accuracy DECIMAL(8,2) NULL,
    speed DECIMAL(8,2) NULL,
    heading DECIMAL(5,2) NULL,
    battery_level DECIMAL(5,2) NULL,
    recorded_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES `user`(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES vehicle(id) ON DELETE SET NULL,
    INDEX idx_lh_user (user_id),
    INDEX idx_lh_recorded (recorded_at),
    INDEX idx_lh_user_recorded (user_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
