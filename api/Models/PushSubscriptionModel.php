<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

class PushSubscriptionModel
{
    public function __construct(private PDO $db) {}

    public function upsert(int $userId, string $endpoint, string $p256dh, string $auth, ?string $userAgent = null): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO push_subscription (user_id, endpoint, p256dh, auth, user_agent)
            VALUES (:user_id, :endpoint, :p256dh, :auth, :user_agent)
            ON DUPLICATE KEY UPDATE
             user_id = VALUES(user_id),
             p256dh = VALUES(p256dh),
             auth = VALUES(auth),
             user_agent = VALUES(user_agent)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, user_id, endpoint, p256dh, auth, user_agent, created_at, updated_at
            FROM push_subscription
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function deleteByEndpoint(int $userId, string $endpoint): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM push_subscription
            WHERE user_id = :user_id AND endpoint = :endpoint
        ");
        $stmt->execute(['user_id' => $userId, 'endpoint' => $endpoint]);
    }

    public function deleteById(int $id): void
    {
        $stmt = $this->db->prepare("DELETE FROM push_subscription WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }
}