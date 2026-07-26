<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\AuthService;
use App\Config\Database;

class ProfileController
{
    private UserModel $model;
    private AuthService $authService;

    public function __construct()
    {
        $this->model = new UserModel(Database::getConnection());
        $this->authService = new AuthService();
    }

    public function get(): never
    {
        requireMethod('GET');
        $payload = requireAuth();

        $userId = (int) $payload['sub'];
        $user = $this->model->findWithPermissions($userId);

        if ($user === null) {
            jsonError('Usuario no encontrado', 404);
        }

        jsonSuccess(['user' => $user]);
    }

    public function update(): never
    {
        requireMethod('PUT');
        $payload = requireAuthMiddleware(true);
        rateLimit('profile_update', 10, 900);

        $userId = (int) $payload['sub'];
        $data = getJsonInput();

        $existing = $this->model->findById($userId);
        if ($existing === null) {
            jsonError('Usuario no encontrado', 404);
        }

        $updateData = [];

        if (isset($data['name']) && $data['name'] !== $existing['name']) {
            $updateData['name'] = sanitizeString($data['name'], 100);
        }

        if (isset($data['email']) && $data['email'] !== $existing['email']) {
            $email = validateEmail($data['email']);
            $conflict = $this->model->findByEmail($email);
            if ($conflict !== null && (int) $conflict['id'] !== $userId) {
                jsonError('Ya existe un usuario con ese email', 409);
            }
            $updateData['email'] = $email;
        }

        if (!empty($data['current_password'])) {
            if (empty($data['new_password'])) {
                jsonError('La nueva contraseña es requerida', 422);
            }
            validatePassword($data['new_password']);

            if (!$this->authService->verifyPassword($data['current_password'], $existing['password'])) {
                jsonError('La contraseña actual es incorrecta', 401);
            }

            $updateData['password'] = $this->authService->hashPassword($data['new_password']);
        } elseif (!empty($data['new_password'])) {
            jsonError('La contraseña actual es requerida para cambiar la contraseña', 422);
        }

        if (empty($updateData)) {
            jsonError('No hay datos para actualizar', 422);
        }

        $this->model->update($userId, $updateData);
        $updated = $this->model->findWithPermissions($userId);

        jsonSuccess(['user' => $updated], 'Perfil actualizado');
    }
}
