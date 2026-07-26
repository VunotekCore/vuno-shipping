<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class DocumentSequenceModel
{
    public function __construct(private PDO $db) {}

    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM document_sequence')->fetchColumn();
    }

    public function listPaginated(int $limit, int $offset): array
    {
        $stmt = $this->db->prepare("
            SELECT id, document_code, description, prefix, current_number, created_at, updated_at
            FROM document_sequence
            ORDER BY document_code
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, document_code, description, prefix, current_number, created_at, updated_at FROM document_sequence WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, document_code, description, prefix, current_number FROM document_sequence WHERE document_code = :code LIMIT 1'
        );
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $documentCode, ?string $description, ?string $prefix, int $currentNumber = 1): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO document_sequence (document_code, description, prefix, current_number) VALUES (:document_code, :description, :prefix, :current_number)'
        );
        $stmt->execute([
            'document_code' => $documentCode,
            'description' => $description,
            'prefix' => $prefix,
            'current_number' => $currentNumber,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach (['document_code', 'description', 'prefix', 'current_number'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = 'UPDATE document_sequence SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM document_sequence WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function nextNumber(string $documentCode): string
    {
        $stmt = $this->db->prepare(
            'SELECT id, prefix, current_number FROM document_sequence WHERE document_code = :code LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['code' => $documentCode]);
        $seq = $stmt->fetch();

        if ($seq === null) {
            jsonError("Secuencia no encontrada: {$documentCode}", 404);
        }

        $newNumber = (int) $seq['current_number'] + 1;
        $width = max(6, strlen((string) $newNumber));

        $stmt = $this->db->prepare(
            'UPDATE document_sequence SET current_number = :num WHERE id = :id'
        );
        $stmt->execute(['num' => $newNumber, 'id' => $seq['id']]);

        $prefix = $seq['prefix'] ?? '';
        $padded = str_pad((string) $newNumber, $width, '0', STR_PAD_LEFT);

        return $prefix !== '' ? "{$prefix}-{$padded}" : $padded;
    }
}
