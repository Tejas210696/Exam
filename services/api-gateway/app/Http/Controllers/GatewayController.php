<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\ServiceDiscovery;
use App\Services\LoadBalancer;
use App\Services\CircuitBreaker;

class GatewayController extends Controller
{
    protected $serviceDiscovery;
    protected $loadBalancer;
    protected $circuitBreaker;

    // Service mappings
    protected $serviceMap = [
        'auth' => 'auth-service',
        'users' => 'user-service',
        'exams' => 'exam-service',
        'questions' => 'question-service',
        'submissions' => 'submission-service',
        'evaluations' => 'evaluation-service',
        'proctor' => 'proctor-service',
        'analytics' => 'analytics-service',
        'notifications' => 'notification-service',
    ];

    public function __construct(
        ServiceDiscovery $serviceDiscovery,
        LoadBalancer $loadBalancer,
        CircuitBreaker $circuitBreaker
    ) {
        $this->serviceDiscovery = $serviceDiscovery;
        $this->loadBalancer = $loadBalancer;
        $this->circuitBreaker = $circuitBreaker;
    }

    /**
     * Route requests to appropriate microservices
     */
    public function route(Request $request, string $service, string $path = ''): JsonResponse
    {
        try {
            // Validate service exists
            if (!isset($this->serviceMap[$service])) {
                return $this->errorResponse('Service not found', 404);
            }

            $serviceName = $this->serviceMap[$service];
            
            // Check circuit breaker
            if ($this->circuitBreaker->isOpen($serviceName)) {
                return $this->errorResponse('Service temporarily unavailable', 503);
            }

            // Get service instance
            $serviceUrl = $this->getServiceUrl($serviceName);
            if (!$serviceUrl) {
                return $this->errorResponse('Service unavailable', 503);
            }

            // Prepare request
            $fullPath = $path ? "/{$path}" : '';
            $url = "{$serviceUrl}{$fullPath}";
            
            // Forward headers (excluding host)
            $headers = $this->prepareHeaders($request);
            
            // Make request based on method
            $response = $this->makeRequest($request, $url, $headers);
            
            // Record success for circuit breaker
            $this->circuitBreaker->recordSuccess($serviceName);
            
            // Log request
            $this->logRequest($request, $serviceName, $response->status());
            
            return response()->json(
                $response->json(),
                $response->status()
            );

        } catch (\Exception $e) {
            // Record failure for circuit breaker
            $this->circuitBreaker->recordFailure($serviceName ?? $service);
            
            Log::error('Gateway routing error', [
                'service' => $service,
                'path' => $path,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('Internal server error', 500);
        }
    }

    /**
     * Handle authentication requests
     */
    public function auth(Request $request, string $path = ''): JsonResponse
    {
        return $this->route($request, 'auth', $path);
    }

    /**
     * Handle user requests
     */
    public function users(Request $request, string $path = ''): JsonResponse
    {
        return $this->route($request, 'users', $path);
    }

    /**
     * Handle exam requests
     */
    public function exams(Request $request, string $path = ''): JsonResponse
    {
        return $this->route($request, 'exams', $path);
    }

    /**
     * Handle question requests
     */
    public function questions(Request $request, string $path = ''): JsonResponse
    {
        return $this->route($request, 'questions', $path);
    }

    /**
     * Handle submission requests
     */
    public function submissions(Request $request, string $path = ''): JsonResponse
    {
        return $this->route($request, 'submissions', $path);
    }

    /**
     * Handle evaluation requests
     */
    public function evaluations(Request $request, string $path = ''): JsonResponse
    {
        return $this->route($request, 'evaluations', $path);
    }

    /**
     * Handle proctor requests
     */
    public function proctor(Request $request, string $path = ''): JsonResponse
    {
        return $this->route($request, 'proctor', $path);
    }

    /**
     * Handle analytics requests
     */
    public function analytics(Request $request, string $path = ''): JsonResponse
    {
        return $this->route($request, 'analytics', $path);
    }

    /**
     * Get service URL with load balancing
     */
    protected function getServiceUrl(string $serviceName): ?string
    {
        // Try to get from service discovery
        $instances = $this->serviceDiscovery->getInstances($serviceName);
        
        if (empty($instances)) {
            // Fallback to environment configuration
            $instances = $this->getFallbackInstances($serviceName);
        }

        if (empty($instances)) {
            return null;
        }

        // Use load balancer to select instance
        return $this->loadBalancer->selectInstance($instances);
    }

    /**
     * Get fallback service instances from configuration
     */
    protected function getFallbackInstances(string $serviceName): array
    {
        $serviceUrls = [
            'auth-service' => [env('AUTH_SERVICE_URL', 'http://auth-service:8000')],
            'user-service' => [env('USER_SERVICE_URL', 'http://user-service:8000')],
            'exam-service' => [env('EXAM_SERVICE_URL', 'http://exam-service:8000')],
            'question-service' => [env('QUESTION_SERVICE_URL', 'http://question-service:8000')],
            'submission-service' => [env('SUBMISSION_SERVICE_URL', 'http://submission-service:8000')],
            'evaluation-service' => [env('EVALUATION_SERVICE_URL', 'http://evaluation-service')],
            'proctor-service' => [env('PROCTOR_SERVICE_URL', 'http://proctor-service')],
            'analytics-service' => [env('ANALYTICS_SERVICE_URL', 'http://analytics-service')],
            'notification-service' => [env('NOTIFICATION_SERVICE_URL', 'http://notification-service:8000')],
        ];

        return $serviceUrls[$serviceName] ?? [];
    }

    /**
     * Prepare headers for forwarding
     */
    protected function prepareHeaders(Request $request): array
    {
        $headers = [];
        
        // Forward important headers
        $forwardHeaders = [
            'Authorization',
            'Content-Type',
            'Accept',
            'User-Agent',
            'X-Forwarded-For',
            'X-Real-IP',
            'X-Request-ID',
        ];

        foreach ($forwardHeaders as $header) {
            if ($request->hasHeader($header)) {
                $headers[$header] = $request->header($header);
            }
        }

        // Add gateway headers
        $headers['X-Gateway'] = 'jee-portal-gateway';
        $headers['X-Gateway-Version'] = '1.0.0';
        $headers['X-Forwarded-Host'] = $request->getHost();
        
        // Generate request ID if not present
        if (!isset($headers['X-Request-ID'])) {
            $headers['X-Request-ID'] = uniqid('req_', true);
        }

        return $headers;
    }

    /**
     * Make HTTP request to service
     */
    protected function makeRequest(Request $request, string $url, array $headers)
    {
        $method = strtolower($request->method());
        $timeout = config('gateway.request_timeout', 30);

        $httpClient = Http::timeout($timeout)->withHeaders($headers);

        // Add request body for POST/PUT/PATCH requests
        if (in_array($method, ['post', 'put', 'patch'])) {
            $body = $request->all();
            return $httpClient->$method($url, $body);
        }

        // Add query parameters for GET requests
        if ($method === 'get') {
            $query = $request->query();
            return $httpClient->get($url, $query);
        }

        return $httpClient->$method($url);
    }

    /**
     * Log request for monitoring
     */
    protected function logRequest(Request $request, string $service, int $statusCode): void
    {
        $logData = [
            'service' => $service,
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $statusCode,
            'response_time' => microtime(true) - LARAVEL_START,
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'request_id' => $request->header('X-Request-ID'),
        ];

        Log::info('Gateway request', $logData);

        // Store metrics for monitoring
        $this->recordMetrics($service, $statusCode, $logData['response_time']);
    }

    /**
     * Record metrics for monitoring
     */
    protected function recordMetrics(string $service, int $statusCode, float $responseTime): void
    {
        $metricsKey = "metrics:gateway:{$service}";
        
        Cache::increment("{$metricsKey}:requests_total");
        
        if ($statusCode >= 400) {
            Cache::increment("{$metricsKey}:errors_total");
        }

        // Store response time (simplified - in production use proper metrics system)
        $responseTimes = Cache::get("{$metricsKey}:response_times", []);
        $responseTimes[] = $responseTime;
        
        // Keep only last 100 response times
        if (count($responseTimes) > 100) {
            $responseTimes = array_slice($responseTimes, -100);
        }
        
        Cache::put("{$metricsKey}:response_times", $responseTimes, 3600);
    }

    /**
     * Return error response
     */
    protected function errorResponse(string $message, int $statusCode): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => true,
        ], $statusCode);
    }

    /**
     * Health check endpoint
     */
    public function health(): JsonResponse
    {
        $services = [];
        
        foreach ($this->serviceMap as $alias => $serviceName) {
            $isHealthy = $this->checkServiceHealth($serviceName);
            $services[$alias] = [
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'service_name' => $serviceName,
            ];
        }

        $overallHealth = collect($services)->every(fn($service) => $service['status'] === 'healthy');

        return response()->json([
            'status' => $overallHealth ? 'healthy' : 'degraded',
            'timestamp' => now()->toISOString(),
            'uptime' => $this->getUptime(),
            'services' => $services,
            'version' => '1.0.0',
        ], $overallHealth ? 200 : 503);
    }

    /**
     * Check individual service health
     */
    protected function checkServiceHealth(string $serviceName): bool
    {
        try {
            $serviceUrl = $this->getServiceUrl($serviceName);
            if (!$serviceUrl) {
                return false;
            }

            $response = Http::timeout(5)->get("{$serviceUrl}/health");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get application uptime
     */
    protected function getUptime(): int
    {
        $uptimeFile = storage_path('app/uptime');
        
        if (!file_exists($uptimeFile)) {
            file_put_contents($uptimeFile, time());
        }
        
        $startTime = (int) file_get_contents($uptimeFile);
        return time() - $startTime;
    }
}