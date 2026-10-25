-- Migration 024: Branches and internal transfers
-- Adds branch concept, reception/pickup tracking, and internal warehouse transfers

SET NAMES utf8mb4;

-- 1) Extend warehouse with flags
SET @col_is_central := (
  SELECT COUNT(*) FROM information_schema.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'warehouse' AND COLUMN_NAME = 'is_central'
);
SET @sql_wc := IF(@col_is_central = 0, 
  "ALTER TABLE warehouse ADD COLUMN is_central TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active", 
  "SELECT 'warehouse.is_central exists'");
PREPARE stmt_wc FROM @sql_wc; EXECUTE stmt_wc; DEALLOCATE PREPARE stmt_wc;

SET @col_is_distribution := (
  SELECT COUNT(*) FROM information_schema.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'warehouse' AND COLUMN_NAME = 'is_distribution'
);
SET @sql_wd := IF(@col_is_distribution = 0, 
  "ALTER TABLE warehouse ADD COLUMN is_distribution TINYINT(1) NOT NULL DEFAULT 0 AFTER is_central", 
  "SELECT 'warehouse.is_distribution exists'");
PREPARE stmt_wd FROM @sql_wd; EXECUTE stmt_wd; DEALLOCATE PREPARE stmt_wd;

SET @col_country_code := (
  SELECT COUNT(*) FROM information_schema.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'warehouse' AND COLUMN_NAME = 'country_code'
);
SET @sql_wcc := IF(@col_country_code = 0, 
  "ALTER TABLE warehouse ADD COLUMN country_code VARCHAR(2) NULL AFTER is_distribution", 
  "SELECT 'warehouse.country_code exists'");
PREPARE stmt_wcc FROM @sql_wcc; EXECUTE stmt_wcc; DEALLOCATE PREPARE stmt_wcc;

-- 2) Create branch table
CREATE TABLE IF NOT EXISTS branch (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  name VARCHAR(150) NOT NULL,
  country_code VARCHAR(2) DEFAULT 'NI',
  type ENUM('consolidation','direct') NOT NULL DEFAULT 'direct',
  receiving_warehouse_id INT NULL,
  transfer_to_warehouse_id INT NULL,
  allows_pickup TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_branch_code (code),
  INDEX idx_branch_type (type),
  INDEX idx_branch_receiving (receiving_warehouse_id),
  INDEX idx_branch_transfer_to (transfer_to_warehouse_id),
  CONSTRAINT fk_branch_receiving_warehouse FOREIGN KEY (receiving_warehouse_id) REFERENCES warehouse(id) ON DELETE SET NULL,
  CONSTRAINT fk_branch_transfer_to_warehouse FOREIGN KEY (transfer_to_warehouse_id) REFERENCES warehouse(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Add branch columns to package
SET @col_reception_branch := (
  SELECT COUNT(*) FROM information_schema.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'package' AND COLUMN_NAME = 'reception_branch_id'
);
SET @sql_prb := IF(@col_reception_branch = 0, 
  "ALTER TABLE package ADD COLUMN reception_branch_id INT NULL AFTER origin", 
  "SELECT 'package.reception_branch_id exists'");
PREPARE stmt_prb FROM @sql_prb; EXECUTE stmt_prb; DEALLOCATE PREPARE stmt_prb;

SET @col_pickup_branch := (
  SELECT COUNT(*) FROM information_schema.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'package' AND COLUMN_NAME = 'pickup_branch_id'
);
SET @sql_ppb := IF(@col_pickup_branch = 0, 
  "ALTER TABLE package ADD COLUMN pickup_branch_id INT NULL AFTER reception_branch_id", 
  "SELECT 'package.pickup_branch_id exists'");
PREPARE stmt_ppb FROM @sql_ppb; EXECUTE stmt_ppb; DEALLOCATE PREPARE stmt_ppb;

SET @idx_prb := (
  SELECT COUNT(*) FROM information_schema.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'package' AND INDEX_NAME = 'idx_package_reception_branch'
);
SET @sql_iprb := IF(@idx_prb = 0, 
  "ALTER TABLE package ADD INDEX idx_package_reception_branch (reception_branch_id)", 
  "SELECT 'idx_package_reception_branch exists'");
PREPARE stmt_iprb FROM @sql_iprb; EXECUTE stmt_iprb; DEALLOCATE PREPARE stmt_iprb;

SET @idx_ppb := (
  SELECT COUNT(*) FROM information_schema.STATISTICS 
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'package' AND INDEX_NAME = 'idx_package_pickup_branch'
);
SET @sql_ippb := IF(@idx_ppb = 0, 
  "ALTER TABLE package ADD INDEX idx_package_pickup_branch (pickup_branch_id)", 
  "SELECT 'idx_package_pickup_branch exists'");
PREPARE stmt_ippb FROM @sql_ippb; EXECUTE stmt_ippb; DEALLOCATE PREPARE stmt_ippb;

-- Add FKs if missing
SET @fk_prb := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'package' AND CONSTRAINT_NAME = 'fk_package_reception_branch'
);
SET @sql_fprb := IF(@fk_prb = 0, 
  "ALTER TABLE package ADD CONSTRAINT fk_package_reception_branch FOREIGN KEY (reception_branch_id) REFERENCES branch(id) ON DELETE SET NULL", 
  "SELECT 'fk_package_reception_branch exists'");
PREPARE stmt_fprb FROM @sql_fprb; EXECUTE stmt_fprb; DEALLOCATE PREPARE stmt_fprb;

SET @fk_ppb := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'package' AND CONSTRAINT_NAME = 'fk_package_pickup_branch'
);
SET @sql_fppb := IF(@fk_ppb = 0, 
  "ALTER TABLE package ADD CONSTRAINT fk_package_pickup_branch FOREIGN KEY (pickup_branch_id) REFERENCES branch(id) ON DELETE SET NULL", 
  "SELECT 'fk_package_pickup_branch exists'");
