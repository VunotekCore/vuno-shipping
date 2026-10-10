<?php
declare(strict_types=1);

namespace App\Exceptions;

/**
 * Excepción de dominio con un código HTTP asociado. El handler global de
 * bootstrap.php la traduce a una respuesta JSON con ese código, de modo que
 * los Servicios pueden señalar errores (422, 404, 409...) sin llamar a
 * jsonError() directamente (que aborta la ejecución y no permite rollback).
 */
class ApiException extends \RuntimeException
{
    public function __construct(private int $statusCode, string $message)
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
