-- Migration 010: Camino A — Ruta maestra + route_run (despacho diario)
-- Separa la ruta geografica fija (delivery_route) del despacho diario (route_run).
-- Clean slate del modulo de despacho: se descartan rutas/stops/ubicaciones de prueba
-- y se eliminan los paquetes de prueba del paso 6 (se conservan paquetes 2-4, zonas,
-- vehiculos, usuarios, clientes). Idempotente: seguro para bases existentes.

-- === Limpieza de datos de despacho ===
DELETE FROM driver_location;
DELETE FROM location_history;
DELETE FROM route_stop;
DELETE FROM route_zone;
DELETE FROM delivery_route;

-- Eliminar paquetes de prueba (ids 7-12, creados para validar el paso 6)
DELETE FROM package WHERE tracking_number IN (
    'EV-V98G9L3X','EV-8YKYE2MQ','EV-HP8NJKCY','EV-ED2SE7UL','EV-PR6WTYAX','EV-8ZTU2GZQ'
);

-- === Recrear tablas con el nuevo modelo (clean slate) ===
DROP TABLE IF EXISTS route_stop;
DROP TABLE IF EXISTS route_zone;
DROP TABLE IF EXISTS delivery_route;
DROP TABLE IF EXISTS location_history;

-- Ruta maestra (template geografico fijo): sin conductor/vehiculo/horario/estado.
-- Esos campos viven en route_run.
CREATE TABLE IF NOT EXISTS delivery_route (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    trajectory JSON NULL,
    color VARCHAR(7) DEFAULT '#22c55e',
    start_latitude DECIMAL(10,8) NULL,
    start_longitude DECIMAL(11,8) NULL,
    end_latitude DECIMAL(10,8) NULL,
    end_longitude DECIMAL(11,8) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS route_zone (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    zone_id INT NOT NULL,
    position INT NOT NULL DEFAULT 0,
    UNIQUE KEY unique_route_zone (route_id, zone_id),
    FOREIGN KEY (route_id) REFERENCES delivery_route(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES zone(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Despacho diario: una instancia concreta de la ruta maestra en una fecha.
CREATE TABLE IF NOT EXISTS route_run (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    run_date DATE NOT NULL,
    assigned_to INT NULL,
    vehicle_id INT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    status_id INT NULL,
    optimized_geometry LONGTEXT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (route_id) REFERENCES delivery_route(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES `user`(id) ON DELETE SET NULL,
    FOREIGN KEY (vehicle_id) REFERENCES vehicle(id) ON DELETE SET NULL,
    FOREIGN KEY (status_id) REFERENCES route_status(id) ON DELETE SET NULL,
    INDEX idx_run_route_date (route_id, run_date),
    INDEX idx_run_driver (assigned_to),
    INDEX idx_run_vehicle (vehicle_id),
    INDEX idx_run_status (status_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paradas de entrega: pertenecen a un despacho (route_run), no a la ruta maestra.
CREATE TABLE IF NOT EXISTS route_stop (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_run_id INT NOT NULL,
    package_id INT NULL,
    stop_order INT NOT NULL,
    client_name VARCHAR(255) NULL,
    address TEXT NOT NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    estimated_arrival TIME NULL,
    actual_arrival TIME NULL,
    notes TEXT NULL,
    carried_over TINYINT(1) DEFAULT 0,
    status_id INT NULL,
    signature_data TEXT NULL,
    photo_url VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (route_run_id) REFERENCES route_run(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES package(id) ON DELETE SET NULL,
    FOREIGN KEY (status_id) REFERENCES route_stop_status(id) ON DELETE SET NULL,
    INDEX idx_stop_run (route_run_id),
    INDEX idx_stop_package (package_id),
    INDEX idx_stop_status (status_id),
    INDEX idx_stop_order (route_run_id, stop_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historico de ubicaciones: apunta al despacho (route_run), con FK real.
CREATE TABLE IF NOT EXISTS location_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vehicle_id INT NULL,
    route_run_id INT NULL,
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
    FOREIGN KEY (route_run_id) REFERENCES route_run(id) ON DELETE SET NULL,
    INDEX idx_lh_user (user_id),
    INDEX idx_lh_recorded (recorded_at),
    INDEX idx_lh_user_recorded (user_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
