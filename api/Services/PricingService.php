<?php
declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Models\PricingRuleModel;

class PricingService
{
    private \PDO $db;
    private PricingRuleModel $ruleModel;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->ruleModel = new PricingRuleModel($this->db);
    }

    /**
     * Calculate package total with full cost breakdown.
     * Used at package creation time. Saves result into package row.
     *
     * @param array $data {weight, length, width, height, declared_value, delivery_type_id}
     * @param array $pricingRule  Full pricing_rule row (with type_name)
     * @param array|null $zone  Zone row (with surcharge)
     * @return array {volumetric_weight, chargeable_weight, flete_cost, delivery_cost, total_amount}
     */
    public function calculatePackageTotal(array $data, array $pricingRule, ?array $zone = null): array
    {
        $weight = (float)($data['weight'] ?? 0);
        $length = (float)($data['length'] ?? 0);
        $width  = (float)($data['width'] ?? 0);
        $height = (float)($data['height'] ?? 0);
        $declaredValue = (float)($data['declared_value'] ?? 0);

        $divisor = (int)($pricingRule['volumetric_divisor'] ?? 166);
        $volumetricWeight = $divisor > 0 ? ($length * $width * $height) / $divisor : 0;
        $chargeableWeight = max($weight, $volumetricWeight);

        $fleteCost = (float)$pricingRule['base_price'];
        $typeName = $pricingRule['type_name'] ?? 'weight';

        if ($typeName === 'weight' && $chargeableWeight > 0) {
            $fleteCost += $chargeableWeight * (float)($pricingRule['price_per_kg'] ?? 0);
        } elseif ($typeName === 'volume' && $volumetricWeight > 0) {
            $fleteCost += $volumetricWeight * (float)($pricingRule['price_per_volume'] ?? 0);
        } elseif ($typeName === 'percentage_value' && $declaredValue > 0) {
            $fleteCost += $declaredValue * ((float)($pricingRule['percentage_of_value'] ?? 0) / 100);
        }

        $minPrice = (float)($pricingRule['min_price'] ?? 0);
        if ($minPrice > 0 && $fleteCost < $minPrice) {
            $fleteCost = $minPrice;
        }

        $deliveryCost = 0.00;
        if ((int)($data['delivery_type_id'] ?? 0) === 1 && $zone !== null) {
            $deliveryCost = (float)($zone['surcharge'] ?? 0);
        }

        return [
            'volumetric_weight' => round($volumetricWeight, 2),
            'chargeable_weight' => round($chargeableWeight, 2),
            'flete_cost'        => round($fleteCost, 2),
            'delivery_cost'     => round($deliveryCost, 2),
            'total_amount'      => round($fleteCost + $deliveryCost, 2),
        ];
    }

    /**
     * Calculate shipping cost based on configured rules.
     *
     * @param array $packages [{weight, length, width, height, quantity}]
     * @param int|null $zoneId
     * @param string $deliveryType  'door'|'branch'|'aereo'|'maritimo'|'express'
     * @param float|null $declaredValue
     * @param string|null $type  'weight'|'volume'|'distance'|'zone'|'fixed'|'percentage_value'
     * @return array {cost, rule_id, rule_name, breakdown}
     */
    public function calculate(array $packages, ?int $zoneId, string $deliveryType, ?float $declaredValue, ?string $type = null): array
    {
        $typeId = null;
        if ($type !== null) {
            $stmt = $this->db->prepare('SELECT id FROM pricing_rule_type WHERE name = :name LIMIT 1');
            $stmt->execute(['name' => $type]);
            $typeRow = $stmt->fetch();
            if ($typeRow) {
                $typeId = (int) $typeRow['id'];
            }
        }

        $rule = $this->ruleModel->findBestMatch($zoneId, $deliveryType, $typeId);

        if ($rule === null) {
            return [
                'cost' => 0,
                'rule_id' => null,
                'rule_name' => null,
                'breakdown' => [],
                'error' => 'No hay regla de pricing configurada para este tipo de envío',
            ];
        }

        $breakdown = [
            'base' => (float) $rule['base_price'],
            'weight' => 0,
            'volume' => 0,
            'value_percentage' => 0,
            'zone_surcharge' => 0,
            'min_applied' => false,
        ];

        $totalWeight = 0;
        $totalVolume = 0;

        foreach ($packages as $pkg) {
            $weight = (float) ($pkg['weight'] ?? 0);
            $length = (float) ($pkg['length'] ?? 0);
            $width = (float) ($pkg['width'] ?? 0);
            $height = (float) ($pkg['height'] ?? 0);
            $quantity = max(1, (int) ($pkg['quantity'] ?? 1));

            $divisor = max(1, (int)($rule['volumetric_divisor'] ?? 166));
            $volumetricWeight = ($length * $width * $height) / $divisor;
            $billableWeight = max($weight, $volumetricWeight);

            $totalWeight += $billableWeight * $quantity;
            $totalVolume += $volumetricWeight * $quantity;
        }

        $cost = (float) $rule['base_price'];

        if ($rule['price_per_kg'] !== null && $totalWeight > 0) {
            $weightCost = $totalWeight * (float) $rule['price_per_kg'];
            $breakdown['weight'] = round($weightCost, 2);
            $cost += $weightCost;
        }

        if ($rule['price_per_volume'] !== null && $totalVolume > 0) {
            $volumeCost = $totalVolume * (float) $rule['price_per_volume'];
            $breakdown['volume'] = round($volumeCost, 2);
            $cost += $volumeCost;
        }

        if ($rule['percentage_of_value'] > 0 && $declaredValue !== null && $declaredValue > 0) {
            $valueCost = $declaredValue * ((float) $rule['percentage_of_value'] / 100);
            $breakdown['value_percentage'] = round($valueCost, 2);
            $cost += $valueCost;
        }

        if ($zoneId !== null) {
            $stmt = $this->db->prepare('SELECT surcharge FROM zone WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $zoneId]);
            $zoneRow = $stmt->fetch();
            if ($zoneRow && (float) $zoneRow['surcharge'] > 0) {
                $surcharge = (float) $zoneRow['surcharge'];
                $breakdown['zone_surcharge'] = $surcharge;
                $cost += $surcharge;
            }
        }

        $minPrice = (float) ($rule['min_price'] ?? 0);
        if ($minPrice > 0 && $cost < $minPrice) {
            $breakdown['min_applied'] = true;
            $cost = $minPrice;
        }

        $cost = round($cost, 2);

        return [
            'cost' => $cost,
            'rule_id' => (int) $rule['id'],
            'rule_name' => $rule['name'],
            'breakdown' => $breakdown,
        ];
    }
}
