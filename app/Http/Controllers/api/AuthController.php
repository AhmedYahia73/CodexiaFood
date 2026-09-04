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
            'name' => 'nullable|string',
            'email' => 'nullable|string',
            'password' => 'required|string',
            'guard' => 'nullable|string|in:admin,cashier_man,branch,kitchen',
        ]);

        $credentials = [];

        if ($request->filled('email')) {
            $credentials['email'] = $request->email;
        } elseif ($request->filled('name')) {
            $credentials['name'] = $request->name;
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Validation error: name or email is required.',
            ], 422);
        }

        $credentials['password'] = $request->password;

        $targetGuards = $request->filled('guard')
            ? [$request->guard]
            : ['admin', 'cashier_man', 'branch', 'kitchen'];

        $token = null;
        $activeGuard = null;

        foreach ($targetGuards as $guard) {
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

        return response()->json([
            'status' => true,
            'message' => 'Successfully authenticated',
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => $jwtGuard->factory()->getTTL() * 60,
                'guard' => $guard,
                'user' => $jwtGuard->user(),
            ],
        ]);
    }

    protected function getActiveGuard(): ?string
    {
        $guards = ['admin', 'cashier_man', 'branch', 'kitchen', 'api', 'web'];
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
