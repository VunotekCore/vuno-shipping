<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PackageModel;
use App\Models\StorageLocationModel;
use App\Models\WarehouseModel;
use App\Services\PackageStorageService;
use App\Config\Database;

class PackageStorageController
{
    private PackageModel $model;
    private PackageStorageService $service;

    public function __construct()
    {
        $db = Database::getConnection();
        $this->model = new PackageModel($db);
        $this->service = new PackageStorageService($db);
    }

    public function get(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();
        if (!hasPermission($payload, 'package', 'read')) {
            jsonError('No tenés permiso', 403);
        }

        $packageId = (int) ($_GET['package_id'] ?? $_GET['id'] ?? 0);
        if ($packageId <= 0) {
            jsonError('package_id es requerido', 422);
        }

        $package = $this->model->findById($packageId);
        if (!$package) {
            jsonError('Paquete no encontrado', 404);
        }

        jsonSuccess([
            'package' => [
                'id' => (int) $package['id'],
                'tracking_number' => $package['tracking_number'],
                'origin' => $package['origin'],
                'storage_category_id' => $package['storage_category_id'] !== null ? (int) $package['storage_category_id'] : null,
                'storage_category_name' => $package['storage_category_name'] ?? null,
                'storage_category_color' => $package['storage_category_color'] ?? null,
                'storage_location_id' => $package['storage_location_id'] !== null ? (int) $package['storage_location_id'] : null,
                'storage_location_code' => $package['storage_location_code'] ?? null,
                'storage_warehouse_id' => $package['storage_warehouse_id'] !== null ? (int) $package['storage_warehouse_id'] : null,
                'stored_at' => $package['stored_at'],
            ],
            'history' => $this->service->history($packageId),
        ]);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('package_storage_update', 30, 900);
        if (!hasPermission($payload, 'storage_location', 'edit')) {
            jsonError('No tenés permiso', 403);
        }

        $data = getJsonInput();
        $packageId = (int) ($data['package_id'] ?? $data['id'] ?? 0);
        if ($packageId <= 0) {
            jsonError('package_id es requerido', 422);
        }

        $package = $this->model->findById($packageId);
        if (!$package) {
            jsonError('Paquete no encontrado', 404);
        }

        $userId = (int) $payload['sub'];
        $notes = isset($data['notes']) ? (sanitizeString((string) $data['notes'], 255) ?: null) : null;

        $categoryId = array_key_exists('storage_category_id', $data)
            ? ($data['storage_category_id'] !== '' && $data['storage_category_id'] !== null ? (int) $data['storage_category_id'] : null)
            : null;

        if ($categoryId !== null) {
            $this->model->update($packageId, ['storage_category_id' => $categoryId]);
        }

        $locationId = array_key_exists('storage_location_id', $data)
            ? ($data['storage_location_id'] !== '' && $data['storage_location_id'] !== null ? (int) $data['storage_location_id'] : null)
            : null;

        $auto = !empty($data['auto']);

        if ($locationId !== null) {
            $location = (new StorageLocationModel(Database::getConnection()))->findById($locationId);
            if (!$location) {
                jsonError('Ubicación no encontrada', 404);
            }
            $this->service->move($packageId, $locationId, $userId, $notes ?? 'Reubicación manual');
        } elseif (!empty($data['release'])) {
            $this->service->free($packageId, $userId, 'released', $notes);
        } else {
            $warehouseId = array_key_exists('warehouse_id', $data) && $data['warehouse_id'] !== ''
                ? (int) $data['warehouse_id']
                : null;
            if ($warehouseId === null) {
                $warehouse = (new WarehouseModel(Database::getConnection()))->findByOrigin((string) $package['origin']);
                $location = $this->service->autoSuggest((int) ($warehouse['id'] ?? 0), $categoryId ?? (int) $package['storage_category_id']);
                $locationId = $location ? (int) $location['id'] : null;
            }

            if ($auto || $locationId !== null || $categoryId !== null) {
                $this->service->assign($packageId, (string) $package['origin'], $categoryId, $locationId, $userId, 'moved', $notes);
            }
        }

        jsonSuccess([
            'package' => $this->model->findById($packageId),
            'history' => $this->service->history($packageId),
        ], 'Almacenaje actualizado');
    }
}
