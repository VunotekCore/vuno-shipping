<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Factura en bobina de 80 mm para impresora termica (area util ~72 mm).
 *
 * Pensada para entregas en campo, donde no hay impresora de hoja: una sola
 * columna, tipografia compacta, separadores punteados y el QR de rastreo
 * centrado para que el cliente lo escanee desde el celular.
 *
 * Si el navegador ignora `@page size`, hay que seleccionar la bobina de 80 mm
 * en el dialogo de impresion.
 */
class InvoiceTicketLayout extends InvoicePrintLayout
{
    public function render(array $d): string
    {
        return $this->document(
            'Factura ' . $d['invoiceNumber'],
            $this->css(),
            $this->body($d)
        );
    }

    private function css(): string
    {
        return <<<CSS
  @page { size: 80mm auto; margin: 3mm; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { background: #e8ecf1; padding: 16px; font-family: 'Courier New', Courier, ui-monospace, monospace; font-size: 11px; line-height: 1.35; color: #000; }
  .sheet { width: 72mm; min-height: 120mm; margin: 0 auto; padding: 5mm 4mm; background: #fff; box-shadow: 0 3px 14px rgba(15,23,42,.16); }
  .c { text-align: center; }
  .co { font-size: 14px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
  .l { font-size: 10px; }
  .dash { border-top: 1px dashed #000; margin: 5px 0; }
  .solid { border-top: 1px solid #000; margin: 5px 0; }
  .doc { font-size: 15px; font-weight: 700; letter-spacing: .1em; margin-top: 5px; }
  .stamp { display: inline-block; margin-top: 4px; padding: 1px 7px; border: 1px solid #000; font-size: 10px; font-weight: 700; letter-spacing: .08em; }
  .k { font-size: 9px; letter-spacing: .08em; text-transform: uppercase; }
  .v { font-size: 11px; font-weight: 600; }
  .row { display: flex; justify-content: space-between; gap: 6px; padding: 1.5px 0; }
  .row .lbl { flex: 1; }
  .row .val { white-space: nowrap; font-weight: 600; }
  .item { padding: 2px 0; }
  .item-d { font-size: 11px; font-weight: 600; }
  .item-r { display: flex; justify-content: space-between; font-size: 11px; }
  .total { margin-top: 5px; padding: 4px 0; border-top: 1px solid #000; border-bottom: 3px double #000; display: flex; justify-content: space-between; align-items: baseline; gap: 6px; }
  .big { font-size: 17px; font-weight: 700; letter-spacing: .08em; white-space: nowrap; }
  .note { margin-top: 6px; text-align: center; font-size: 10px; font-weight: 700; letter-spacing: .06em; border: 1px solid #000; padding: 2px 4px; }
  .qr-wrap { margin-top: 7px; }
  .qr { width: 26mm; height: 26mm; image-rendering: pixelated; }
  .qr-cap { font-size: 9px; margin-top: 2px; }
  .tr-no { font-size: 12px; font-weight: 700; letter-spacing: .06em; margin-top: 3px; }
  .foot { margin-top: 6px; font-size: 8.5px; line-height: 1.3; }
  @media print {
    body { background: #fff; padding: 0; }
    .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
  }
CSS;
    }

    private function body(array $d): string
    {
        $contact = $d['companyContact'] === []
            ? ''
            : '<div class="l">' . htmlspecialchars(implode(' · ', $d['companyContact']), ENT_QUOTES, 'UTF-8') . '</div>';

        $items = '<div class="item">'
            . '<div class="item-d">Servicio de envío de paquetes</div>'
            . '<div class="item-r"><span>Cant: 1</span><span>' . $d['docSymbol'] . $d['baseDoc'] . '</span></div>'
            . '</div>';

        if ($d['taxEnabled'] && (float) $d['taxRatePct'] > 0) {
            $items .= '<div class="item">'
                . '<div class="item-d">IVA ' . $d['taxRatePct'] . '%</div>'
                . '<div class="item-r"><span>—</span><span>' . $d['docSymbol'] . $d['taxDoc'] . '</span></div>'
                . '</div>';
        }

        $kv = '';
        if ($d['taxEnabled']) {
            $kv .= $this->kv('Subtotal', $d['docSymbol'] . $d['baseDoc']);
            if ((float) $d['taxRatePct'] > 0) {
                $kv .= $this->kv('IVA ' . $d['taxRatePct'] . '%', $d['docSymbol'] . $d['taxDoc']);
            }
        }
        if ($d['rateNote'] !== null) {
            $kv .= $this->kv($d['rateNote']['label'] . ' (C$/USD)', $d['rateNote']['rate']);
            $kv .= $this->kv($d['rateNote']['equivLabel'], $d['rateNote']['equivValue']);
        }
        foreach ($d['paidBlock'] as $row) {
            $kv .= $this->kv($row['label'], $row['value']);
        }

        $qr = '';
        if ($d['qrDataUri'] !== null) {
            $qr = '<div class="qr-wrap c">' . $this->qrImage($d['qrDataUri'], 'QR de rastreo')
                . '<div class="qr-cap">Rastreá tu envío</div></div>';
        }

        $payNote = $d['statusLabel'] === 'PENDIENTE'
            ? '<div class="note">PAGAR PARA RETIRAR EL PAQUETE</div>'
            : '';

        $clientRuc = $d['clientRuc'] === '' ? '' : '<div class="row"><span class="l">' . $d['clientRuc'] . '</span></div>';
        $companyRuc = $d['companyRuc'] === '' ? '' : '<div class="l">RUC: ' . $d['companyRuc'] . '</div>';
        $companyAddr = $d['companyAddr'] === '' ? '' : '<div class="l">' . $d['companyAddr'] . '</div>';

        return <<<HTML
  <div class="sheet">
    <div class="c">
      <div class="co">{$d['companyName']}</div>
      {$companyRuc}{$companyAddr}{$contact}
    </div>

    <div class="dash"></div>

    <div class="c">
      <div class="k">Factura</div>
      <div class="doc">{$d['invoiceNumber']}</div>
      <span class="stamp">{$d['statusLabel']}</span>
      <div class="l" style="margin-top:4px;">{$d['issuedAt']}</div>
      <div class="l">Expresada en {$d['docCurrencyLabel']}</div>
    </div>

    <div class="dash"></div>

    <div class="row"><span class="lbl k">Facturar a</span></div>
    <div class="row"><span class="v">{$d['clientName']}</span></div>
    {$clientRuc}

    <div class="dash"></div>

    <div class="row"><span class="lbl k">Paquete</span><span class="val">{$d['trackingNumber']}</span></div>
    <div class="row"><span class="l">Envío Miami → Nicaragua</span></div>

    <div class="solid"></div>

    {$items}

    <div class="solid"></div>

    <div>{$kv}</div>

    <div class="total">
      <span class="k">Total</span>
      <span class="big">{$d['docSymbol']}{$d['totalDoc']}</span>
    </div>

    {$payNote}

    <div class="dash"></div>

    {$qr}

    <div class="c">
      <div class="k">Rastreo</div>
      <div class="tr-no">{$d['trackingNumber']}</div>
      <div class="foot">{$d['companyName']}<br>Gracias por su confianza</div>
    </div>
  </div>
HTML;
    }
}