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

// Serve static files (test-ui.html, test-images/*)
$requestUri = $_SERVER['REQUEST_URI'];
$parsedPath = parse_url($requestUri, PHP_URL_PATH);

// Debug log
error_log("REQUEST_URI: " . $requestUri);
error_log("Parsed Path: " . $parsedPath);

// Static files
if ($parsedPath === '/test-ui.html') {
    $filePath = __DIR__ . '/test-ui.html';
    if (file_exists($filePath)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($filePath);
        exit;
    }
}

// Static directories (test-images)
if (strpos($parsedPath, '/test-images/') === 0) {
    $filePath = __DIR__ . $parsedPath;
    if (file_exists($filePath)) {
        $mimeType = mime_content_type($filePath);
        header('Content-Type: ' . $mimeType);
        readfile($filePath);
        exit;
    }
}

// Router
$router = new Router();

// Health check endpoint
$router->get('/api/v1/health', 'HealthController@check');

// Debug Endpoints (Remove in production)
$router->get('/api/v1/debug/env', 'DebugController@envInfo');
$router->get('/api/v1/debug/db', 'DebugController@testDbConnection');

// Checklist Endpoints
$router->get('/api/v1/checklists', 'ChecklistController@index');
$router->get('/api/v1/checklists/:id', 'ChecklistController@show');
$router->get('/api/v1/companies/:companyId/checklists', 'ChecklistController@getByCompany');
$router->post('/api/v1/checklists', 'ChecklistController@store');
$router->put('/api/v1/checklists/:id', 'ChecklistController@update');
$router->delete('/api/v1/checklists/:id', 'ChecklistController@destroy');

// Field Tour Endpoints
$router->post('/api/v1/field-tours', 'FieldTourController@start');
$router->get('/api/v1/field-tours', 'FieldTourController@index');
$router->get('/api/v1/field-tours/:id', 'FieldTourController@show');
$router->post('/api/v1/field-tours/:id/responses', 'FieldTourController@saveResponse');
$router->put('/api/v1/field-tours/:id/complete', 'FieldTourController@complete');

// File Upload Endpoints
$router->post('/api/v1/upload', 'FileUploadController@upload');
$router->delete('/api/v1/upload', 'FileUploadController@delete');

// Free Nonconformity Endpoints (Serbest Uygunsuzluk)
$router->post('/api/v1/free-nonconformities', 'FreeNonConformityController@store');
$router->get('/api/v1/free-nonconformities', 'FreeNonConformityController@index');
$router->get('/api/v1/free-nonconformities/:id', 'FreeNonConformityController@show');
$router->put('/api/v1/free-nonconformities/:id', 'FreeNonConformityController@update');
$router->delete('/api/v1/free-nonconformities/:id', 'FreeNonConformityController@destroy');

// Action Endpoints
$router->get('/api/v1/actions/form-config', 'ActionController@getFormConfig');
// Timeline endpoint - :id'den önce olmalı
$router->get('/api/v1/actions/:id/timeline', 'ActionController@getTimeline');
$router->get('/api/v1/actions', 'ActionController@index');
$router->get('/api/v1/actions/:id', 'ActionController@show');
$router->post('/api/v1/actions/manual', 'ActionController@createManual');
$router->put('/api/v1/actions/:id', 'ActionController@update');
$router->put('/api/v1/actions/:id/complete', 'ActionController@complete');

// Action Closure Endpoints (Kapatma Süreci)
$router->post('/api/v1/actions/:id/closure-request', 'ActionController@requestClosure');
$router->get('/api/v1/actions/:id/closures', 'ActionController@getClosures');
$router->put('/api/v1/actions/:id/closure/:closureId/approve', 'ActionController@approveClosure');
$router->put('/api/v1/actions/:id/closure/:closureId/reject', 'ActionController@rejectClosure');

// Dashboard & Analytics Endpoints
$router->get('/api/v1/dashboard/statistics', 'DashboardController@getStatistics');
$router->get('/api/v1/dashboard/risk-matrix', 'DashboardController@getRiskMatrix');
$router->get('/api/v1/dashboard/actions/prioritized', 'DashboardController@getPrioritizedActions');
$router->get('/api/v1/dashboard/actions/real-time', 'DashboardController@getRealTimeActions');

// Periodic Inspection Endpoints
$router->post('/api/v1/periodic-inspections', 'PeriodicInspectionController@store');
$router->get('/api/v1/periodic-inspections', 'PeriodicInspectionController@index');
$router->get('/api/v1/periodic-inspections/upcoming', 'PeriodicInspectionController@getUpcoming');
$router->get('/api/v1/periodic-inspections/overdue', 'PeriodicInspectionController@getOverdue');
$router->post('/api/v1/periodic-inspections/:id/complete', 'PeriodicInspectionController@complete');
$router->put('/api/v1/periodic-inspections/:id', 'PeriodicInspectionController@update');

// Export Endpoints
$router->get('/api/v1/export/actions/excel', 'ExportController@exportActionsExcel');
$router->get('/api/v1/export/actions/csv', 'ExportController@exportActionsCsv');
$router->get('/api/v1/export/actions/json', 'ExportController@exportActionsJson');

// Notification Endpoints (Debug)
$router->get('/api/v1/notifications', 'NotificationController@index');
$router->get('/api/v1/notifications/user/:userId', 'NotificationController@getByUser');
$router->put('/api/v1/notifications/:id/read', 'NotificationController@markAsRead');
$router->put('/api/v1/notifications/user/:userId/read-all', 'NotificationController@markAllAsRead');

$router->dispatch();
