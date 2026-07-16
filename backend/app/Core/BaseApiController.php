<?php

declare(strict_types=1);

namespace App\Core;

abstract class BaseApiController extends Controller
{
    protected function json(mixed $data, int $statusCode = 200, array $headers = []): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        foreach ($headers as $key => $value) {
            header("$key: $value");
        }

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'JsonEncodeError',
                'message' => 'Gagal memproses respons.',
            ]);
            return;
        }

        echo $encoded;
    }

    protected function success(mixed $data = [], string $message = 'OK', array $meta = [], int $statusCode = 200): void
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        $this->json($response, $statusCode);
    }

    protected function error(string $error, string $message, array $errors = [], int $statusCode = 400): void
    {
        $response = [
            'success' => false,
            'error' => $error,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        $this->json($response, $statusCode);
    }
}
