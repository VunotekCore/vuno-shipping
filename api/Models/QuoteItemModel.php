<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class QuoteItemModel
{
    public function __construct(private PDO $db) {}

    public function listByQuote(int $quoteId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM quote_item WHERE quote_id = :q ORDER BY position ASC, id ASC'
        );
        $stmt->execute(['q' => $quoteId]);
        return $stmt->fetchAll();
    }

    public function deleteByQuote(int $quoteId): void
    {
        $stmt = $this->db->prepare('DELETE FROM quote_item WHERE quote_id = :q');
        $stmt->execute(['q' => $quoteId]);
    }

    /**
     * Inserta los ítems de una cotización. Cada elemento debe traer ya los
     * costos calculados (flete_cost/delivery_cost/total_amount).
     *
     * @param array<int, array<string, mixed>> $items
     */
    public function insertMany(int $quoteId, array $items): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO quote_item
                (quote_id, position, description, quantity, weight, length, width, height,
                 declared_value, volumetric_weight, chargeable_weight,
                 flete_cost, delivery_cost, total_amount)
             VALUES
                (:quote_id, :position, :description, :quantity, :weight, :length, :width, :height,
                 :declared_value, :volumetric_weight, :chargeable_weight,
                 :flete_cost, :delivery_cost, :total_amount)'
        );

        $position = 0;
        foreach ($items as $item) {
            $stmt->execute([
                'quote_id' => $quoteId,
                'position' => $position++,
                'description' => $item['description'] ?? null,
                'quantity' => (int) ($item['quantity'] ?? 1),
                'weight' => $item['weight'] ?? null,
                'length' => $item['length'] ?? null,
                'width' => $item['width'] ?? null,
                'height' => $item['height'] ?? null,
                'declared_value' => $item['declared_value'] ?? null,
                'volumetric_weight' => $item['volumetric_weight'] ?? 0,
                'chargeable_weight' => $item['chargeable_weight'] ?? 0,
                'flete_cost' => $item['flete_cost'] ?? 0,
                'delivery_cost' => $item['delivery_cost'] ?? 0,
                'total_amount' => $item['total_amount'] ?? 0,
            ]);
        }
    }
}
