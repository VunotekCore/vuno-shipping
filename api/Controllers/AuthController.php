<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\AuthService;
use App\Traits\JwtAuth;
use App\Config\Database;

class AuthController
{
    use JwtAuth;

    private UserModel $userModel;
    private AuthService $authService;

    public function __construct()
    {
        $this->userModel = new UserModel(Database::getConnection());
        $this->authService = new AuthService();
    }

    public function login(): never
    {
        requireMethod('POST');
        rateLimit('login', 5, 900);

        $data = getJsonInput();

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if ($email === '' || $password === '') {
            jsonError('Email y contraseña son requeridos');
        }

        $email = validateEmail($email);
        validatePassword($password);

        $user = $this->userModel->findByEmail($email);

        if ($user === null || !$this->authService->verifyPassword($password, $user['password'])) {
            jsonError('Credenciales incorrectas', 401);
        }

        $permissions = $this->userModel->getEffectivePermissions($user['role_id'], $user['id']);

        $token = $this->createToken([
            'sub'         => $user['id'],
            'email'       => $user['email'],
            'role_id'     => $user['role_id'],
            'role_slug'   => $user['role_slug'],
            'permissions' => $permissions,
        ]);

        $config = $this->getConfig();
        $expiresIn = $config['jwt']['expires_in'] ?? 86400;

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

        setcookie('admin_token', $token, [
            'expires'  => time() + $expiresIn,
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);

        jsonSuccess([
            'user' => [
                'id'          => $user['id'],
                'email'       => $user['email'],
                'name'        => $user['name'],
                'role_id'     => $user['role_id'],
                'role_name'   => $user['role_name'],
                'role_slug'   => $user['role_slug'],
                'permissions' => $permissions,
            ],
        ]);
    }

    public function verify(): never
    {
        requireMethod('GET', 'POST');
        rateLimit('verify', 15, 900);

        $payload = $this->requireAuth();

        $user = $this->userModel->findWithPermissions((int) $payload['sub']);

        if ($user === null) {
            jsonError('Usuario no encontrado', 401);
        }

        jsonSuccess(['user' => $user]);
    }

    public function logout(): never
    {
        requireMethod('POST');
        $this->requireAuth();

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

        setcookie('admin_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isSecure,
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);

        jsonSuccess(null, 'Sesión cerrada');
    }
}
