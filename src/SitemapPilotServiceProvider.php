<?php

namespace SitemapPilot\Laravel;

use Illuminate\Support\ServiceProvider;

class SitemapPilotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/sitemappilot.php',
            'sitemappilot'
        );

        $this->app->singleton(SitemapPilotClient::class, function ($app) {
            return new SitemapPilotClient(
                apiKey: config('sitemappilot.api_key'),
                propertyId: config('sitemappilot.property_id') ? (int) config('sitemappilot.property_id') : null,
                baseUrl: config('sitemappilot.base_url', 'https://sitemappilot.com/api/v1'),
                timeout: (int) config('sitemappilot.timeout', 10),
            );
        });

        $this->app->alias(SitemapPilotClient::class, 'sitemappilot');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sitemappilot.php' => config_path('sitemappilot.php'),
            ], 'sitemappilot-config');

            $this->commands([
                Commands\PingCommand::class,
                Commands\GenerateCommand::class,
                Commands\SubmitCommand::class,
                Commands\StatusCommand::class,
            ]);
        }
    }
}
