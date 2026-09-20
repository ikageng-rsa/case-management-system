<?php

namespace App\Providers;

use App\Models\Matter;
use App\Models\Narration;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        /*
         * store an alia rather than the actual class name
         */
        Relation::enforceMorphMap([
            'matter' => Matter::class,
            'narration' => Narration::class,
        ]);
    }
}
