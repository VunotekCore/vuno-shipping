<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Factura en hoja Carta (8.5 x 11 in).
 *
 * Es el comprobante formal del envio: tabla de items con columnas reales,
 * bloque de totales alineado y reglas de cierre visibles. La regla `@page`
 * es indispensable para que el navegador use el tamano de papel correcto.
 */
class InvoiceLetterLayout extends InvoicePrintLayout
{
    public function render(array $d): string
    {
        return $this->document(
            'Factura ' . $d['invoiceNumber'],
            $this->css($d),
            $this->body($d)
        );
    }

    private function css(array $d): string
    {
        return <<<CSS
  @page { size: letter; margin: 14mm; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { background: #e8ecf1; padding: 24px; font-family: Arial, Helvetica, sans-serif; }
  .sheet { width: 190mm; min-height: 246mm; margin: 0 auto; padding: 14mm; background: #fff; box-shadow: 0 4px 20px rgba(15,23,42,.14); }
  .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding-bottom: 10px; border-bottom: 2px solid #0f172a; }
  .brand-name { font-size: 19px; font-weight: 700; letter-spacing: .02em; text-transform: uppercase; color: #0f172a; }
  .brand-line { font-size: 11px; color: #475569; margin-top: 3px; }
  .brand-contact { font-size: 11px; color: #64748b; margin-top: 3px; }
  .head-right { text-align: right; flex-shrink: 0; }
  .doc-label { font-size: 10px; letter-spacing: .16em; text-transform: uppercase; color: #64748b; }
  .doc-number { font-size: 25px; font-weight: 700; letter-spacing: .06em; color: #0f172a; margin-family: 'Courier New', monospace; }
  .stamp { display: inline-block; margin-top: 7px; padding: 3px 11px; border-radius: 3px; font-size: 10px; font-weight: 700; letter-spacing: .1em; color: #fff; background: {$d['statusColor']}; }
  .issued { font-size: 10.5px; color: #64748b; margin-top: 7px; }
  .bill { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding: 12px 0; border-bottom: 1px solid #cbd5e1; }
  .bill-block { min-width: 0; }
  .meta-label { font-size: 9.5px; letter-spacing: .14em; text-transform: uppercase; color: #64748b; }
  .meta-value { font-size: 14px; font-weight: 600; color: #0f172a; margin-top: 4px; }
  .meta-sub { font-size: 11px; color: #64748b; margin-top: 2px; }
  .bill-right { text-align: right; flex-shrink: 0; }
  .items { width: 100%; margin-top: 16px; border-collapse: collapse; }
  .items thead th { padding: 8px 9px; background: #f1f5f9; border-bottom: 2px solid #0f172a; font-size: 9.5px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #334155; text-align: left; }
  .items tbody td { padding: 11px 9px; border-bottom: 1px solid #cbd5e1; font-size: 12.5px; color: #1e293b; }
  .items tbody tr:last-child td { border-bottom: 2px solid #0f172a; }
  .c-cant { width: 52px; text-align: center; }
  .c-money { width: 96px; text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .totals { margin: 14px 0 0 auto; width: 78mm; }
  .totals-table { width: 100%; border-collapse: collapse; }
  .totals-table td { padding: 6px 0; font-size: 12px; color: #475569; border-bottom: 1px dotted #cbd5e1; }
  .totals-table td.v { text-align: right; padding-left: 14px; font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .grand { display: flex; justify-content: space-between; align-items: baseline; gap: 14px; margin-top: 14px; padding: 12px 14px; background: #f8fafc; border-top: 2px solid #0f172a; border-bottom: 3px double #0f172a; }
  .grand-label { font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #334155; }
  .grand-amount { font-size: 24px; font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .pay-note { margin-top: 14px; padding: 9px 12px; border-left: 3px solid {$d['statusColor']}; background: #f8fafc; font-size: 11px; color: #475569; }
  .qr-block { margin-top: 18px; text-align: center; }
  .qr { width: 30mm; height: 30mm; }
  .qr-cap { font-size: 10px; color: #64748b; margin-top: 5px; }
  .qr-tracking { font-size: 13px; font-weight: 700; letter-spacing: .06em; color: #0f172a; margin-top: 3px; font-family: 'Courier New', monospace; }
  .foot { margin-top: 18px; padding-top: 10px; border-top: 1px solid #cbd5e1; display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
  .foot-note { font-size: 9.5px; color: #64748b; max-width: 62%; }
  .foot-stamp { font-size: 9.5px; color: #64748b; text-align: right; flex-shrink: 0; }
  @media print {
    body { background: #fff; padding: 0; }
    .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
    .pay-note { background: none; }
  }
CSS;
    }

    private function body(array $d): string
    {
        $contact = $d['companyContact'] === []
            ? ''
            : '<p class="brand-contact">' . htmlspecialchars(implode(' · ', $d['companyContact']), ENT_QUOTES, 'UTF-8') . '</p>';

        $items = '<tr><td>Servicio de envío de paquetes</td>'
            . '<td class="c-cant">1</td>'
            . '<td class="c-money">' . $d['docSymbol'] . $d['baseDoc'] . '</td>'
            . '<td class="c-money">' . $d['docSymbol'] . $d['baseDoc'] . '</td></tr>';

        if ($d['taxEnabled'] && (float) $d['taxRatePct'] > 0) {
            $items .= '<tr><td>Impuesto al Valor Agregado (IVA ' . $d['taxRatePct'] . '%)</td>'
                . '<td class="c-cant">—</td>'
                . '<td class="c-money">' . $d['docSymbol'] . $d['taxDoc'] . '</td>'
                . '<td class="c-money">' . $d['docSymbol'] . $d['taxDoc'] . '</td></tr>';
        }

        $totals = '';
        if ($d['taxEnabled']) {
            $totals .= $this->row('Subtotal', $d['docSymbol'] . $d['baseDoc']);
            if ((float) $d['taxRatePct'] > 0) {
                $totals .= $this->row('IVA ' . $d['taxRatePct'] . '%', $d['docSymbol'] . $d['taxDoc']);
            }
        }
        if ($d['rateNote'] !== null) {
            $totals .= $this->row($d['rateNote']['label'] . ' (C$ por USD)', $d['rateNote']['rate']);
            $totals .= $this->row($d['rateNote']['equivLabel'], $d['rateNote']['equivValue']);
        }
        foreach ($d['paidBlock'] as $row) {
            $totals .= $this->row($row['label'], $row['value'], $row['color']);
        }

        $notes = $totals === '' ? '' : '<div class="totals"><table class="totals-table">' . $totals . '</table></div>';

        $payNote = '';
        if ($d['statusLabel'] === 'PENDIENTE') {
            $payNote = '<div class="pay-note">Factura pendiente de pago. Presentela junto con su paquete para retirar la entrega.</div>';
        }

        $qr = '';
        if ($d['qrDataUri'] !== null) {
            $qr = '<div class="qr-block">' . $this->qrImage($d['qrDataUri'])
                . '<p class="qr-cap">Escaneá para rastrear tu envío</p>'
                . '<p class="qr-tracking">' . $d['trackingNumber'] . '</p>'
                . '</div>';
        }

        $clientRuc = $d['clientRuc'] === '' ? '' : '<p class="meta-sub">' . $d['clientRuc'] . '</p>';
        $companyRuc = $d['companyRuc'] === '' ? '' : '<p class="brand-line">RUC: ' . $d['companyRuc'] . '</p>';
        $companyAddr = $d['companyAddr'] === '' ? '' : '<p class="brand-line">' . $d['companyAddr'] . '</p>';

        return <<<HTML
  <div class="sheet">
    <div class="head">
      <div class="brand">
        <div class="brand-name">{$d['companyName']}</div>
        {$companyRuc}{$companyAddr}{$contact}
      </div>
      <div class="head-right">
        <div class="doc-label">Factura</div>
        <div class="doc-number">{$d['invoiceNumber']}</div>
        <span class="stamp">{$d['statusLabel']}</span>
        <p class="issued">Emitida el {$d['issuedAt']}<br>Expresada en {$d['docCurrencyLabel']}</p>
      </div>
    </div>

    <div class="bill">
      <div class="bill-block">
        <div class="meta-label">Facturar a</div>
        <div class="meta-value">{$d['clientName']}</div>
        {$clientRuc}
      </div>
      <div class="bill-right">
        <div class="meta-label">Paquete</div>
        <div class="meta-value">{$d['trackingNumber']}</div>
        <p class="meta-sub">Envío Miami → Nicaragua</p>
      </div>
    </div>

    <table class="items">
      <thead>
        <tr>
          <th>Concepto</th>
          <th class="c-cant">Cant.</th>
          <th class="c-money">P. unitario</th>
          <th class="c-money">Importe</th>
        </tr>
      </thead>
      <tbody>{$items}</tbody>
    </table>

    {$notes}

    <div class="grand">
      <div class="grand-label">Total</div>
      <div class="grand-amount">{$d['docSymbol']}{$d['totalDoc']}</div>
    </div>

    {$payNote}
    {$qr}

    <div class="foot">
      <p class="foot-note">Documento generado por {$d['companyName']}. Conserve este comprobante para cualquier consulta sobre su envío.</p>
      <p class="foot-stamp">{$d['trackingNumber']} · {$d['issuedAt']}</p>
    </div>
  </div>
HTML;
    }
}