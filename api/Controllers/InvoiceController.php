<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\InvoiceModel;
use App\Models\CompanyModel;
use App\Models\BillingConfigModel;
use App\Services\InvoicePrintService;
use App\Config\Database;

class InvoiceController
{
    private InvoiceModel $model;

    public function __construct()
    {
        $this->model = new InvoiceModel(Database::getConnection());
    }

    public function list(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'invoice', 'read')) {
            jsonError('No tenés permiso para ver facturas', 403);
        }

        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $limit   = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset  = ($page - 1) * $limit;

        $filters = [
            'status'    => sanitizeString($_GET['status'] ?? '', 20),
            'client_id' => (int) ($_GET['client_id'] ?? 0),
            'from'      => sanitizeString($_GET['from'] ?? '', 10),
            'to'        => sanitizeString($_GET['to'] ?? '', 10),
        ];
        $filters = array_filter($filters, fn($v) => $v !== '' && $v !== 0);

        $total    = $this->model->count($filters);
        $invoices = $this->model->listPaginated($limit, $offset, $filters);

        $config  = (new BillingConfigModel(Database::getConnection()))->get();
        $paidDay = $this->model->sumPaidTodayByCurrency();

        $summary = [
            // `total_amount` siempre esta en USD (base de cobro del paquete)
            'pending_total'   => $this->model->sumByStatus('pending'),
            'paid_today_usd'  => $paidDay['USD'],
            'paid_today_cord' => $paidDay['CORD'],
        ];

        jsonSuccess([
            'invoices'  => $invoices,
            'summary'   => $summary,
            // Tasa vigente: la que aplicara pay() si se cobra en cordonbas
            'exchange_rate_usd' => (float) ($config['exchange_rate_usd'] ?? 36.00),
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    public function get(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'invoice', 'read')) {
            jsonError('No tenés permiso para ver facturas', 403);
        }

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID de factura inválido', 422);
        }

        $invoice = $this->model->findById($id);
        if ($invoice === null) {
            jsonError('Factura no encontrada', 404);
        }

        // Tasa vigente desde billing_config. Se expone aqui (y no en el
        // frontend via billing_config/get.php) porque ese endpoint exige el
        // permiso `billing_config.read`, que solo tiene el rol admin: un
        // facturador con `invoice.read` recibiria 403 y caeria a una tasa
        // hardcodeada, mostrando un monto en cordonbas incorrecto.
        $config = (new BillingConfigModel(Database::getConnection()))->get();

        jsonSuccess([
            'invoice' => $invoice,
            'exchange_rate_usd' => (float) ($config['exchange_rate_usd'] ?? 36.00),
        ]);
    }

    public function pay(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('invoice_pay', 20, 900);

        if (!hasPermission($payload, 'invoice', 'edit')) {
            jsonError('No tenés permiso para registrar cobros', 403);
        }

        $data  = getJsonInput();
        $id    = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID de factura inválido', 422);
        }

        $invoice = $this->model->findById($id);
        if ($invoice === null) {
            jsonError('Factura no encontrada', 404);
        }
        if ($invoice['status'] !== 'pending') {
            jsonError('Solo se pueden cobrar facturas pendientes', 409);
        }

        $methodId = (int) ($data['payment_method_id'] ?? 0);
        $methodStmt = Database::getConnection()->prepare(
            'SELECT id FROM payment_method WHERE id = :id AND is_active = 1'
        );
        $methodStmt->execute(['id' => $methodId]);
        if ($methodStmt->fetch() === false) {
            jsonError('Método de pago inválido', 422);
        }

        $currency = in_array($data['paid_currency'] ?? '', ['USD', 'CORD'], true)
            ? $data['paid_currency']
            : 'USD';

        $total = (float) $invoice['total_amount'];
        $config = (new BillingConfigModel(Database::getConnection()))->get();
        $rate = (float) ($config['exchange_rate_usd'] ?? 36.00);

        if ($currency === 'CORD') {
            $paidAmount = round($total * $rate, 2);
        } else {
            $paidAmount = $total;
        }

        $this->model->markPaid($id, [
            'paid_amount'       => $paidAmount,
            'paid_currency'     => $currency,
            'payment_method_id' => $methodId,
            'payment_reference' => sanitizeString($data['payment_reference'] ?? '', 100),
            'exchange_rate'     => $currency === 'CORD' ? $rate : null,
            'notes'             => sanitizeString($data['notes'] ?? '', 500),
        ]);

        jsonSuccess(
            ['invoice' => $this->model->findById($id)],
            $currency === 'CORD' ? "Cobro registrado (C\$$paidAmount)" : 'Cobro registrado'
        );
    }

    public function cancel(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('invoice_cancel', 5, 900);

        if (!hasPermission($payload, 'invoice', 'edit')) {
            jsonError('No tenés permiso para anular facturas', 403);
        }

        $data = getJsonInput();
        $id   = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID de factura inválido', 422);
        }

        $invoice = $this->model->findById($id);
        if ($invoice === null) {
            jsonError('Factura no encontrada', 404);
        }
        if ($invoice['status'] !== 'pending') {
            jsonError('Solo se pueden anular facturas pendientes', 409);
        }

        $this->model->cancel($id);
        jsonSuccess(['invoice' => $this->model->findById($id)], 'Factura anulada');
    }

    public function print(): never
    {
        requireMethod('GET');
        $payload = requireAuthMiddleware();

        if (!hasPermission($payload, 'invoice', 'read')) {
            jsonError('No tenés permiso para ver facturas', 403);
        }

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            jsonError('ID de factura inválido', 422);
        }

        $invoice = $this->model->findById($id);
        if ($invoice === null) {
            jsonError('Factura no encontrada', 404);
        }

        // Moneda del documento. En Nicaragua la factura se emite en cordonbas
        // aunque el servicio este dolarizado, por eso CORD es el default y USD
        // queda como impresion de referencia.
        $docCurrency = ($_GET['currency'] ?? 'CORD') === 'USD' ? 'USD' : 'CORD';

        // Formato: carta (por defecto) o bobina de 80 mm para termica.
        $format = InvoicePrintService::sanitizeFormat($_GET['format'] ?? null);

        $db = Database::getConnection();
        $service = new InvoicePrintService();
        $data = $service->buildData(
            $invoice,
            (new CompanyModel($db))->get(),
            (new BillingConfigModel($db))->get(),
            $docCurrency
        );

        header('Content-Type: text/html; charset=UTF-8');
        echo $service->render($data, $format);
        // Imprescindible: el metodo es `never`, asi que sin `exit` PHP lanza
        // "never-returning function must not implicitly return" al llegar al
        // cierre y el handler global anexa un JSON de error tras el HTML.
        exit;
    }
}