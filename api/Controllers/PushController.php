<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PushSubscriptionModel;
use App\Config\Database;
use App\Services\PushService;

/**
 * Endpoints self-service de notificaciones push.
 * Cada usuario gestiona SUS propias suscripciones (patron igual a ProfileController).
 */
class PushController
{
    private PushSubscriptionModel $model;

    public function __construct()
    {
        $this->model = new PushSubscriptionModel(Database::getConnection());
    }

    public function config(): never
    {
        requireMethod('GET');
        requireAuth();

        $publicKey = (string) (config()['push']['vapid_public_key'] ?? '');
        if ($publicKey === '') {
            jsonError('Web Push no configurado', 500);
        }

        jsonSuccess([
            'vapid_public_key' => $publicKey,
            'subject' => (string) (config()['push']['vapid_subject'] ?? ''),
        ]);
    }

    public function subscribe(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('push_subscribe', 10, 900);

        $data = getJsonInput();
        $userId = (int) ($payload['sub'] ?? 0);
        if ($userId <= 0) jsonError('Usuario no valido', 401);

        $endpoint = sanitizeString((string) ($data['endpoint'] ?? ''), 500);
        $p256dh = sanitizeString((string) ($data['p256dh'] ?? ''), 255);
        $auth = sanitizeString((string) ($data['auth'] ?? ''), 255);

        if ($endpoint === '' || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
            jsonError('Endpoint invalido', 422);
        }
        if ($p256dh === '' || $auth === '') {
            jsonError('p256dh y auth son requeridos', 422);
        }

        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null;

        $this->model->upsert($userId, $endpoint, $p256dh, $auth, $userAgent);

        jsonSuccess(null, 'Suscripcion guardada', 201);
    }

    public function unsubscribe(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('push_unsubscribe', 10, 900);

        $data = getJsonInput();
        $userId = (int) ($payload['sub'] ?? 0);
        if ($userId <= 0) jsonError('Usuario no valido', 401);

        $endpoint = sanitizeString((string) ($data['endpoint'] ?? ''), 500);
        if ($endpoint === '') jsonError('Endpoint requerido', 422);

        $this->model->deleteByEndpoint($userId, $endpoint);

        jsonSuccess(null, 'Suscrito eliminada');
    }

    public function test(): never
    {
        requireMethod('POST');
        $payload = requireAuthMiddleware(true);
        rateLimit('push_test', 5, 900);

        $data = getJsonInput();
        $targetUserId = isset($data['user_id']) ? (int) $data['user_id'] : null;

        if ($targetUserId !== null) {
            if (!hasPermission($payload, 'delivery_route', 'edit')) {
                jsonError('No tenes permiso', 403);
            }
        } else {
            $targetUserId = (int) ($payload['sub'] ?? 0);
        }

        if ($targetUserId <= 0) jsonError('user_id invalido', 422);

        $service = new PushService($this->model);
        $service->sendToUser(
            $targetUserId,
            'Prueba de notificacion',
            'Las notificaciones push estan activas en tu dispositivo.',
            '/backoffice/conductor',
            'push-test'
        );

        jsonSuccess(null, 'Notificacion de prueba enviada');
    }
}