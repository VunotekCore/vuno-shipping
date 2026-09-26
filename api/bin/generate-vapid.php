<?php
declare(strict_types=1);

/**
 * Script one-shot para generar un par de claves VAPID.
 * Ejecutar: php bin/generate-vapid.php
 * Copiar el output a api/.env (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();

echo "VAPID_PUBLIC_KEY=" . $keys['publicKey'] . PHP_EOL;
echo "VAPID_PRIVATE_KEY=" . $keys['privateKey'] . PHP_EOL;