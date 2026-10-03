<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class BillingConfigModel
{
    private const DEFAULT_ROW = [
        'tax_enabled' => 0,
        'tax_rate' => 15.00,
        'show_client_ruc' => 0,
        'exchange_rate_usd' => 36.00,
    ];

    public function __construct(private PDO $db) {}

    public function get(): array
    {
        $stmt = $this->db->query('SELECT * FROM billing_config WHERE id = 1 LIMIT 1');
        $row = $stmt->fetch();
        return $row ?: self::DEFAULT_ROW;
    }

    public function update(array $data): bool
    {
        $fields = [];
        $params = [];

        foreach (['tax_enabled', 'tax_rate', 'show_client_ruc', 'exchange_rate_usd'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE billing_config SET ' . implode(', ', $fields) . ' WHERE id = 1';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}