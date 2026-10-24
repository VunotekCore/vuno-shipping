-- Migration 023: Add delivery_frequency to delivery_type
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'delivery_type'
    AND COLUMN_NAME = 'delivery_frequency'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE delivery_type ADD COLUMN delivery_frequency VARCHAR(255) NULL AFTER description',
  'SELECT "delivery_frequency already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
