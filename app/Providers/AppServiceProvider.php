<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, RateLimiter};
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Stateless collaborators can be shared across the request.
        $this->app->singleton(\App\Services\Payment\RazorpayClient::class);
        $this->app->singleton(\App\Services\Payment\PricingService::class);
    }

    public function boot(): void
    {
        // Admin role bypasses nothing implicitly except via explicit policy `before` hooks.
        // Surface N+1 queries in logs (non-fatal) so "always eager load" is enforced without breaking requests.
        \Illuminate\Database\Eloquent\Model::preventLazyLoading(! $this->app->isProduction());
        \Illuminate\Database\Eloquent\Model::handleLazyLoadingViolationUsing(
            fn ($model, $relation) => logger()->warning('N+1 lazy load', ['model' => $model::class, 'relation' => $relation])
        );

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(120)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('auth', fn (Request $r) => [Limit::perMinute(5)->by($r->ip() . '|' . $r->input('email')), Limit::perMinute(20)->by($r->ip())]);
        RateLimiter::for('payments', fn (Request $r) => Limit::perMinute(20)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('uploads', fn (Request $r) => Limit::perMinute(30)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('webhooks', fn (Request $r) => Limit::perMinute(300)->by($r->ip()));
    }
}
