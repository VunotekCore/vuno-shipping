<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Notificaciones de cambio de estado de paquete.
 * Centraliza: notificacion in-app (campanita) + email al cliente con URL de rastreo + token.
 * Se ejecuta siempre post-commit y nunca debe romper la operacion principal.
 */
class PackageNotifierService
{
    private const STATUS_LABELS = [
        'pending' => 'Pendiente',
        'received' => 'Recibido en almacén Miami',
        'in_transit' => 'En tránsito a Nicaragua',
        'in_nicaragua' => 'Recibido en Nicaragua',
        'out_for_delivery' => 'En ruta de entrega',
        'delivered' => 'Entregado',
        'returned' => 'Devuelto',
        'cancelled' => 'Cancelado',
        'disputed' => 'En disputa',
    ];

    public function __construct(private PDO $db) {}

    public function notifyStatusChange(
        array $package,
        string $oldStatus,
        string $newStatus,
        ?string $description = null
    ): void {
        try {
            $trackingNumber = (string) ($package['tracking_number'] ?? '');
            $oldLabel = self::STATUS_LABELS[$oldStatus] ?? $oldStatus;
            $newLabel = self::STATUS_LABELS[$newStatus] ?? $newStatus;

            $notifSvc = new NotificationService($this->db);
            $notifSvc->create(
                (int) ($package['id'] ?? 0),
                'status_change',
                "Paquete {$trackingNumber}: {$newLabel}",
                "{$oldLabel} → {$newLabel}"
            );

            $clientEmail = $package['client_email'] ?? null;
            if (!$clientEmail) {
                return;
            }

            $baseUrl = (config()['app']['base_url'] ?? 'http://localhost:4321');
            $trackingUrl = rtrim($baseUrl, '/') . '/rastreo?tracking=' . rawurlencode($trackingNumber);
            if (!empty($package['tracking_token'])) {
                $trackingUrl .= '&token=' . rawurlencode((string) $package['tracking_token']);
            }

            $mailSvc = new MailService();
            $mailSvc->sendStatusChange(
                (string) $clientEmail,
                (string) ($package['client_name'] ?? 'Cliente'),
                $trackingNumber,
                $trackingUrl,
                $oldStatus,
                $newStatus,
                $description
            );
        } catch (\Throwable $e) {
            apiLog('ERROR', 'Package notifier failed: ' . $e->getMessage());
        }
    }
}