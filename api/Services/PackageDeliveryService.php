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

        try {
            (new PackageStorageService($this->db))->free($packageId, $createdBy, 'released', 'Entregado al cliente');
        } catch (\Throwable $e) {
            apiLog('ERROR', 'Error liberando almacen del paquete ' . $packageId . ': ' . $e->getMessage());
        }

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
        $notifier = new PackageNotifierService($this->db);
        $notifier->notifyStatusChange($package, $oldStatus, 'delivered', $description);
    }
}
