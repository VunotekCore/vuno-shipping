<?php
declare(strict_types=1);

namespace App\Services;

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Envio de notificaciones push (Web Push / VAPID) a los dispositivos suscritos
 * de un usuario. Se ejecuta siempre post-commit y NUNCA debe romper la operacion
 * principal: fallos y suscripciones caducas se manejan de forma silenciosa.
 */
class PushService
{
    private ?WebPush $webPush = null;

    public function __construct(private PushSubscriptionModel $model) {}

    private function client(): ?WebPush
    {
        if ($this->webPush !== null) {
            return $this->webPush;
        }

        $publicKey = (string) (config()['push']['vapid_public_key'] ?? '');
        $privateKey = (string) (config()['push']['vapid_private_key'] ?? '');
        if ($publicKey === '' || $privateKey === '') {
            return null;
        }

        $this->webPush = new WebPush([
            'VAPID' => [
                'subject' => (string) (config()['push']['vapid_subject'] ?? 'mailto:notificaciones@vunotek.com'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        return $this->webPush;
    }

    public function sendToUser(
        int $userId,
        string $title,
        string $body,
        string $url,
        string $tag = ''
    ): void {
        $client = $this->client();
        if ($client === null) {
            return;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
            'tag' => $tag,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $subscriptions = $this->model->listByUser($userId);
            if (empty($subscriptions)) {
                return;
            }

            foreach ($subscriptions as $sub) {
                $client->queueNotification(
                    Subscription::create([
                        'endpoint' => $sub['endpoint'],
                        'publicKey' => $sub['p256dh'],
                        'authToken' => $sub['auth'],
                    ]),
                    $payload,
                    ['TTL' => 3600]
                );
            }

            $expiredIds = [];
            foreach ($client->flush() as $report) {
                if (!$report->isSuccess()) {
                    $response = $report->getResponse();
                    // 404/410: endpoint ya no existe -> limpiar suscripcion
                    if ($response !== null && in_array($response->getStatusCode(), [404, 410], true)) {
                        $endpoint = $report->getEndpoint();
                        foreach ($subscriptions as $sub) {
                            if ($sub['endpoint'] === $endpoint) {
                                $expiredIds[] = (int) $sub['id'];
                                break;
                            }
                        }
                    }
                }
            }

            foreach ($expiredIds as $id) {
                $this->model->deleteById($id);
            }
        } catch (\Throwable $e) {
            apiLog('ERROR', 'Push notifier failed: ' . $e->getMessage());
        }
    }
}