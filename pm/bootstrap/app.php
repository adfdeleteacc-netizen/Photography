<?php

use App\Exceptions\MarketplaceException;
use App\Http\Middleware\{EnsureActiveSubscription, EnsureKycVerified, EnsureUserIsActive};
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\{Exceptions, Middleware};
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\{PermissionMiddleware, RoleMiddleware, RoleOrPermissionMiddleware};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        then: function () {
            // Role-scoped route files (spec: admin.php, customer.php, photographer.php, studio.php)
            Route::middleware(['api', 'auth:sanctum', 'verified', EnsureUserIsActive::class, 'throttle:api'])->prefix('api')->group(function () {
                Route::prefix('admin')->middleware('role:admin')->group(base_path('routes/admin.php'));
                Route::middleware('role:customer')->group(base_path('routes/customer.php'));
                Route::middleware('role:photographer|studio')->group(base_path('routes/photographer.php'));
                Route::prefix('studio')->middleware('role:studio')->group(base_path('routes/studio.php'));
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RoleMiddleware::class, 'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'subscribed' => EnsureActiveSubscription::class, 'kyc' => EnsureKycVerified::class,
        ]);
        $middleware->statefulApi();   // Sanctum cookie auth for the Blade/Alpine dashboards
        // Razorpay webhooks are server-to-server (no CSRF cookie); signature verified in the controller.
        $middleware->validateCsrfTokens(except: ['api/webhooks/*']);
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('marketplace:maintain')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
        $schedule->command('marketplace:settle daily')->dailyAt('02:00')->onOneServer();
        $schedule->command('marketplace:settle weekly')->weeklyOn(1, '03:00')->onOneServer();
        $schedule->command('marketplace:detect-fraud')->hourly()->onOneServer();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (MarketplaceException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => $e->errorCode], $e->status);
        });
    })->create();
