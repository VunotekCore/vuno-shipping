<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use App\Models\DocumentSequenceModel;
use App\Models\InvoiceModel;
use App\Models\BillingConfigModel;

class InvoiceService
{
    public function __construct(private PDO $db) {}

    /**
     * Crea la factura de un paquete a partir del costo de envío calculado.
     * Debe llamarse dentro de la misma transacción del paquete.
     *
     * @return int id de la factura creada
     */
    public function createForPackage(int $packageId, array $package, ?array $client, ?array $payload): int
    {
        $seqModel = new DocumentSequenceModel($this->db);
        $configModel = new BillingConfigModel($this->db);
        $config = $configModel->get();

        $baseAmount = (float) ($package['shipping_cost'] ?? 0);
        $taxEnabled = (int) ($config['tax_enabled'] ?? 0) === 1;
        $taxRate = (float) ($config['tax_rate'] ?? 0);

        $taxAmount = $taxEnabled ? round($baseAmount * ($taxRate / 100), 2) : 0.00;
        $totalAmount = round($baseAmount + $taxAmount, 2);

        $clientName = $client['name'] ?? $package['recipient_name'] ?? null;
        $clientRuc = ($config['show_client_ruc'] ?? 0) ? ($client['ruc'] ?? null) : null;

        $invoiceNumber = $seqModel->nextNumber('invoice');

        $model = new InvoiceModel($this->db);
        return $model->create([
            'invoice_number' => $invoiceNumber,
            'package_id' => $packageId,
            'client_id' => $client['id'] ?? null,
            'client_name' => $clientName,
            'client_ruc' => $clientRuc,
            'base_amount' => $baseAmount,
            'tax_rate' => $taxEnabled ? $taxRate : 0.00,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'currency' => 'USD',
            'exchange_rate' => $config['exchange_rate_usd'] ?? null,
            'created_by' => $payload['sub'] ?? null,
        ]);
    }
}