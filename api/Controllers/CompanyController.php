<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\CompanyModel;
use App\Config\Database;

class CompanyController
{
    private CompanyModel $model;

    public function __construct()
    {
        $this->model = new CompanyModel(Database::getConnection());
    }

    public function get(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'company', 'read')) {
            jsonError('No tenés permiso para ver la empresa', 403);
        }

        $company = $this->model->get();

        if ($company === null) {
            $this->model->create(['name' => 'Mi Empresa']);
            $company = $this->model->get();
        }

        jsonSuccess(['company' => $company]);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('company_update', 10, 900);

        if (!hasPermission($payload, 'company', 'edit')) {
            jsonError('No tenés permiso para editar la empresa', 403);
        }

        $data = getJsonInput();
        $company = $this->model->get();

        if ($company === null) {
            $sanitized = $this->sanitizeData($data);
            if (!isset($sanitized['name']) || $sanitized['name'] === '') {
                jsonError('El nombre de la empresa es requerido', 422);
            }
            $this->model->create($sanitized);
        } else {
            $updateData = $this->sanitizeData($data);

            if (!empty($updateData)) {
                $this->model->update($company['id'], $updateData);
            }
        }

        $updated = $this->model->get();
        jsonSuccess(['company' => $updated], 'Empresa actualizada');
    }

    private function sanitizeData(array $data): array
    {
        $result = [];

        if (isset($data['name'])) {
            $result['name'] = sanitizeString($data['name'], 150);
        }
        if (isset($data['logo'])) {
            $result['logo'] = sanitizeString($data['logo'], 255);
        }
        // array_key_exists (y no isset) para que `ruc: null` explícito también borre
        // el campo: isset() ignoraría el null y dejaría el valor anterior.
        if (array_key_exists('ruc', $data)) {
            $result['ruc'] = $data['ruc'] !== null ? validateRuc((string) $data['ruc']) : null;
        }
        if (isset($data['address'])) {
            $result['address'] = trim($data['address']);
        }
        if (isset($data['phone'])) {
            $result['phone'] = sanitizeString($data['phone'], 30);
        }
        if (isset($data['email'])) {
            $result['email'] = validateEmail($data['email']);
        }
        if (isset($data['website'])) {
            $result['website'] = sanitizeString($data['website'], 255);
        }

        return $result;
    }
}
