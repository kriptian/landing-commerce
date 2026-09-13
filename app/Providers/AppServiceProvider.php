<?php

namespace App\Providers;

use App\Models\Store;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        if (app()->isProduction()) {
            Vite::useHotFile(storage_path('framework/vite.hot'));
        }

        RateLimiter::for('store-registration', function (Request $request) {
            $email = Str::lower((string) $request->input('email', 'unknown'));

            return [
                Limit::perMinute(10)->by('store-registration-ip:'.$request->ip()),
                Limit::perHour(3)->by('store-registration:'.hash('sha256', $email).':'.$request->ip()),
            ];
        });

        RateLimiter::for('customer-registration', function (Request $request) {
            return $this->customerAuthLimits($request, 'registration', 5);
        });

        RateLimiter::for('customer-login', function (Request $request) {
            return $this->customerAuthLimits($request, 'login', 8);
        });

        RateLimiter::for('customer-cart', function (Request $request) {
            return [
                Limit::perMinute(60)->by('cart-ip:'.$request->ip()),
                Limit::perMinute(30)->by('cart-product:'.$request->ip().':'.$request->input('product_id', 'unknown')),
            ];
        });

        RateLimiter::for('customer-checkout', function (Request $request) {
            return $this->storeLimits($request, 'checkout', 10);
        });

        RateLimiter::for('checkout-coupon', function (Request $request) {
            return $this->storeLimits($request, 'coupon', 20);
        });

        RateLimiter::for('deployment', function (Request $request) {
            return Limit::perMinutes(10, 5)->by('deployment:'.($request->user('web')?->id ?? $request->ip()));
        });

        RateLimiter::for('deployment-status', function (Request $request) {
            return Limit::perMinute(120)->by('deployment-status:'.($request->user('web')?->id ?? $request->ip()));
        });

        RateLimiter::for('product-ai', function (Request $request) {
            $user = $request->user('web');
            $storeId = $user?->store_id ?? 'unknown';

            return [
                Limit::perMinute(2)->by('product-ai-user:'.($user?->id ?? $request->ip())),
                Limit::perDay(20)->by('product-ai-store:'.$storeId),
            ];
        });

        Vite::prefetch(concurrency: 3);
    }

    private function customerAuthLimits(Request $request, string $operation, int $attempts): array
    {
        $store = $request->route('store');
        $storeKey = $store instanceof Store ? $store->getKey() : 'unknown';
        $email = Str::lower((string) $request->input('email', 'unknown'));

        return [
            Limit::perMinute(30)->by('customer-'.$operation.'-ip:'.$request->ip()),
            Limit::perMinute($attempts)->by('customer-'.$operation.':'.$storeKey.':'.hash('sha256', $email).':'.$request->ip()),
        ];
    }

    private function storeLimits(Request $request, string $operation, int $attempts): array
    {
        $store = $request->route('store');
        $storeKey = $store instanceof Store ? $store->getKey() : 'unknown';

        return [
            Limit::perMinute($attempts * 2)->by($operation.'-ip:'.$request->ip()),
            Limit::perMinute($attempts)->by($operation.'-store:'.$storeKey.':'.$request->ip()),
        ];
    }
}
