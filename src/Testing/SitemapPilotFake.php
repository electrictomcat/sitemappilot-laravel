<?php

namespace SitemapPilot\Laravel\Testing;

use PHPUnit\Framework\Assert as PHPUnit;
use SitemapPilot\Laravel\SitemapPilotClient;

class SitemapPilotFake extends SitemapPilotClient
{
    /** @var array<int, array{urls: array<int, string>, property_id: ?int}> */
    protected array $pings = [];

    /** @var array<int, array{property_id: ?int}> */
    protected array $generations = [];

    /** @var array<int, array{property_id: ?int}> */
    protected array $submissions = [];

    public function __construct(
        protected array $responses = []
    ) {
        $propertyId = config('sitemappilot.property_id') ? (int) config('sitemappilot.property_id') : 1;
        parent::__construct('fake-api-key', $propertyId, 'https://fake-api.sitemappilot.com');
    }

    public function generate(?int $propertyId = null): array
    {
        $id = $propertyId ?? $this->propertyId;
        $this->generations[] = ['property_id' => $id];

        return $this->responses['generate'] ?? [
            'status' => 'queued',
            'message' => 'Sitemap generation dispatched (faked).',
            'dispatched_at' => now()->toIso8601String(),
        ];
    }

    public function submit(?int $propertyId = null): array
    {
        $id = $propertyId ?? $this->propertyId;
        $this->submissions[] = ['property_id' => $id];

        return $this->responses['submit'] ?? [
            'status' => 'submitted',
            'targets' => ['google', 'bing'],
            'submitted_at' => now()->toIso8601String(),
        ];
    }

    public function pingUrls(string|array $urls, ?int $propertyId = null): array
    {
        $urlList = is_array($urls) ? $urls : [$urls];
        $id = $propertyId ?? $this->propertyId;
        $this->pings[] = [
            'urls' => array_values($urlList),
            'property_id' => $id,
        ];

        return $this->responses['ping_urls'] ?? [
            'status' => 'queued',
            'engine' => 'indexnow',
            'urls_submitted' => count($urlList),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function status(?int $propertyId = null): array
    {
        $id = $propertyId ?? $this->propertyId;

        return $this->responses['status'] ?? [
            'property_id' => $id,
            'host' => 'example.com',
            'cloudflare_status' => 'active',
            'latest_generation' => ['url_count' => 100],
            'gsc_status' => ['errors_count' => 0],
        ];
    }

    public function assertPinged(string|array|callable|null $expected = null, ?int $propertyId = null): void
    {
        PHPUnit::assertNotEmpty($this->pings, 'No URLs were pinged via SitemapPilot.');

        if ($expected === null) {
            return;
        }

        if (is_callable($expected)) {
            $matched = false;
            foreach ($this->pings as $ping) {
                if ($expected($ping['urls'], $ping['property_id'])) {
                    $matched = true;
                    break;
                }
            }
            PHPUnit::assertTrue($matched, 'The custom callback condition for assertPinged failed.');

            return;
        }

        $expectedUrls = is_array($expected) ? $expected : [$expected];
        $allPinged = [];
        foreach ($this->pings as $ping) {
            if ($propertyId !== null && $ping['property_id'] !== $propertyId) {
                continue;
            }
            $allPinged = array_merge($allPinged, $ping['urls']);
        }

        foreach ($expectedUrls as $url) {
            PHPUnit::assertContains(
                $url,
                $allPinged,
                "Expected URL [{$url}] was not pinged to search engines."
            );
        }
    }

    public function assertNotPinged(string|array $urls, ?int $propertyId = null): void
    {
        $urlList = is_array($urls) ? $urls : [$urls];
        $allPinged = [];
        foreach ($this->pings as $ping) {
            if ($propertyId !== null && $ping['property_id'] !== $propertyId) {
                continue;
            }
            $allPinged = array_merge($allPinged, $ping['urls']);
        }

        foreach ($urlList as $url) {
            PHPUnit::assertNotContains(
                $url,
                $allPinged,
                "Unexpected URL [{$url}] was pinged to search engines."
            );
        }
    }

    public function assertGenerated(?int $propertyId = null): void
    {
        PHPUnit::assertNotEmpty($this->generations, 'Sitemap generation was not triggered.');

        if ($propertyId !== null) {
            $ids = array_column($this->generations, 'property_id');
            PHPUnit::assertContains($propertyId, $ids, "Sitemap generation was not triggered for Property ID [{$propertyId}].");
        }
    }

    public function assertSubmitted(?int $propertyId = null): void
    {
        PHPUnit::assertNotEmpty($this->submissions, 'Sitemap submission was not triggered.');

        if ($propertyId !== null) {
            $ids = array_column($this->submissions, 'property_id');
            PHPUnit::assertContains($propertyId, $ids, "Sitemap submission was not triggered for Property ID [{$propertyId}].");
        }
    }

    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty($this->pings, 'Unexpected URLs were pinged.');
        PHPUnit::assertEmpty($this->generations, 'Unexpected sitemap generation was triggered.');
        PHPUnit::assertEmpty($this->submissions, 'Unexpected sitemap submission was triggered.');
    }
}
