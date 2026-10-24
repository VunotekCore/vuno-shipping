-- Migration 026: Add recipient_company to quote
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'quote'
    AND COLUMN_NAME = 'recipient_company'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE quote ADD COLUMN recipient_company VARCHAR(150) NULL AFTER recipient_name',
  'SELECT "quote.recipient_company already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
