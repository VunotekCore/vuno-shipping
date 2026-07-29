<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;

class MailService
{
    private PHPMailer $mailer;
    private bool $configured = false;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);
        try {
            $cfg = config()['mail'] ?? [];
            if (!empty($cfg['host'])) {
                $this->mailer->isSMTP();
                $this->mailer->Host = $cfg['host'];
                $this->mailer->SMTPAuth = true;
                $this->mailer->Username = $cfg['username'] ?? '';
                $this->mailer->Password = $cfg['password'] ?? '';
                $this->mailer->Port = (int) ($cfg['port'] ?? 587);
                $this->mailer->SMTPSecure = $cfg['encryption'] ?? 'tls';
                $this->configured = true;
            }
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->setFrom(
                $cfg['from'] ?? 'noreply@vunotek.com',
                $cfg['from_name'] ?? 'Vuno Web Shipping'
            );
        } catch (\Throwable $e) {
            apiLog('ERROR', 'MailService init: ' . $e->getMessage());
        }
    }

    public function sendStatusChange(
        string $toEmail,
        string $toName,
        string $trackingNumber,
        string $trackingUrl,
        string $oldStatus,
        string $newStatus,
        ?string $description = null
    ): bool {
        if (!$this->configured) {
            apiLog('WARNING', 'Mail not configured, skipping send');
            return false;
        }

        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($toEmail, $toName);

            $labels = [
                'pending' => 'Pendiente',
                'received' => 'Recibido en bodega',
                'in_transit' => 'En tránsito',
                'out_for_delivery' => 'En ruta de entrega',
                'delivered' => 'Entregado',
                'returned' => 'Devuelto',
                'cancelled' => 'Cancelado',
                'disputed' => 'En disputa',
            ];

            $oldLabel = $labels[$oldStatus] ?? $oldStatus;
            $newLabel = $labels[$newStatus] ?? $newStatus;

            $this->mailer->Subject = "Tu paquete {$trackingNumber} ha cambiado de estado";
            $this->mailer->isHTML(true);
            $this->mailer->Body = $this->buildHtml($trackingNumber, $trackingUrl, $oldLabel, $newLabel, $description);
            $this->mailer->AltBody = strip_tags(str_replace(['<br>', '</p>'], "\n", $this->mailer->Body));

            $this->mailer->send();
            return true;
        } catch (\Throwable $e) {
            apiLog('ERROR', 'Mail send failed: ' . $e->getMessage());
            return false;
        }
    }

    private function buildHtml(
        string $trackingNumber,
        string $trackingUrl,
        string $oldLabel,
        string $newLabel,
        ?string $description
    ): string {
        $descHtml = $description
            ? "<p style='color:#64748b;margin:12px 0;font-size:14px;'>{$description}</p>"
            : '';

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f0f3ff;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:32px 16px;">
<table width="600" cellpadding="0" cellspacing="0" style="margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;">
<tr><td style="padding:24px;background:#001e40;text-align:center;">
<h1 style="color:#fff;margin:0;font-size:20px;">Vuno Web Shipping</h1>
</td></tr>
<tr><td style="padding:32px 24px;">
<h2 style="color:#001e40;margin:0 0 16px;font-size:18px;">Tu paquete #{$trackingNumber} ha cambiado de estado</h2>
<table width="100%" cellpadding="12" cellspacing="0">
<tr>
<td style="background:#f0f3ff;border-radius:8px;text-align:center;width:45%;">
<p style="color:#64748b;margin:0 0 4px;font-size:12px;">Estado anterior</p>
<p style="color:#001e40;margin:0;font-weight:bold;font-size:16px;">{$oldLabel}</p>
</td>
<td style="text-align:center;width:10%;"><span style="color:#001e40;font-size:20px;">→</span></td>
<td style="background:#e8f5e9;border-radius:8px;text-align:center;width:45%;">
<p style="color:#64748b;margin:0 0 4px;font-size:12px;">Estado actual</p>
<p style="color:#2e7d32;margin:0;font-weight:bold;font-size:16px;">{$newLabel}</p>
</td>
</tr>
</table>
{$descHtml}
<table width="100%" cellpadding="0" cellspacing="0"><tr><td style="padding:24px 0 0;">
<a href="{$trackingUrl}" style="display:inline-block;padding:12px 32px;background:#001e40;color:#fff;text-decoration:none;border-radius:8px;font-size:14px;">Rastrear paquete</a>
</td></tr></table>
</td></tr>
<tr><td style="padding:16px 24px;background:#f8f9fa;text-align:center;">
<p style="color:#94a3b8;margin:0;font-size:12px;">Vuno Web Shipping &mdash; Env&iacute;os Miami a Nicaragua</p>
</td></tr>
</table>
</td></tr></table>
</body>
</html>
HTML;
    }
}
