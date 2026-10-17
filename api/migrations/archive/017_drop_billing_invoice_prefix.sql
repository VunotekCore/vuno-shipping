-- 017_drop_billing_invoice_prefix.sql
-- El prefijo de factura pasa a residir únicamente en document_sequence (document_code = 'invoice')

SET @db := DATABASE();

-- Eliminar columna invoice_prefix si existe
SET @has_col = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'billing_config' AND COLUMN_NAME = 'invoice_prefix'
);
SET @sql = IF(@has_col > 0,
  'ALTER TABLE billing_config DROP COLUMN invoice_prefix',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Asegurar que la secuencia invoice exista (por si alguna BD antigua no la tiene)
INSERT IGNORE INTO document_sequence (document_code, description, prefix, current_number) VALUES
('invoice', 'Factura de cobro', 'FAC', 0);
