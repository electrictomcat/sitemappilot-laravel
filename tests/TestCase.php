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

    /**
     * The configured environment a consumer's application would have.
     *
     * SitemapPilotClient falls back to these values for every constructor
     * argument it is not given, so a test about MISSING configuration cannot
     * express itself by passing null - it has to empty the config first. See
     * SitemapPilotClientTest::test_missing_configuration_raises_before_any_request.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('sitemappilot.api_key', 'sp_live_testing_token');
        $app['config']->set('sitemappilot.property_id', 42);
        $app['config']->set('sitemappilot.base_url', 'https://sitemappilot.com/api/v1');
    }
}
