<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\BillingConfigModel;
use App\Models\DocumentSequenceModel;
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

        jsonSuccess([
            'config'           => $this->model->get(),
            'invoice_sequence' => $this->getInvoiceSequence(),
        ]);
    }

    /**
     * Secuencia que numeriza las facturas. `document_sequence` es la única
     * fuente de verdad del prefijo; se expone aquí (en vez de pedir
     * document_sequence.read) para poder mostrarlo en la configuración.
     */
    private function getInvoiceSequence(): array
    {
        $seq = (new DocumentSequenceModel(Database::getConnection()))->findByCode('invoice');

        if ($seq === null) {
            return ['exists' => false, 'prefix' => '', 'current_number' => 0, 'next_preview' => ''];
        }

        $prefix = (string) ($seq['prefix'] ?? '');
        $next = ((int) $seq['current_number']) + 1;
        $width = max(6, strlen((string) $next));
        $preview = $prefix !== '' ? $prefix . '-' . str_pad((string) $next, $width, '0', STR_PAD_LEFT) : str_pad((string) $next, $width, '0', STR_PAD_LEFT);

        return [
            'exists'         => true,
            'prefix'         => $prefix,
            'current_number' => (int) $seq['current_number'],
            'next_preview'   => $preview,
        ];
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

        if (empty($update)) {
            jsonError('No hay campos para actualizar', 422);
        }

        $this->model->update($update);
        jsonSuccess([
            'config'           => $this->model->get(),
            'invoice_sequence' => $this->getInvoiceSequence(),
        ], 'Configuración de facturación actualizada');
    }
}