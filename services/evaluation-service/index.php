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
use App\Controllers\EvaluationController;
use App\Controllers\HealthController;
use App\Services\EvaluationService;
use App\Services\ScoringService;
use App\Services\RankingService;
use App\Services\AnalyticsService;

try {
    // Initialize container
    $container = new Container();
    
    // Register services
    $container->register('database', function() {
        return new Database([
            'mysql' => [
                'host' => $_ENV['DB_HOST'] ?? 'mysql',
                'port' => (int)($_ENV['DB_PORT'] ?? 3306),
                'database' => $_ENV['DB_DATABASE'] ?? 'jee_portal',
                'username' => $_ENV['DB_USERNAME'] ?? 'jee_user',
                'password' => $_ENV['DB_PASSWORD'] ?? 'jee_password',
            ],
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
        return new Logger('evaluation-service');
    });
    
    $container->register('scoringService', function() use ($container) {
        return new ScoringService(
            $container->get('database'),
            $container->get('logger')
        );
    });
    
    $container->register('rankingService', function() use ($container) {
        return new RankingService(
            $container->get('database'),
            $container->get('logger')
        );
    });
    
    $container->register('analyticsService', function() use ($container) {
        return new AnalyticsService(
            $container->get('database'),
            $container->get('logger')
        );
    });
    
    $container->register('evaluationService', function() use ($container) {
        return new EvaluationService(
            $container->get('database'),
            $container->get('scoringService'),
            $container->get('rankingService'),
            $container->get('analyticsService'),
            $container->get('logger')
        );
    });
    
    // Initialize router
    $router = new Router($container);
    
    // Health check routes
    $router->get('/health', HealthController::class, 'health');
    $router->get('/health/ready', HealthController::class, 'ready');
    $router->get('/health/live', HealthController::class, 'live');
    
    // Evaluation routes
    $router->post('/evaluate/submission/{submissionId}', EvaluationController::class, 'evaluateSubmission');
    $router->post('/evaluate/exam/{examId}', EvaluationController::class, 'evaluateExam');
    $router->get('/results/{submissionId}', EvaluationController::class, 'getResults');
    $router->get('/results/exam/{examId}', EvaluationController::class, 'getExamResults');
    $router->get('/analytics/exam/{examId}', EvaluationController::class, 'getExamAnalytics');
    $router->post('/recalculate/rankings/{examId}', EvaluationController::class, 'recalculateRankings');
    
    // Performance metrics
    $router->get('/metrics', EvaluationController::class, 'getMetrics');
    
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