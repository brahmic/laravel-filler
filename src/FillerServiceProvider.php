<?php

namespace Brahmic\Filler;

use Brahmic\Filler\Contracts\KeyGeneratorInterface;
use Illuminate\Support\ServiceProvider;

class FillerServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/filler.php', 'filler');

        $this->app->singleton(KeyGeneratorInterface::class, $this->app['config']['filler.key_generator']);
        $this->app->singleton(UnitOfWork::class);
        $this->app->singleton(IdentityMap::class);
        $this->app->singleton(Filler::class);
    }

    public function boot()
    {
        $this->publishes([__DIR__ . '/../config/filler.php' => config_path('brahmic.filler.php')], 'filler');
    }
}
