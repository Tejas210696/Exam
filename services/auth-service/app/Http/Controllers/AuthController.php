<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\RefreshToken;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Services\JWTService;
use App\Services\UserService;
use Carbon\Carbon;

class AuthController extends Controller
{
    protected $jwtService;
    protected $userService;

    public function __construct(JWTService $jwtService, UserService $userService)
    {
        $this->jwtService = $jwtService;
        $this->userService = $userService;
    }

    /**
     * User login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $credentials = $request->validated();
            
            // Find user by email
            $user = User::where('email', $credentials['email'])->first();
            
            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return $this->errorResponse('Invalid credentials', 401);
            }

            // Check if user is active
            if ($user->status !== 'ACTIVE') {
                return $this->errorResponse('Account is not active', 403);
            }

            // Generate tokens
            $accessToken = $this->jwtService->generateAccessToken($user);
            $refreshToken = $this->jwtService->generateRefreshToken($user);

            // Store refresh token
            $this->storeRefreshToken($user, $refreshToken);

            // Update last login
            $user->update(['last_login' => now()]);

            // Log successful login
            Log::info('User login successful', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $this->userService->formatUser($user),
                    'tokens' => [
                        'access_token' => $accessToken,
                        'refresh_token' => $refreshToken,
                        'token_type' => 'Bearer',
                        'expires_in' => config('jwt.ttl') * 60,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Login error', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Login failed', 500);
        }
    }

    /**
     * User registration
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Check if email already exists
            if (User::where('email', $data['email'])->exists()) {
                return $this->errorResponse('Email already registered', 422);
            }

            // Create user
            $user = User::create([
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'role' => $data['role'] ?? 'STUDENT',
                'status' => 'PENDING', // Requires email verification
            ]);

            // Send verification email (implement as needed)
            // event(new UserRegistered($user));

            Log::info('User registered', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Please verify your email.',
                'data' => [
                    'user' => $this->userService->formatUser($user),
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration error', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Registration failed', 500);
        }
    }

    /**
     * Refresh access token
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $refreshToken = $request->validated()['refresh_token'];

            // Validate refresh token
            $tokenRecord = RefreshToken::where('token', hash('sha256', $refreshToken))
                ->where('expires_at', '>', now())
                ->where('revoked_at', null)
                ->first();

            if (!$tokenRecord) {
                return $this->errorResponse('Invalid or expired refresh token', 401);
            }

            $user = $tokenRecord->user;

            // Check if user is still active
            if ($user->status !== 'ACTIVE') {
                return $this->errorResponse('Account is not active', 403);
            }

            // Generate new tokens
            $newAccessToken = $this->jwtService->generateAccessToken($user);
            $newRefreshToken = $this->jwtService->generateRefreshToken($user);

            // Revoke old refresh token
            $tokenRecord->update(['revoked_at' => now()]);

            // Store new refresh token
            $this->storeRefreshToken($user, $newRefreshToken);

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'tokens' => [
                        'access_token' => $newAccessToken,
                        'refresh_token' => $newRefreshToken,
                        'token_type' => 'Bearer',
                        'expires_in' => config('jwt.ttl') * 60,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Token refresh error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Token refresh failed', 500);
        }
    }

    /**
     * User logout
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Revoke all refresh tokens for the user
            RefreshToken::where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            // Add token to blacklist (implement as needed)
            $token = $request->bearerToken();
            if ($token) {
                Cache::put("blacklisted_token:{$token}", true, now()->addDays(7));
            }

            Log::info('User logout', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logout successful',
            ]);

        } catch (\Exception $e) {
            Log::error('Logout error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Logout failed', 500);
        }
    }

    /**
     * Get current user
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $this->userService->formatUser($user),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get user error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to get user data', 500);
        }
    }

    /**
     * Verify JWT token
     */
    public function verify(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();
            
            if (!$token) {
                return $this->errorResponse('No token provided', 401);
            }

            // Check if token is blacklisted
            if (Cache::has("blacklisted_token:{$token}")) {
                return $this->errorResponse('Token is blacklisted', 401);
            }

            $payload = $this->jwtService->validateToken($token);
            
            if (!$payload) {
                return $this->errorResponse('Invalid token', 401);
            }

            $user = User::find($payload['sub']);
            
            if (!$user || $user->status !== 'ACTIVE') {
                return $this->errorResponse('User not found or inactive', 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'valid' => true,
                    'user' => $this->userService->formatUser($user),
                    'payload' => $payload,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Token verification error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Token verification failed', 401);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            
            if (!Hash::check($request->current_password, $user->password)) {
                return $this->errorResponse('Current password is incorrect', 422);
            }

            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            // Revoke all refresh tokens to force re-login
            RefreshToken::where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            Log::info('Password changed', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Change password error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Failed to change password', 500);
        }
    }

    /**
     * Store refresh token
     */
    protected function storeRefreshToken(User $user, string $refreshToken): void
    {
        RefreshToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $refreshToken),
            'expires_at' => now()->addDays(config('jwt.refresh_ttl', 30)),
        ]);

        // Clean up old refresh tokens (keep only last 5)
        $oldTokens = RefreshToken::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->skip(5)
            ->pluck('id');

        if ($oldTokens->isNotEmpty()) {
            RefreshToken::whereIn('id', $oldTokens)->delete();
        }
    }

    /**
     * Return error response
     */
    protected function errorResponse(string $message, int $statusCode): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $statusCode);
    }

    /**
     * Health check
     */
    public function health(): JsonResponse
    {
        try {
            // Check database connection
            $dbStatus = 'healthy';
            try {
                User::count();
            } catch (\Exception $e) {
                $dbStatus = 'unhealthy';
            }

            // Check Redis connection
            $redisStatus = 'healthy';
            try {
                Cache::get('health_check');
            } catch (\Exception $e) {
                $redisStatus = 'unhealthy';
            }

            $isHealthy = $dbStatus === 'healthy' && $redisStatus === 'healthy';

            return response()->json([
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'timestamp' => now()->toISOString(),
                'service' => 'auth-service',
                'version' => '1.0.0',
                'checks' => [
                    'database' => $dbStatus,
                    'redis' => $redisStatus,
                ],
            ], $isHealthy ? 200 : 503);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'service' => 'auth-service',
                'error' => $e->getMessage(),
            ], 503);
        }
    }
}