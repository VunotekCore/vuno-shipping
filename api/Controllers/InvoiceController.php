<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\InvoiceModel;
use App\Models\CompanyModel;
use App\Models\BillingConfigModel;
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

        $summary = [
            'pending_total' => $this->model->sumByStatus('pending'),
            'paid_today'    => $this->model->sumPaidToday(),
        ];

        jsonSuccess([
            'invoices'  => $invoices,
            'summary'   => $summary,
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

        jsonSuccess(['invoice' => $invoice]);
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

        $db = Database::getConnection();
        $company = (new CompanyModel($db))->get();
        $config = (new BillingConfigModel($db))->get();

        $statusLabel = ['pending' => 'PENDIENTE', 'paid' => 'PAGADA', 'cancelled' => 'ANULADA'][$invoice['status']] ?? strtoupper($invoice['status']);
        $statusColor = ['pending' => '#f59e0b', 'paid' => '#22c55e', 'cancelled' => '#ef4444'][$invoice['status']] ?? '#64748b';
        $taxEnabled = (int) ($config['tax_enabled'] ?? 0) === 1;

        $companyName = $company['name'] ?? 'Vuno Web Shipping';
        $companyRuc  = $company['ruc'] ?? '';
        $companyAddr = $company['address'] ?? '';
        $companyTel  = $company['phone'] ?? '';
        $companyEmail = $company['email'] ?? '';

        $clientName = $invoice['client_name'] ?? $invoice['client_name_ref'] ?? 'Cliente';
        $clientRuc  = $invoice['client_ruc'] ?? '';

        $trackingNumber = htmlspecialchars($invoice['tracking_number'] ?? '', ENT_QUOTES, 'UTF-8');
        $invoiceNumber  = htmlspecialchars($invoice['invoice_number'] ?? '', ENT_QUOTES, 'UTF-8');

        $base   = number_format((float) $invoice['base_amount'], 2);
        $tax    = number_format((float) $invoice['tax_amount'], 2);
        $total  = number_format((float) $invoice['total_amount'], 2);
        $createdAt = date('d/m/Y H:i', strtotime($invoice['created_at'] ?? 'now'));

        $paidRow = '';
        if ($invoice['status'] === 'paid') {
            $paidAmount = number_format((float) $invoice['paid_amount'], 2);
            $paidCur    = $invoice['paid_currency'] === 'CORD' ? 'C$' : '$';
            $paidRate   = $invoice['exchange_rate'] ? ' · Tasa ' . number_format((float) $invoice['exchange_rate'], 2) : '';
            $paidRow    = "<tr><td style='text-align:right;color:#64748b;padding-top:8px;font-size:13px;'>Pagado: <strong style='color:#22c55e;'>$paidCur$paidAmount</strong>$paidRate</td></tr>";
        }

        $taxLine = '';
        if ($taxEnabled) {
            $taxRatePct = number_format((float) ($invoice['tax_rate'] ?? 0), 0);
            $taxLine = "<div class='row'>Subtotal: \${$base}</div><div class='row'>IVA {$taxRatePct}%: \${$tax}</div>";
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Factura {$invoiceNumber}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f1f5f9; font-family: Arial, Helvetica, sans-serif; padding: 24px; }
  .inv { width: 620px; background: #fff; border: 1px solid #e2e8f0; padding: 32px; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 16px; }
  .brand h1 { font-size: 20px; font-weight: bold; color: #0f172a; text-transform: uppercase; }
  .brand p { font-size: 12px; color: #64748b; margin-top: 2px; }
  .docno { text-align: right; }
  .docno .number { font-size: 24px; font-weight: bold; color: #0f172a; letter-spacing: 1px; }
  .docno .stamp { display: inline-block; margin-top: 6px; padding: 4px 12px; border-radius: 999px; color: #fff; font-size: 11px; font-weight: bold; background: {$statusColor}; }
  .section { display: flex; justify-content: space-between; padding: 20px 0 8px; }
  .block .label { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
  .block .value { font-size: 14px; color: #0f172a; margin-top: 4px; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 20px; }
  table.items th { text-align: left; font-size: 11px; text-transform: uppercase; color: #94a3b8; padding: 8px; border-bottom: 1px solid #e2e8f0; letter-spacing: 0.5px; }
  table.items td { padding: 12px 8px; font-size: 13px; border-bottom: 1px solid #f1f5f9; color: #0f172a; }
  .taxline { margin-top: 16px; text-align: right; font-size: 13px; color: #475569; }
  .taxline .row { padding: 4px 0; }
  .grand { display: flex; justify-content: flex-end; margin-top: 12px; border-top: 2px solid #0f172a; padding-top: 12px; }
  .grand .label { font-size: 13px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; }
  .grand .amount { font-size: 24px; font-weight: bold; color: #0f172a; margin-left: 16px; }
  .footer { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 12px; text-align: center; font-size: 11px; color: #94a3b8; }
  @media print { body { background: #fff; padding: 0; } .inv { border: none; } }
</style>
</head>
<body>
<div class="inv">
  <div class="header">
    <div class="brand">
      <h1>{$companyName}</h1>
      <p>{$companyRuc}</p>
      <p>{$companyAddr}</p>
      <p>{$companyTel} &nbsp; {$companyEmail}</p>
    </div>
    <div class="docno">
      <div class="number">{$invoiceNumber}</div>
      <span class="stamp">{$statusLabel}</span>
      <p style="font-size:12px;color:#64748b;margin-top:6px;">Envío de paquetes</p>
    </div>
  </div>

  <div class="section">
    <div class="block">
      <div class="label">Facturar a</div>
      <div class="value">{$clientName}</div>
      <div class="value" style="font-size:12px;color:#64748b;">{$clientRuc}</div>
    </div>
    <div class="block" style="text-align:right;">
      <div class="label">Paquete</div>
      <div class="value">{$trackingNumber}</div>
    </div>
  </div>

  <table class="items">
    <thead><tr><th>Concepto</th><th style="text-align:right;">Monto</th></tr></thead>
    <tbody>
      <tr><td>Servicio de envío — paquete {$trackingNumber}</td><td style="text-align:right;">\${$base}</td></tr>
    </tbody>
  </table>

  <div class="taxline">
    {$taxLine}
    {$paidRow}
  </div>

  <div class="grand">
    <div class="label">Total</div>
    <div class="amount">\${$total}</div>
  </div>

  <div class="footer">{$companyName} — Factura generada el {$createdAt}</div>
</div>
</body>
</html>
HTML;
    }
}