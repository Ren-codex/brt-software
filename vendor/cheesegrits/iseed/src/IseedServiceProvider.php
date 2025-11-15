<?php

namespace Cheesegrits\Iseed;

use Cheesegrits\Iseed\Commands\IseedAllCommand;
use Cheesegrits\Iseed\Commands\IseedCommand;
use Illuminate\Support\ServiceProvider;

class IseedServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [
                    __DIR__.'/config/config.php' => config_path('iseed.php'),
                ], 'config'
            );
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config/config.php', 'iseed');

        $this->app->singleton(
            'iseed', function ($app) {
                return new Iseed;
            }
        );

        $this->app->booting(
            function () {
                $loader = \Illuminate\Foundation\AliasLoader::getInstance();
                $loader->alias('Iseed', 'Cheesgrits\Iseed\Facades\Iseed');
            }
        );

        $this->app->singleton(
            'command.iseed', function ($app) {
                return new IseedCommand;
            }
        );

        $this->commands('command.iseed');


        $this->app->singleton(
            'command.iseed:all', function ($app) {
                return new IseedAllCommand;
            }
        );

        $this->commands('command.iseed:all');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('iseed');
    }
}
