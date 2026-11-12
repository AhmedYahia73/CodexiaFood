<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderCart;
use App\Observers\OrderCartObserver;
use App\Observers\OrderObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewApiDocs', function ($user = null) {
            return true;
        });

        Gate::before(function ($user, string $ability, array $arguments = []) {
            if ($ability === 'viewApiDocs') {
                return true;
            }

            if ($user instanceof Admin) {
                if ($user->role_id === null) {
                    return true;
                }

                $module = null;
                $action = null;

                if (! empty($arguments) && is_string($arguments[0])) {
                    $action = $ability;
                    $module = $arguments[0];
                } elseif (str_contains($ability, '.')) {
                    [$module, $action] = explode('.', $ability, 2);
                } elseif (str_contains($ability, ':')) {
                    [$module, $action] = explode(':', $ability, 2);
                } elseif (str_contains($ability, ',')) {
                    [$module, $action] = explode(',', $ability, 2);
                } else {
                    $action = $ability;
                    $module = $arguments[0] ?? null;
                }

                if ($module && $action) {
                    return $user->hasPermission($module, $action);
                }
            }

            return null;
        });

        Order::observe(OrderObserver::class);
        OrderCart::observe(OrderCartObserver::class);
    }
}