PREPARE stmt_fppb FROM @sql_fppb; EXECUTE stmt_fppb; DEALLOCATE PREPARE stmt_fppb;

-- 4) Warehouse transfers
CREATE TABLE IF NOT EXISTS warehouse_transfer (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL,
  from_warehouse_id INT NOT NULL,
  to_warehouse_id INT NOT NULL,
  branch_id INT NULL,
  status ENUM('pending','in_transit','received','cancelled') NOT NULL DEFAULT 'pending',
  notes TEXT NULL,
  created_by INT NULL,
  received_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  received_at TIMESTAMP NULL,
  UNIQUE KEY uk_warehouse_transfer_code (code),
  INDEX idx_wt_from (from_warehouse_id),
  INDEX idx_wt_to (to_warehouse_id),
  INDEX idx_wt_status (status),
  INDEX idx_wt_branch (branch_id),
  CONSTRAINT fk_wt_from FOREIGN KEY (from_warehouse_id) REFERENCES warehouse(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wt_to FOREIGN KEY (to_warehouse_id) REFERENCES warehouse(id) ON DELETE RESTRICT,
  CONSTRAINT fk_wt_branch FOREIGN KEY (branch_id) REFERENCES branch(id) ON DELETE SET NULL,
  CONSTRAINT fk_wt_created_by FOREIGN KEY (created_by) REFERENCES `user`(id) ON DELETE SET NULL,
  CONSTRAINT fk_wt_received_by FOREIGN KEY (received_by) REFERENCES `user`(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS warehouse_transfer_item (
  id INT AUTO_INCREMENT PRIMARY KEY,
  transfer_id INT NOT NULL,
  package_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_wti_transfer_package (transfer_id, package_id),
  INDEX idx_wti_transfer (transfer_id),
  INDEX idx_wti_package (package_id),
  CONSTRAINT fk_wti_transfer FOREIGN KEY (transfer_id) REFERENCES warehouse_transfer(id) ON DELETE CASCADE,
  CONSTRAINT fk_wti_package FOREIGN KEY (package_id) REFERENCES package(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5) Mark existing warehouses if obvious
UPDATE warehouse SET is_central = 1, is_distribution = 1, country_code = 'NI' WHERE LOWER(name) LIKE '%central%' OR LOWER(name) LIKE '%managua%';
UPDATE warehouse SET country_code = 'US' WHERE LOWER(name) LIKE '%miami%';
