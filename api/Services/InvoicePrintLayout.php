<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Base comun de las plantillas imprimibles de factura.
 *
 * Concentra el esqueleto HTML y los helpers de filas que usan por igual el
 * formato carta y el ticket, para que las plantillas solo contengan su
 * estructura y CSS.
 */
abstract class InvoicePrintLayout
{
    /**
     * @param array<string,mixed> $d Datos de `InvoicePrintService::buildData()`
     */
    abstract public function render(array $d): string;

    /** Envoltura HTML comun a todas las plantillas. */
    protected function document(string $title, string $css, string $body): string
    {
        return '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . $title . '</title>
<style>' . $css . '
</style>
</head>
<body>
' . $body . '
</body>
</html>';
    }

    /** Fila label/valor en tabla (formato carta). */
    protected function row(string $label, string $value, string $color = '#475569'): string
    {
        return '<tr><td>' . $label . '</td>'
            . '<td class="v" style="color:' . $color . ';">' . $value . '</td></tr>';
    }

    /** Fila label/valor en divs (formato ticket). */
    protected function kv(string $label, string $value): string
    {
        return '<div class="row"><span class="lbl">' . $label . '</span>'
            . '<span class="val">' . $value . '</span></div>';
    }

    /** Bloque `<img>` del QR de rastreo, o cadena vacia si no se pudo generar. */
    protected function qrImage(?string $dataUri, string $alt = 'QR de rastreo'): string
    {
        return $dataUri === null ? '' : '<img class="qr" src="' . $dataUri . '" alt="' . $alt . '">';
    }
}