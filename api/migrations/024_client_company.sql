-- Migration 024: Add company to client
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'client'
    AND COLUMN_NAME = 'company'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE client ADD COLUMN company VARCHAR(150) NULL AFTER name',
  'SELECT "client.company already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
