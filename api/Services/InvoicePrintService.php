<?php
declare(strict_types=1);

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;

/**
 * Arma los datos derivados de una factura y los entrega a la plantilla que
 * corresponda (`letter` o `ticket`).
 *
 * Los importes de la factura viven en USD; el documento puede emitirse en
 * cordonbas usando la tasa que quedo congelada al cobrar, o la tasa vigente
 * si todavia no se cobro.
 */
class InvoicePrintService
{
    public const FORMAT_LETTER = 'letter';
    public const FORMAT_TICKET = 'ticket';

    /** Normaliza el parametro `format` recibido por HTTP. */
    public static function sanitizeFormat(?string $format): string
    {
        return strtolower(trim((string) $format)) === self::FORMAT_TICKET
            ? self::FORMAT_TICKET
            : self::FORMAT_LETTER;
    }

    /**
     * Calcula todos los valores derivados una sola vez, para que carta y
     * ticket no puedan mostrar informacion distinta.
     *
     * @param array<string,mixed> $invoice Fila de `invoice` + joins de `InvoiceModel::findById`
     * @param array<string,mixed> $company Fila de `company`
     * @param array<string,mixed> $config Fila de `billing_config`
     * @param string $docCurrency 'USD' o 'CORD'
     * @return array<string,mixed>
     */
    public function buildData(array $invoice, array $company, array $config, string $docCurrency): array
    {
        $status = (string) ($invoice['status'] ?? 'pending');
        $isCord = $docCurrency !== 'USD';
        $paidInCord = $status === 'paid' && ($invoice['paid_currency'] ?? null) === 'CORD';

        // Tasa del documento:
        //  - cobrada en CORD -> snapshot `exchange_rate` (la que quedo aplicada)
        //  - pendiente o cobrada en USD -> tasa vigente de billing_config
        $rate = $paidInCord && $invoice['exchange_rate'] !== null
            ? (float) $invoice['exchange_rate']
            : (float) ($config['exchange_rate_usd'] ?? 36.00);

        $baseUsd = (float) $invoice['base_amount'];
        $taxUsd = (float) $invoice['tax_amount'];
        $totalUsd = (float) $invoice['total_amount'];

        $fmt = static fn(float $n): string => number_format($n, 2, '.', ',');
        $fmtRate = static fn(float $n): string => number_format($n, 4, '.', ',');
        $conv = static fn(float $usd): float => $isCord ? round($usd * $rate, 2) : $usd;

        $e = static fn(mixed $v): string => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');

        return [
            // Metadatos
            'invoiceNumber' => $e($invoice['invoice_number'] ?? ''),
            'trackingNumber' => $e($invoice['tracking_number'] ?? ''),
            'statusLabel' => self::statusLabel($status),
            'statusColor' => self::statusColor($status),
            'issuedAt' => date('d/m/Y H:i', strtotime((string) ($invoice['created_at'] ?? 'now'))),

            // Empresa
            'companyName' => $e($company['name'] ?? 'Vuno Web Shipping'),
            'companyRuc' => $e($company['ruc'] ?? ''),
            'companyAddr' => $e($company['address'] ?? ''),
            'companyContact' => array_values(array_filter([$e($company['phone'] ?? ''), $e($company['email'] ?? '')])),

            // Cliente
            'clientName' => $e($invoice['client_name'] ?? $invoice['client_name_ref'] ?? 'Cliente'),
            'clientRuc' => $e($invoice['client_ruc'] ?? ''),

            // Moneda e importes del documento
            'docCurrency' => $isCord ? 'CORD' : 'USD',
            'docCurrencyLabel' => $isCord ? 'Córdobas (C$)' : 'Dólares (US$)',
            'docSymbol' => $isCord ? 'C$' : '$',
            'baseDoc' => $fmt($conv($baseUsd)),
            'taxDoc' => $fmt($conv($taxUsd)),
            'totalDoc' => $fmt($conv($totalUsd)),
            'totalUsd' => $fmt($totalUsd),
            'taxRatePct' => number_format((float) ($invoice['tax_rate'] ?? 0), 0),
            'taxEnabled' => (int) ($config['tax_enabled'] ?? 0) === 1,

            // Nota de tasa: solo aparece en el documento en cordonbas
            'rateNote' => $isCord ? $this->rateNote($paidInCord, $rate, $fmtRate, $fmt, $totalUsd) : null,

            // Bloque de cobro (vacio si la factura no esta pagada)
            'paidBlock' => $this->paidBlock($invoice, $status, $rate, $fmt, $fmtRate, $totalUsd, $isCord),

            // QR de rastreo
            'qrDataUri' => $this->qr((string) ($invoice['tracking_number'] ?? '')),
        ];
    }

