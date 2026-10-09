<?php

namespace App\Providers;

use App\Http\Resources\ReadingResource;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // Resource responses carry the reading fields at the top level, not
        // nested under a "data" envelope. The firmware parses them directly;
        // the 409 response already mixes its own keys beside the reading.
        ReadingResource::withoutWrapping();

        // Ingest is limited per device, not per IP: a single IP fronting many
        // devices (a NAT'd office, a gateway hub) must not choke them all.
        // The device is attached to the request by the device.auth middleware,
        // which sits ahead of throttle on the route.
        RateLimiter::for('device-ingest', function (Request $request) {
            $device = $request->attributes->get('device');

            return Limit::perMinute(30)->by((string) ($device?->id ?? $request->ip()));
        });
    }
}
