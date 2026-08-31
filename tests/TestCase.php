<?php

namespace SitemapPilot\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SitemapPilot\Laravel\SitemapPilotServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [SitemapPilotServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('sitemappilot.api_key', 'sp_live_testing_token');
        $app['config']->set('sitemappilot.property_id', 42);
        $app['config']->set('sitemappilot.base_url', 'https://sitemappilot.com/api/v1');
    }
}
