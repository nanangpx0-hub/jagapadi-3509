<?php

declare(strict_types=1);

use App\Controllers\Api\AuthController as ApiAuthController;
use App\Controllers\Api\HealthController;
use App\Controllers\Api\MeController;
use App\Controllers\Web\AuthController as WebAuthController;
use App\Controllers\Web\DashboardController;
use App\Controllers\Web\PasswordController;
use App\Middleware\AdminMiddleware;
use App\Middleware\ApiAuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\WebAuthMiddleware;

/** @var \App\Core\Router $router */

// Global middleware
$router->addGlobalMiddleware(CsrfMiddleware::class);

// Web — Auth (public)
$router->get('/login', [WebAuthController::class, 'showLoginForm']);
$router->post('/login', [WebAuthController::class, 'login']);
$router->post('/logout', [WebAuthController::class, 'logout']);

// Web — Protected (session required)
$router->get('/dashboard', [DashboardController::class, 'index'], [WebAuthMiddleware::class]);
$router->get('/password/change', [PasswordController::class, 'showChangeForm'], [WebAuthMiddleware::class]);
$router->post('/password/change', [PasswordController::class, 'change'], [WebAuthMiddleware::class]);

// Web — Admin only
$router->get('/admin', [DashboardController::class, 'index'], [WebAuthMiddleware::class, AdminMiddleware::class]);

// API v1 — Public
$router->get('/api/v1/health', [HealthController::class, 'index']);
$router->post('/api/v1/auth/login', [ApiAuthController::class, 'login']);

// API v1 — Protected (JWT required)
$router->get('/api/v1/me', [MeController::class, 'index'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/auth/refresh', [ApiAuthController::class, 'refresh'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/auth/logout', [ApiAuthController::class, 'logout'], [ApiAuthMiddleware::class]);
$router->post('/api/v1/auth/change-password', [ApiAuthController::class, 'changePassword'], [ApiAuthMiddleware::class]);