    /**
     * Render completo del documento imprimible.
     *
     * @param array<string,mixed> $d
     */
    public function render(array $d, string $format): string
    {
        $layout = self::sanitizeFormat($format) === self::FORMAT_TICKET
            ? new InvoiceTicketLayout()
            : new InvoiceLetterLayout();

        return $layout->render($d);
    }

    private static function statusLabel(string $status): string
    {
        return ['pending' => 'PENDIENTE', 'paid' => 'PAGADA', 'cancelled' => 'ANULADA'][$status] ?? strtoupper($status);
    }

    /** Colores legibles en impresion b/n, mas oscuros que los del badge en pantalla. */
    private static function statusColor(string $status): string
    {
        return ['pending' => '#b45309', 'paid' => '#15803d', 'cancelled' => '#b91c1c'][$status] ?? '#475569';
    }

    /** @param callable(float):string $fmtRate */
    private function rateNote(bool $paidInCord, float $rate, callable $fmtRate, callable $fmt, float $totalUsd): array
    {
        return [
            'label' => $paidInCord ? 'Tasa de cambio aplicada' : 'Tasa de cambio vigente',
            'rate' => 'C$' . $fmtRate($rate),
            'equivLabel' => 'Equivalente en USD',
            'equivValue' => '$' . $fmt($totalUsd),
        ];
    }

    /**
     * Filas del bloque de cobro. La tasa aplicada y el total en USD solo se
     * agregan cuando el documento no los muestra ya en la nota de tasa, para
     * no duplicar informacion (documento en C$ cobrado en C$).
     *
     * @param callable(float):string $fmt
     * @param callable(float):string $fmtRate
     * @return array<int,array{label:string,value:string,color:string}>
     */
    private function paidBlock(
        array $invoice,
        string $status,
        float $rate,
        callable $fmt,
        callable $fmtRate,
        float $totalUsd,
        bool $docIsCord
    ): array {
        if ($status !== 'paid') {
            return [];
        }

        $paidIsCord = ($invoice['paid_currency'] ?? null) === 'CORD';
        $rows = [];

        if ($paidIsCord && !$docIsCord) {
            $rows[] = ['label' => 'Tasa aplicada (C$ por USD)', 'value' => 'C$' . $fmtRate($rate), 'color' => '#475569'];
            $rows[] = ['label' => 'Total facturado en USD', 'value' => '$' . $fmt($totalUsd), 'color' => '#475569'];
        }

        $rows[] = [
            'label' => $paidIsCord ? 'Monto cobrado (Córdobas)' : 'Monto cobrado (USD)',
            'value' => ($paidIsCord ? 'C$' : '$') . $fmt((float) $invoice['paid_amount']),
            'color' => '#15803d',
        ];

        if ($paidIsCord) {
            $rows[] = [
                'label' => 'Método de pago',
                'value' => (string) ($invoice['payment_method_name'] ?? ''),
                'color' => '#475569',
            ];
            $reference = (string) ($invoice['payment_reference'] ?? '');
            if ($reference !== '') {
                $rows[] = ['label' => 'Referencia', 'value' => $reference, 'color' => '#475569'];
            }
        }

        return $rows;
    }

    /** QR como data URI para embeber en el HTML (mismo patron que `PackageController::label`). */
    private function qr(string $trackingNumber): ?string
    {
        if ($trackingNumber === '') {
            return null;
        }

        try {
            $options = new QROptions([
                'outputInterface' => QRGdImagePNG::class,
                'eccLevel' => EccLevel::M,
                'scale' => 4,
                'outputBase64' => true,
                'bgColor' => [255, 255, 255],
                'imageTransparent' => false,
            ]);

            return (new QRCode($options))->render($this->trackingUrl($trackingNumber));
        } catch (\Throwable $e) {
            apiLog('invoice_print_qr_failed: ' . $e->getMessage());
            return null;
        }
    }

    private function trackingUrl(string $trackingNumber): string
    {
        $baseUrl = (string) (config()['app']['base_url'] ?? 'http://localhost:4321');
        return rtrim($baseUrl, '/') . '/rastreo?tracking=' . rawurlencode($trackingNumber);
    }
}