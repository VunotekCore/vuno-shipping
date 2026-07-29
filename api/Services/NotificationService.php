<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

class NotificationService
{
    public function __construct(private PDO $db) {}

    public function create(
        int $packageId,
        string $type,
        string $title,
        string $message
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO notification (package_id, type, title, message)
             VALUES (:package_id, :type, :title, :message)'
        );
        $stmt->execute([
            'package_id' => $packageId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function getUnreadCount(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM notification n
             WHERE n.id > COALESCE(
                 (SELECT last_read_notification_id FROM `user` WHERE id = :uid), 0
             )'
        );
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function getList(int $userId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT n.id, n.package_id, n.type, n.title, n.message, n.created_at,
                    p.tracking_number,
                    CASE WHEN n.id > COALESCE(u.last_read_notification_id, 0) THEN 0 ELSE 1 END AS is_read
             FROM notification n
             LEFT JOIN package p ON p.id = n.package_id
             CROSS JOIN `user` u ON u.id = :uid
             ORDER BY n.id DESC
             LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue('uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue('off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function markRead(int $userId, int $notificationId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE `user` SET last_read_notification_id = :nid
             WHERE id = :uid AND last_read_notification_id < :nid2'
        );
        $stmt->execute([
            'nid' => $notificationId,
            'uid' => $userId,
            'nid2' => $notificationId,
        ]);
        return $stmt->rowCount() > 0;
    }
}
