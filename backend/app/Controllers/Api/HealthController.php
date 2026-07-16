<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\BaseApiController;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;

class HealthController extends BaseApiController
{
    public function index(array $params = []): void
    {
        $appName = Env::get('APP_NAME', 'JAGAPADI');
        $environment = Env::get('APP_ENV', 'production');

        $timezone = Env::get('APP_TIMEZONE', 'Asia/Jakarta');
        date_default_timezone_set($timezone);

        $data = [
            'app' => $appName,
            'environment' => $environment,
            'time' => date('Y-m-d\TH:i:sP'),
        ];

        try {
            $pdo = Database::connect();
            if ($pdo !== null) {
                $pdo->query('SELECT 1');
                $data['database'] = 'connected';
            } else {
                $data['database'] = 'disconnected';
            }
        } catch (\Throwable $e) {
            Logger::error('Health check - database unavailable: ' . $e->getMessage());

            $this->error(
                'DatabaseUnavailable',
                'Layanan database tidak tersedia.',
                [],
                503
            );
            return;
        }

        $this->success($data, "$appName is healthy");
    }
}
