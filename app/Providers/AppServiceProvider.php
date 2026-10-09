<?php

namespace App\Providers;

use App\Http\Resources\ReadingResource;
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
    }
}
