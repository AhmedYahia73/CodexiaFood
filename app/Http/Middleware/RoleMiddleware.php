<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = null;

        $guards = ['admin', 'cashier_man', 'branch', 'kitchen', 'api', 'web'];
        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                break;
            }
        }

        if (! $user) {
            $user = $request->user();
        }

        if (! $user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $userRole = $user->role ?? null;

        if (! $userRole || (! in_array($userRole, $roles) && ! in_array('*', $roles))) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden: Invalid role access.',
            ], 403);
        }

        return $next($request);
    }
}
