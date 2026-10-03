<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\BillingConfigModel;
use App\Config\Database;

class BillingConfigController
{
    private BillingConfigModel $model;

    public function __construct()
    {
        $this->model = new BillingConfigModel(Database::getConnection());
    }

    public function get(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'billing_config', 'read')) {
            jsonError('No tenés permiso para ver la configuración de facturación', 403);
        }

        jsonSuccess(['config' => $this->model->get()]);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('billing_config_update', 20, 900);

        if (!hasPermission($payload, 'billing_config', 'edit')) {
            jsonError('No tenés permiso para editar la configuración de facturación', 403);
        }

        $data = getJsonInput();

        $update = [];
        if (array_key_exists('tax_enabled', $data)) {
            $update['tax_enabled'] = (int) (bool) $data['tax_enabled'];
        }
        if (array_key_exists('tax_rate', $data)) {
            $rate = (float) $data['tax_rate'];
            $update['tax_rate'] = max(0, min(100, $rate));
        }
        if (array_key_exists('show_client_ruc', $data)) {
            $update['show_client_ruc'] = (int) (bool) $data['show_client_ruc'];
        }
        if (array_key_exists('exchange_rate_usd', $data)) {
            $rate = (float) $data['exchange_rate_usd'];
            $update['exchange_rate_usd'] = max(0.01, $rate);
        }
        if (array_key_exists('invoice_prefix', $data)) {
            $prefix = sanitizeString((string) $data['invoice_prefix'], 10);
            $update['invoice_prefix'] = $prefix === '' ? 'FAC' : $prefix;

            $db = Database::getConnection();
            $stmt = $db->prepare(
                "UPDATE document_sequence SET prefix = :prefix WHERE document_code = 'invoice'"
            );
            $stmt->execute(['prefix' => $update['invoice_prefix']]);
        }

        if (empty($update)) {
            jsonError('No hay campos para actualizar', 422);
        }

        $this->model->update($update);
        jsonSuccess(['config' => $this->model->get()], 'Configuración de facturación actualizada');
    }
}