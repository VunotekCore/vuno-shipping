<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Models\UserModel;
use App\Config\Database;

requireMethod('POST');
rateLimit('refresh', 15, 900);

$data = getJsonInput();
$refreshToken = $data['refresh_token'] ?? '';

if ($refreshToken === '') {
    jsonError('Refresh token requerido', 422);
}

$userId = verifyRefreshToken($refreshToken);

if ($userId === null) {
    jsonError('Refresh token inválido o expirado', 401);
}

$userModel = new UserModel(Database::getConnection());
$user = $userModel->findWithPermissions($userId);

if ($user === null) {
    jsonError('Usuario no encontrado', 401);
}

$token = createJwt([
    'sub'         => $user['id'],
    'email'       => $user['email'],
    'role_id'     => $user['role_id'],
    'role_slug'   => $user['role_slug'],
    'permissions' => $user['permissions'],
]);

$newRefreshToken = createRefreshToken($user['id']);

$config = config();
$expiresIn = $config['jwt']['expires_in'] ?? 86400;

setcookie('admin_token', $token, [
    'expires'  => time() + $expiresIn,
    'path'     => '/',
    'secure'   => isSecureRequest(),
    'httponly'  => true,
    'samesite' => 'Lax',
]);

generateCsrfToken();

jsonSuccess([
    'refresh_token' => $newRefreshToken,
    'user' => [
        'id'          => $user['id'],
        'email'       => $user['email'],
        'name'        => $user['name'],
        'role_id'     => $user['role_id'],
        'role_name'   => $user['role_name'],
        'role_slug'   => $user['role_slug'],
        'permissions' => $user['permissions'],
    ],
]);
