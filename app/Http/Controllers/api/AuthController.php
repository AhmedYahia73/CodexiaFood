<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

/**
 * @tags Auth
 */
class AuthController extends Controller
{
    /**
     * Get a JWT via given credentials.
     *
     * @unauthenticated
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'password' => 'required|string',
            'guard' => 'nullable|string|in:admin,cashier_man,branch,kitchen,captain_order',
        ]);

        $credentials = [];

        $credentials['name'] = $request->name;
        $credentials['password'] = $request->password;

        $targetGuards = $request->filled('guard')
            ? [$request->guard]
            : ['admin', 'cashier_man', 'branch', 'kitchen', 'captain_order'];

        $token = null;
        $activeGuard = null;

        foreach ($targetGuards as $guard) {
            if ($guard === 'branch' || $guard === 'kitchen' || $guard === 'captain_order') {
                if ($token = Auth::guard($guard)->attempt(['user_name' => $request->name, 'password' => $request->password])) {
                    $activeGuard = $guard;
                    break;
                }
            }

            if ($token = Auth::guard($guard)->attempt($credentials)) {
                $activeGuard = $guard;
                break;
            }
        }

        if (! $token) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized: Invalid login credentials.',
            ], 401);
        }

        return $this->respondWithToken($token, $activeGuard);
    }

    /**
     * Get the authenticated User.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        return response()->json([
            'status' => true,
            'data' => $user,
        ]);
    }

    /**
     * Log the user out (Invalidate the token).
     */
    public function logout(): JsonResponse
    {
        $guard = $this->getActiveGuard();

        if ($guard) {
            Auth::guard($guard)->logout();
        }

        return response()->json([
            'status' => true,
            'message' => 'Successfully logged out.',
        ]);
    }

    /**
     * Refresh a token.
     */
    public function refresh(): JsonResponse
    {
        $guard = $this->getActiveGuard();

        if (! $guard) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /** @var JWTGuard $jwtGuard */
        $jwtGuard = Auth::guard($guard);

        return $this->respondWithToken($jwtGuard->refresh(), $guard);
    }

    /**
     * Get token array structure.
     */
    protected function respondWithToken(string $token, string $guard): JsonResponse
    {
        /** @var JWTGuard $jwtGuard */
        $jwtGuard = Auth::guard($guard);
        $ttlMinutes = (int) $jwtGuard->factory()->getTTL();

        return response()->json([
            'status' => true,
            'message' => 'Successfully authenticated',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => now()->addMinutes($ttlMinutes)->toIso8601String(),
                'expires_in' => $ttlMinutes * 60,
                'guard' => $guard,
                'user' => $jwtGuard->user(),
            ],
        ]);
    }

    protected function getActiveGuard(): ?string
    {
        $guards = ['admin', 'cashier_man', 'branch', 'kitchen', 'captain_order', 'api', 'web'];
        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return $guard;
            }
        }

        return null;
    }

    protected function getAuthenticatedUser(): mixed
    {
        $guard = $this->getActiveGuard();

        return $guard ? Auth::guard($guard)->user() : null;
    }
}
