<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class PricingRuleModel
{
    public function __construct(private PDO $db) {}

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM pricing_rule')->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare("
            SELECT pr.*,
                   prt.name AS type_name,
                   dt.name AS delivery_type_name
            FROM pricing_rule pr
            LEFT JOIN pricing_rule_type prt ON prt.id = pr.type_id
            LEFT JOIN delivery_type dt ON dt.id = pr.delivery_type_id
            ORDER BY pr.name ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function listAll(): array
    {
        $stmt = $this->db->query("
            SELECT pr.id, pr.name, pr.base_price, prt.name AS type_name,
                   dt.name AS delivery_type_name, pr.is_active
            FROM pricing_rule pr
            LEFT JOIN pricing_rule_type prt ON prt.id = pr.type_id
            LEFT JOIN delivery_type dt ON dt.id = pr.delivery_type_id
            WHERE pr.is_active = 1
            ORDER BY pr.name ASC
        ");
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT pr.*, prt.name AS type_name, dt.name AS delivery_type_name
            FROM pricing_rule pr
            LEFT JOIN pricing_rule_type prt ON prt.id = pr.type_id
            LEFT JOIN delivery_type dt ON dt.id = pr.delivery_type_id
            WHERE pr.id = :id LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findBestMatch(?int $zoneId, string $deliveryType, ?int $typeId = null): ?array
    {
        $dtSub = '(SELECT id FROM delivery_type WHERE name = :dt_name LIMIT 1)';
        $zoneSub = '(SELECT prz.pricing_rule_id FROM pricing_rule_zone prz WHERE prz.zone_id = :zone_id)';

        $queries = [
            // 1. delivery_type + zone + type
            [
                'conditions' => ['pr.is_active = 1', "pr.delivery_type_id = {$dtSub}", "pr.id IN {$zoneSub}", 'pr.type_id = :type_id'],
                'params' => ['dt_name' => $deliveryType, 'zone_id' => $zoneId, 'type_id' => $typeId],
                'requires_zone' => true,
                'requires_type' => true,
            ],
            // 2. delivery_type + zone (any type)
            [
                'conditions' => ['pr.is_active = 1', "pr.delivery_type_id = {$dtSub}", "pr.id IN {$zoneSub}"],
                'params' => ['dt_name' => $deliveryType, 'zone_id' => $zoneId],
                'requires_zone' => true,
                'requires_type' => false,
            ],
            // 3. delivery_type + type (universal rules)
            [
                'conditions' => ['pr.is_active = 1', "pr.delivery_type_id = {$dtSub}", 'pr.type_id = :type_id2',
                    '(pr.id NOT IN (SELECT prz.pricing_rule_id FROM pricing_rule_zone prz))'],
                'params' => ['dt_name' => $deliveryType, 'type_id2' => $typeId],
                'requires_zone' => false,
                'requires_type' => true,
            ],
            // 4. delivery_type (any type, universal)
            [
                'conditions' => ['pr.is_active = 1', "pr.delivery_type_id = {$dtSub}",
                    '(pr.id NOT IN (SELECT prz.pricing_rule_id FROM pricing_rule_zone prz))'],
                'params' => ['dt_name' => $deliveryType],
                'requires_zone' => false,
                'requires_type' => false,
            ],
        ];

        foreach ($queries as $q) {
            if ($q['requires_zone'] && $zoneId === null) continue;
            if ($q['requires_type'] && $typeId === null) continue;

            $whereClause = implode(' AND ', $q['conditions']);
            $stmt = $this->db->prepare("
                SELECT pr.*, prt.name AS type_name, dt.name AS delivery_type_name
                FROM pricing_rule pr
                LEFT JOIN pricing_rule_type prt ON prt.id = pr.type_id
                LEFT JOIN delivery_type dt ON dt.id = pr.delivery_type_id
                WHERE {$whereClause}
                ORDER BY pr.name ASC
                LIMIT 1
            ");
            $stmt->execute($q['params']);
            $row = $stmt->fetch();
            if ($row !== false) {
                return $row;
            }
        }

        return null;
    }

    public function getZones(int $ruleId): array
    {
        $stmt = $this->db->prepare("
            SELECT z.id, z.name
            FROM pricing_rule_zone prz
            JOIN zone z ON z.id = prz.zone_id
            WHERE prz.pricing_rule_id = :id
        ");
        $stmt->execute(['id' => $ruleId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO pricing_rule (name, type_id, delivery_type_id, base_price, min_weight, max_weight, price_per_kg, price_per_volume, price_per_km, price_per_zone, percentage_of_value, min_price, is_active)
             VALUES (:name, :type_id, :delivery_type_id, :base_price, :min_weight, :max_weight, :price_per_kg, :price_per_volume, :price_per_km, :price_per_zone, :percentage_of_value, :min_price, :is_active)'
        );
        $stmt->execute([
            'name' => $data['name'],
            'type_id' => $data['type_id'] ?? null,
            'delivery_type_id' => $data['delivery_type_id'] ?? null,
            'base_price' => $data['base_price'] ?? 0,
            'min_weight' => $data['min_weight'] ?? null,
            'max_weight' => $data['max_weight'] ?? null,
            'price_per_kg' => $data['price_per_kg'] ?? null,
            'price_per_volume' => $data['price_per_volume'] ?? null,
            'price_per_km' => $data['price_per_km'] ?? null,
            'price_per_zone' => $data['price_per_zone'] ?? null,
            'percentage_of_value' => $data['percentage_of_value'] ?? 0,
            'min_price' => $data['min_price'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['name', 'type_id', 'delivery_type_id', 'base_price', 'min_weight', 'max_weight', 'price_per_kg', 'price_per_volume', 'price_per_km', 'price_per_zone', 'percentage_of_value', 'min_price', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE pricing_rule SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM pricing_rule WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function setZones(int $ruleId, array $zoneIds): void
    {
        $this->db->prepare('DELETE FROM pricing_rule_zone WHERE pricing_rule_id = :id')->execute(['id' => $ruleId]);

        if (!empty($zoneIds)) {
            $stmt = $this->db->prepare(
                'INSERT INTO pricing_rule_zone (pricing_rule_id, zone_id) VALUES (:rule_id, :zone_id)'
            );
            foreach ($zoneIds as $zoneId) {
                $stmt->execute(['rule_id' => $ruleId, 'zone_id' => (int) $zoneId]);
            }
        }
    }

    public function validateTypeExists(?int $typeId): bool
    {
        if ($typeId === null) {
            return true;
        }
        $stmt = $this->db->prepare('SELECT 1 FROM pricing_rule_type WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $typeId]);
        return $stmt->fetch() !== false;
    }

    public function validateDeliveryTypeExists(?int $deliveryTypeId): bool
    {
        if ($deliveryTypeId === null) {
            return true;
        }
        $stmt = $this->db->prepare('SELECT 1 FROM delivery_type WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $deliveryTypeId]);
        return $stmt->fetch() !== false;
    }
}
