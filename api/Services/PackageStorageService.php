<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\PackageModel;
use App\Models\PackageStorageMovementModel;
use App\Models\StorageCategoryModel;
use App\Models\StorageLocationModel;
use App\Models\WarehouseModel;
use PDO;

/**
 * Gestiona la ubicacion fisica de los paquetes en bodega y su historial de
 * movimientos. Toda la logica es opcional/defensiva: si no hay bodega o
 * ubicacion configurada, los metodos no hacen nada (no rompen el flujo).
 *
 * Ciclo de vida:
 *  - stored    : se asigna ubicacion (admisión o llegada a Nicaragua)
 *  - moved     : cambio manual de ubicacion
 *  - released  : el paquete sale de bodega (se asigna a un despacho)
 *  - restored  : el paquete vuelve a bodega (skip/failed)
 */
class PackageStorageService
{
    public function __construct(private PDO $db) {}

    public function resolveWarehouseId(string $origin): ?int
    {
        $warehouse = (new WarehouseModel($this->db))->findByOrigin($origin);
        return $warehouse ? (int) $warehouse['id'] : null;
    }

    /**
     * Elige la ubicacion mas adecuada (clasificacion + menor ocupacion) con
     * capacidad disponible. Si ninguna tiene capacidad, devuelve la menos
     * ocupada igualmente. Devuelve null si no hay ubicaciones.
     */
    public function autoSuggest(int $warehouseId, ?int $categoryId): ?array
    {
        $locations = (new StorageLocationModel($this->db))->findByWarehouse($warehouseId, $categoryId);
        if (empty($locations)) {
            return null;
        }

        foreach ($locations as $loc) {
            if ((int) ($loc['occupied'] ?? 0) < (int) ($loc['capacity'] ?? 0)) {
                return $loc;
            }
        }

        return $locations[0];
    }

    /**
     * Asigna una ubicacion a un paquete y registra el movimiento.
     * Si $locationId es null, se auto-sugiere segun la clasificacion.
     */
    public function assign(
        int $packageId,
        string $origin,
        ?int $categoryId,
        ?int $locationId,
        ?int $userId,
        string $action = 'stored',
        ?string $notes = null
    ): ?int {
        $packageModel = new PackageModel($this->db);
        $package = $packageModel->findById($packageId);
        if ($package === null) {
            return null;
        }

        if ($categoryId === null && isset($package['storage_category_id'])) {
            $categoryId = (int) $package['storage_category_id'];
        }
        if ($categoryId === null) {
            $normal = (new StorageCategoryModel($this->db))->findByName('Normal');
            $categoryId = $normal ? (int) $normal['id'] : null;
        }

        $locationModel = new StorageLocationModel($this->db);
        $location = null;
        if ($locationId !== null) {
            $location = $locationModel->findById($locationId);
        }

        $warehouseId = null;
        if ($location !== null) {
            $warehouseId = (int) $location['warehouse_id'];
        } else {
            $warehouseId = $this->resolveWarehouseId($origin);
            if ($warehouseId !== null) {
                $location = $this->autoSuggest($warehouseId, $categoryId);
            }
        }

        if ($location === null) {
            return null;
        }

        $locationId = (int) $location['id'];
        $warehouseId = (int) $location['warehouse_id'];

        $update = [
            'storage_location_id' => $locationId,
            'stored_at' => date('Y-m-d H:i:s'),
        ];
        if ($categoryId !== null) {
            $update['storage_category_id'] = $categoryId;
        }
        $packageModel->update($packageId, $update);

        (new PackageStorageMovementModel($this->db))->create([
            'package_id' => $packageId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'category_id' => $categoryId,
            'action' => $action,
            'notes' => $notes,
            'created_by' => $userId,
        ]);

        return $locationId;
    }

    /**
     * Libera la ubicacion del paquete (deja la bodega). Registra el movimiento.
     * Devuelve true si el paquete tenia ubicacion.
     */
    public function free(int $packageId, ?int $userId, string $action = 'released', ?string $notes = null): bool
    {
        $packageModel = new PackageModel($this->db);
        $package = $packageModel->findById($packageId);
        if ($package === null || empty($package['storage_location_id'])) {
            return false;
        }

        $locationModel = new StorageLocationModel($this->db);
        $location = $locationModel->findById((int) $package['storage_location_id']);

        (new PackageStorageMovementModel($this->db))->create([
            'package_id' => $packageId,
            'warehouse_id' => $location ? (int) $location['warehouse_id'] : null,
            'location_id' => (int) $package['storage_location_id'],
            'category_id' => isset($package['storage_category_id']) ? (int) $package['storage_category_id'] : null,
            'action' => $action,
            'notes' => $notes,
            'created_by' => $userId,
        ]);

        $packageModel->update($packageId, ['storage_location_id' => null, 'stored_at' => null]);
        return true;
    }

    /**
     * Vuelve a ubicar el paquete (retorno a bodega tras skip/failed). Asigna en
     * la bodega de Nicaragua (fallback: la bodega del origen del paquete).
     */
    public function restore(int $packageId, ?int $userId, ?string $notes = null): ?int
    {
        $packageModel = new PackageModel($this->db);
        $package = $packageModel->findById($packageId);
        if ($package === null) {
            return null;
        }

        $warehouse = (new WarehouseModel($this->db))->findByOrigin('local');
        $categoryId = isset($package['storage_category_id']) ? (int) $package['storage_category_id'] : null;

        if ($warehouse === null) {
            return $this->assign($packageId, (string) ($package['origin'] ?? 'local'), $categoryId, null, $userId, 'restored', $notes);
        }

        $location = $this->autoSuggest((int) $warehouse['id'], $categoryId);
        if ($location === null) {
            return $this->assign($packageId, 'local', $categoryId, null, $userId, 'restored', $notes);
        }

        return $this->assign($packageId, 'local', $categoryId, (int) $location['id'], $userId, 'restored', $notes);
    }

    /**
     * Mueve manualmente un paquete a otra ubicacion.
     */
    public function move(int $packageId, int $locationId, ?int $userId, ?string $notes = null): ?int
    {
        $locationModel = new StorageLocationModel($this->db);
        $location = $locationModel->findById($locationId);
        if ($location === null) {
            return null;
        }

        return $this->assign($packageId, (string) ($location['warehouse_code'] ?? 'online'), null, $locationId, $userId, 'moved', $notes);
    }

    public function history(int $packageId): array
    {
        return (new PackageStorageMovementModel($this->db))->listByPackage($packageId);
    }
}
