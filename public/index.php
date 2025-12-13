<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Src\Router;
use Src\Middleware\CorsMiddleware;
use Src\Helpers\Response;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', $_ENV['APP_DEBUG'] === 'true' ? 1 : 0);

set_exception_handler(function ($e) {
    // Always send CORS headers even on error
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    error_log($e->getMessage());
    Response::error($e->getMessage(), 500);
});

// CORS
CorsMiddleware::handle();

// Router
$router = new Router();

// Public routes
$router->post('/api/v1/auth/login', 'AuthController@login');

// Protected routes
$router->group(['middleware' => 'auth'], function ($router) {
    // Auth
    $router->get('/api/v1/auth/me', 'AuthController@me');
    $router->post('/api/v1/auth/logout', 'AuthController@logout');

    // Dashboard
    $router->get('/api/v1/dashboard/stats', 'DashboardController@stats');

    // Field Tours - Specific routes first!
    $router->get('/api/v1/field-tours/checklists', 'FieldTourController@getChecklists');
    $router->get('/api/v1/field-tours/checklists/:id', 'FieldTourController@getChecklistWithQuestions');
    $router->get('/api/v1/field-tours', 'FieldTourController@index');
    $router->get('/api/v1/field-tours/:id', 'FieldTourController@show');
    $router->post('/api/v1/field-tours', 'FieldTourController@store');
    $router->post('/api/v1/field-tours/:id/responses', 'FieldTourController@saveResponses');
    $router->post('/api/v1/field-tours/:id/complete', 'FieldTourController@complete');

    // Actions
    $router->get('/api/v1/actions', 'ActionController@index');
    $router->get('/api/v1/actions/:id', 'ActionController@show');
    $router->post('/api/v1/actions', 'ActionController@store');
    $router->post('/api/v1/actions/:id/comments', 'ActionController@addComment');
    $router->get('/api/v1/actions/:id/timeline', 'ActionController@timeline');
    $router->put('/api/v1/actions/:id/status', 'ActionController@updateStatus');
    $router->put('/api/v1/actions/:id/assign', 'ActionController@assign');

    // Notifications
    $router->get('/api/v1/notifications', 'NotificationController@index');
    $router->put('/api/v1/notifications/:id/read', 'NotificationController@markAsRead');
    $router->put('/api/v1/notifications/read-all', 'NotificationController@markAllAsRead');

    // Helpers
    $router->get('/api/v1/departments', 'HelperController@departments');
    $router->get('/api/v1/users', 'HelperController@users');

    // File upload routes
    $router->post('/api/v1/upload', 'FileController@upload');
    $router->delete('/api/v1/upload', 'FileController@delete');

    // User API routes (mock - simulates external user API)
    $router->get('/api/v1/users/:id', 'UserController@getUser');
    $router->post('/api/v1/users/batch', 'UserController@getUsersByIds');
    $router->get('/api/v1/users/search', 'UserController@searchUsers');

    // ===== WEB PANEL ENDPOINTS =====

    // Admin - Checklist Management
    $router->get('/api/v1/admin/checklists', 'AdminChecklistController@index');
    $router->post('/api/v1/admin/checklists', 'AdminChecklistController@store');
    $router->put('/api/v1/admin/checklists/:id', 'AdminChecklistController@update');
    $router->delete('/api/v1/admin/checklists/:id', 'AdminChecklistController@destroy');
    $router->post('/api/v1/admin/checklists/:id/duplicate', 'AdminChecklistController@duplicate');

    // Analytics
    $router->get('/api/v1/analytics/overview', 'AnalyticsController@overview');
    $router->get('/api/v1/analytics/by-department', 'AnalyticsController@byDepartment');
    $router->get('/api/v1/analytics/trends', 'AnalyticsController@trends');
    $router->get('/api/v1/analytics/risk-distribution', 'AnalyticsController@riskDistribution');

    // Approvals
    $router->get('/api/v1/approvals/closure-requests', 'ApprovalController@closureRequests');
    $router->post('/api/v1/approvals/closure-requests/:id/approve', 'ApprovalController@approve');
    $router->post('/api/v1/approvals/closure-requests/:id/reject', 'ApprovalController@reject');
});

$router->dispatch();
