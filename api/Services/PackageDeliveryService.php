<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\PackageModel;
use App\Models\TrackingEventModel;
use PDO;

class PackageDeliveryService
{
    public function __construct(private PDO $db) {}

    /**
     * Marca un paquete como entregado y crea su tracking_event.
     * Debe ejecutarse dentro de una transaccion ya iniciada por el caller.
     *
     * @param array $event [description, latitude, longitude, photo_url]
     * @return array{package: ?array, old_status: string, already_delivered: bool}
     */
    public function markDelivered(int $packageId, int $createdBy, array $event): array
    {
        $packageModel = new PackageModel($this->db);

        $existing = $packageModel->findById($packageId);
        if ($existing === null) {
            throw new \RuntimeException("Package {$packageId} not found");
        }

        $oldStatus = (string) ($existing['status_name'] ?? '');
        if ($oldStatus === 'delivered') {
            return ['package' => $existing, 'old_status' => $oldStatus, 'already_delivered' => true];
        }

        $deliveredStatusId = $packageModel->getStatusId('delivered');
        if ($deliveredStatusId === null) {
            throw new \RuntimeException('Package status delivered not found');
        }

        $packageModel->update($packageId, [
            'status_id' => $deliveredStatusId,
            'delivered_at' => date('Y-m-d H:i:s'),
        ]);

        $trackingModel = new TrackingEventModel($this->db);
        $trackingModel->create([
            'package_id' => $packageId,
            'status' => 'delivered',
            'description' => $event['description'] ?? null,
            'latitude' => $event['latitude'] ?? null,
            'longitude' => $event['longitude'] ?? null,
            'location_name' => $event['location_name'] ?? null,
            'photo_url' => $event['photo_url'] ?? null,
            'recorded_at' => date('Y-m-d H:i:s'),
            'created_by' => $createdBy,
        ]);

        return [
            'package' => $packageModel->findById($packageId),
            'old_status' => $oldStatus,
            'already_delivered' => false,
        ];
    }

    /**
     * Notificaciones post-commit (in-app + email). Se ejecuta fuera de la
     * transaccion para no fallar el commit si el email falla.
     */
    public function notifyDelivered(array $package, string $oldStatus, ?string $description): void
    {
        try {
            $notifSvc = new NotificationService($this->db);
            $notifSvc->create(
                (int) $package['id'],
                'status_change',
                "Paquete {$package['tracking_number']}: Entregado",
                $description !== null ? "Entregado — {$description}" : 'Entregado'
            );

            $clientEmail = $package['client_email'] ?? null;
            if ($clientEmail) {
                $baseUrl = (config()['app']['base_url'] ?? 'http://localhost:4321');
                $trackingUrl = rtrim($baseUrl, '/') . '/rastreo?tracking=' . rawurlencode($package['tracking_number']);
                if (!empty($package['tracking_token'])) {
                    $trackingUrl .= '&token=' . rawurlencode($package['tracking_token']);
                }

                $mailSvc = new MailService();
                $mailSvc->sendStatusChange(
                    $clientEmail,
                    $package['client_name'] ?? 'Cliente',
                    $package['tracking_number'],
                    $trackingUrl,
                    $oldStatus,
                    'delivered',
                    $description
                );
            }
        } catch (\Throwable $e) {
            apiLog('ERROR', 'Post-delivery notification failed: ' . $e->getMessage());
        }
    }
}
