<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\SubzoneModel;
use App\Config\Database;

class SubzoneController
{
    private SubzoneModel $model;

    public function __construct()
    {
        $this->model = new SubzoneModel(Database::getConnection());
    }

    public function listByZone(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'subzone', 'read')) {
            jsonError('No tenés permiso para ver municipalidades', 403);
        }

        $zoneId = max(1, (int) ($_GET['zone_id'] ?? 0));
        if ($zoneId <= 0) {
            jsonError('zone_id requerido', 422);
        }

        $subzones = $this->model->listByZone($zoneId);
        jsonSuccess(['subzones' => $subzones]);
    }
}