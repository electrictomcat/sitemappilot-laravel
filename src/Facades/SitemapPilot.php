<?php

namespace SitemapPilot\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use SitemapPilot\Laravel\SitemapPilotClient;
use SitemapPilot\Laravel\Testing\SitemapPilotFake;

/**
 * @method static \SitemapPilot\Laravel\SitemapPilotClient property(int $propertyId)
 * @method static array generate(?int $propertyId = null)
 * @method static array submit(?int $propertyId = null)
 * @method static array pingUrls(string|array $urls, ?int $propertyId = null)
 * @method static array status(?int $propertyId = null)
 *
 * @see SitemapPilotClient
 */
class SitemapPilot extends Facade
{
    /**
     * Replace the bound instance with a fake for testing.
     *
     * @param  array<string, mixed>  $responses
     */
    public static function fake(array $responses = []): SitemapPilotFake
    {
        static::swap($fake = new SitemapPilotFake($responses));

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return SitemapPilotClient::class;
    }
}
