<?php
declare(strict_types=1);

namespace App\Traits;

trait ApiResponse
{
    public function jsonResponse(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    public function jsonError(string $message, int $code = 400, array $errors = []): never
    {
        $response = ['success' => false, 'message' => $message];
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        $this->jsonResponse($response, $code);
    }

    public function jsonSuccess(mixed $data = null, string $message = '', int $code = 200): never
    {
        $response = ['success' => true];
        if ($message !== '') {
            $response['message'] = $message;
        }
        if ($data !== null) {
            $response['data'] = $data;
        }
        $this->jsonResponse($response, $code);
    }
}
