<?php

declare(strict_types=1);

// Error handling
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Autoloader
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/bootstrap.php';

use App\Core\Router;
use App\Core\Container;
use App\Core\Database;
use App\Core\Logger;
use App\Controllers\ProctorController;
use App\Controllers\HealthController;
use App\Services\FaceDetectionService;
use App\Services\BehaviorAnalysisService;
use App\Services\ViolationDetectionService;
use App\Services\ProctorSessionService;

try {
    // Initialize container
    $container = new Container();
    
    // Register services
    $container->register('database', function() {
        return new Database([
            'mongodb' => [
                'host' => $_ENV['MONGODB_HOST'] ?? 'mongodb',
                'port' => (int)($_ENV['MONGODB_PORT'] ?? 27017),
                'database' => $_ENV['MONGODB_DATABASE'] ?? 'jee_portal',
                'username' => $_ENV['MONGODB_USERNAME'] ?? 'jee_user',
                'password' => $_ENV['MONGODB_PASSWORD'] ?? 'jee_password',
            ],
            'redis' => [
                'host' => $_ENV['REDIS_HOST'] ?? 'redis',
                'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
            ],
        ]);
    });
    
    $container->register('logger', function() {
        return new Logger('proctor-service');
    });
    
    $container->register('faceDetectionService', function() use ($container) {
        return new FaceDetectionService(
            $container->get('logger')
        );
    });
    
    $container->register('behaviorAnalysisService', function() use ($container) {
        return new BehaviorAnalysisService(
            $container->get('database'),
            $container->get('logger')
        );
    });
    
    $container->register('violationDetectionService', function() use ($container) {
        return new ViolationDetectionService(
            $container->get('database'),
            $container->get('logger')
        );
    });
    
    $container->register('proctorSessionService', function() use ($container) {
        return new ProctorSessionService(
            $container->get('database'),
            $container->get('faceDetectionService'),
            $container->get('behaviorAnalysisService'),
            $container->get('violationDetectionService'),
            $container->get('logger')
        );
    });
    
    // Initialize router
    $router = new Router($container);
    
    // Health check routes
    $router->get('/health', HealthController::class, 'health');
    $router->get('/health/ready', HealthController::class, 'ready');
    $router->get('/health/live', HealthController::class, 'live');
    
    // Proctor session routes
    $router->post('/sessions', ProctorController::class, 'createSession');
    $router->get('/sessions/{sessionId}', ProctorController::class, 'getSession');
    $router->put('/sessions/{sessionId}/status', ProctorController::class, 'updateSessionStatus');
    $router->delete('/sessions/{sessionId}', ProctorController::class, 'endSession');
    
    // Face detection and verification
    $router->post('/face/detect', ProctorController::class, 'detectFace');
    $router->post('/face/verify', ProctorController::class, 'verifyFace');
    $router->post('/face/analyze', ProctorController::class, 'analyzeFace');
    
    // Behavior monitoring
    $router->post('/behavior/analyze', ProctorController::class, 'analyzeBehavior');
    $router->get('/behavior/patterns/{sessionId}', ProctorController::class, 'getBehaviorPatterns');
    
    // Violation detection
    $router->post('/violations/detect', ProctorController::class, 'detectViolations');
    $router->get('/violations/session/{sessionId}', ProctorController::class, 'getSessionViolations');
    $router->post('/violations/{violationId}/review', ProctorController::class, 'reviewViolation');
    
    // Real-time monitoring
    $router->post('/monitor/frame', ProctorController::class, 'processFrame');
    $router->get('/monitor/session/{sessionId}/status', ProctorController::class, 'getMonitoringStatus');
    
    // Analytics and reports
    $router->get('/analytics/session/{sessionId}', ProctorController::class, 'getSessionAnalytics');
    $router->get('/reports/violations', ProctorController::class, 'getViolationReports');
    
    // Route the request
    $router->route($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
        'error' => $e->getMessage(),
        'timestamp' => date('c'),
    ]);
    
    // Log error
    if (isset($container) && $container->has('logger')) {
        $container->get('logger')->error('Application error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
        ]);
    }
}