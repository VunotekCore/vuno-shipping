<?php
declare(strict_types=1);

namespace App\Traits;

trait JwtAuth
{
    use ApiResponse;

    public function requireAuth(): array
    {
        return \requireAuth();
    }

    public function createToken(array $payload): string
    {
        return \createJwt($payload);
    }

    public function verifyToken(string $token): ?array
    {
        return \verifyJwt($token);
    }

    private function getConfig(): array
    {
        return \config();
    }
}
