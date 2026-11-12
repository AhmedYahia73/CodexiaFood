<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Services\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $module = null, ?string $action = null): Response
    {
        $admin = null;

        if (Auth::guard('admin')->check()) {
            $admin = Auth::guard('admin')->user();
        }

        if (! $admin && $request->user() instanceof Admin) {
            $admin = $request->user();
        }

        if (! $admin) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Rule: If the admin has no role_id, they have full access.
        if ($admin->role_id === null) {
            return $next($request);
        }

        // Auto-detect module and action from route if not explicitly supplied
        if (! $module || ! $action) {
            $route = $request->route();
            $actionName = $route ? $route->getActionName() : '';

            if (is_string($actionName) && str_contains($actionName, '@')) {
                [$controllerClass, $methodName] = explode('@', $actionName, 2);
                [$resolvedModule, $resolvedAction] = PermissionService::resolveModuleAndAction(
                    $controllerClass,
                    $methodName,
                    $request->method()
                );

                $module = $module ?: $resolvedModule;
                $action = $action ?: $resolvedAction;
            } else {
                $module = $module ?: 'general';
                $action = $action ?: PermissionService::resolveActionFromMethod(null, $request->method());
            }
        }

        if (! $admin->hasPermission($module, $action)) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden: You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
