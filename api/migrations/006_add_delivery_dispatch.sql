-- Migration 006: Phase 4 - Despacho y App de Conductores
-- Crea las tablas de despacho (rutas, paradas, ubicaciones), los permisos
-- delivery_route.* y el rol Conductor. Idempotente: seguro para bases existentes.

-- === Tablas de despacho ===

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

-- === Permisos delivery_route.* ===

INSERT IGNORE INTO permission (module, action, label) VALUES
    ('delivery_route', 'create', 'Crear ruta de despacho'),
    ('delivery_route', 'read', 'Ver rutas de despacho'),
    ('delivery_route', 'edit', 'Editar ruta de despacho'),
    ('delivery_route', 'delete', 'Eliminar ruta de despacho');

-- Admin: todos los permisos de delivery_route
INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id
FROM role r, permission p
WHERE r.slug = 'admin' AND p.module = 'delivery_route';

-- Editor: read + edit
INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id
FROM role r, permission p
WHERE r.slug = 'editor' AND p.module = 'delivery_route' AND p.action IN ('read', 'edit');

-- Viewer: solo read
INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id
FROM role r, permission p
WHERE r.slug = 'viewer' AND p.module = 'delivery_route' AND p.action = 'read';

-- === Rol Conductor ===

INSERT INTO role (name, slug) VALUES ('Conductor', 'conductor')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Conductor: delivery_route (read/edit), vehicle (read), package (read/edit)
INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id
FROM role r, permission p
WHERE r.slug = 'conductor' AND (
    (p.module = 'delivery_route' AND p.action IN ('read', 'edit')) OR
    (p.module = 'vehicle' AND p.action = 'read') OR
    (p.module = 'package' AND p.action IN ('read', 'edit'))
);
