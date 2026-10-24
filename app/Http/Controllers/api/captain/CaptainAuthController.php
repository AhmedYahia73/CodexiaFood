<?php

namespace App\Http\Controllers\api\captain;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

/**
 * @tags Captain Auth
 */
class CaptainAuthController extends Controller
{
    /**
     * Authenticate captain order user and return JWT token.
     *
     * @unauthenticated
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            /**
             * Username or Name of the captain.
             *
             * @var string
             *
             * @example "captain1"
             */
            'user_name' => 'required|nullable|string',

            /**
             * Alias for user_name.
             *
             * @var string
             *
             * @example "captain1"
             */

            /**
             * Captain account password.
             *
             * @var string
             *
             * @example "secret123"
             */
            'password' => 'required|string',
        ]);

        $username = $validated['user_name'];
        $password = $validated['password'];

        $token = Auth::guard('captain_order')->attempt([
            'user_name' => $username,
            'password' => $password,
        ]);

        if (! $token) {
            $token = Auth::guard('captain_order')->attempt([
                'name' => $username,
                'password' => $password,
            ]);
        }

        if (! $token) {
            return response()->json([
                'status' => false,
                'message' => 'بيانات الدخول غير صحيحة (Invalid credentials).',
            ], 401);
        }

        /** @var JWTGuard $jwtGuard */
        $jwtGuard = Auth::guard('captain_order');
        $user = $jwtGuard->user();

        if ($user && ! $user->status) {
            $jwtGuard->logout();

            return response()->json([
                'status' => false,
                'message' => 'الحساب معطل حالياً (Account is inactive).',
            ], 403);
        }

        $ttlMinutes = (int) $jwtGuard->factory()->getTTL();

        return response()->json([
            'status' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => now()->addMinutes($ttlMinutes)->toIso8601String(),
                'expires_in' => $ttlMinutes * 60,
                'guard' => 'captain_order',
                'user' => $user,
            ],
        ]);
    }

    /**
     * Get the authenticated captain order profile.
     */
    public function me(): JsonResponse
    {
        $user = Auth::guard('captain_order')->user();

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
     * Log out authenticated captain and invalidate the token.
     */
    public function logout(): JsonResponse
    {
        Auth::guard('captain_order')->logout();

        return response()->json([
            'status' => true,
            'message' => 'تم تسجيل الخروج بنجاح (Successfully logged out).',
        ]);
    }

    /**
     * Refresh current JWT token for captain.
     */
    public function refresh(): JsonResponse
    {
        /** @var JWTGuard $jwtGuard */
        $jwtGuard = Auth::guard('captain_order');

        try {
            $token = $jwtGuard->refresh();
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'فشل تجديد التوكن (Token refresh failed).',
            ], 401);
        }

        $ttlMinutes = (int) $jwtGuard->factory()->getTTL();

        return response()->json([
            'status' => true,
            'message' => 'تم تجديد التوكن بنجاح',
            'data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => now()->addMinutes($ttlMinutes)->toIso8601String(),
                'expires_in' => $ttlMinutes * 60,
                'guard' => 'captain_order',
                'user' => $jwtGuard->user(),
            ],
        ]);
    }
}
