<?php

namespace SitemapPilot\Laravel;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use SitemapPilot\Laravel\Exceptions\ConfigurationException;
use SitemapPilot\Laravel\Exceptions\ConnectionFailedException;
use SitemapPilot\Laravel\Exceptions\SitemapPilotException;

class SitemapPilotClient
{
    /**
     * The package version, sent on the User-Agent so a support request can be
     * tied to a release. Bump it in the same commit as the git tag.
     */
    public const VERSION = '1.0.0';

    /**
     * The four paths this package calls, relative to the configured base URL.
     *
     * They are constants rather than inline strings so that a single test can
     * hold them against the router the API actually serves. The last time
     * these were written out by hand in more than one place, the copies and
     * the routes drifted apart without anything failing.
     */
    public const PATH_GENERATE = '/properties/%d/generate';

    public const PATH_SUBMIT = '/properties/%d/submit';

    public const PATH_PING_URLS = '/properties/%d/ping-urls';

    public const PATH_STATUS = '/properties/%d/status';

    public function __construct(
        protected ?string $apiKey = null,
        protected ?int $propertyId = null,
        protected ?string $baseUrl = null,
        protected int $timeout = 10,
    ) {
        $this->apiKey = $apiKey ?? config('sitemappilot.api_key');
        $this->propertyId = $propertyId ?? (int) config('sitemappilot.property_id');
        $this->baseUrl = rtrim($baseUrl ?? config('sitemappilot.base_url', 'https://sitemappilot.com/api/v1'), '/');
        $this->timeout = $timeout ?: (int) config('sitemappilot.timeout', 10);
    }

    /**
     * Set a custom property ID for this request.
     */
    public function property(int $propertyId): self
    {
        $clone = clone $this;
        $clone->propertyId = $propertyId;

        return $clone;
    }

    /**
     * Trigger a sitemap crawl & generation in SitemapPilot Cloud.
     *
     * Answers 202 as soon as the crawl is queued; it does not wait for it.
     *
     * @return array<string, mixed>
     */
    public function generate(?int $propertyId = null): array
    {
        $id = $this->resolvePropertyId($propertyId);

        return $this->send(fn (PendingRequest $request): Response => $request->post($this->url(self::PATH_GENERATE, $id)));
    }

    /**
     * Submit the latest sitemap to Google Search Console and Bing.
     *
     * @return array<string, mixed>
     */
    public function submit(?int $propertyId = null): array
    {
        $id = $this->resolvePropertyId($propertyId);

        return $this->send(fn (PendingRequest $request): Response => $request->post($this->url(self::PATH_SUBMIT, $id)));
    }

    /**
     * Push one or multiple URLs directly to IndexNow for instant search indexing.
     *
     * Every URL has to be on the property's own host. The API rejects the whole
     * batch with a 422 if one is not, rather than silently dropping it.
     *
     * @param  string|array<int, string>  $urls
     * @return array<string, mixed>
     */
    public function pingUrls(string|array $urls, ?int $propertyId = null): array
    {
        $id = $this->resolvePropertyId($propertyId);
        $urlList = is_array($urls) ? $urls : [$urls];

        return $this->send(fn (PendingRequest $request): Response => $request->post(
            $this->url(self::PATH_PING_URLS, $id),
            ['urls' => array_values($urlList)],
        ));
    }

    /**
     * Retrieve the real-time health, crawl snapshot, and GSC error status for the property.
     *
     * @return array<string, mixed>
     */
    public function status(?int $propertyId = null): array
    {
        $id = $this->resolvePropertyId($propertyId);

        return $this->send(fn (PendingRequest $request): Response => $request->get($this->url(self::PATH_STATUS, $id)));
    }

    /**
     * The absolute URL for one endpoint against the configured base URL.
     */
    public function url(string $path, int $propertyId): string
    {
        return $this->baseUrl.sprintf($path, $propertyId);
    }

    protected function http(): PendingRequest
    {
        return Http::withToken($this->apiKey)
            ->acceptJson()
            ->timeout($this->timeout)
            ->withUserAgent('SitemapPilot-Laravel-SDK/'.self::VERSION);
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     *
     * @throws SitemapPilotException
     */
    protected function send(callable $call): array
    {
        try {
            $response = $call($this->http());
        } catch (ConnectionException $e) {
            // Guzzle never got an answer, so there is no status and no body to
            // report. Wrapping it keeps one catchable hierarchy for callers
            // without pretending a transport failure is an API refusal.
            throw new ConnectionFailedException(
                'SitemapPilot API could not be reached: '.$e->getMessage(),
                previous: $e,
            );
        }

        return $this->handleResponse($response);
    }

    /**
     * @throws ConfigurationException
     */
    protected function resolvePropertyId(?int $propertyId): int
    {
        $id = $propertyId ?? $this->propertyId;

        $this->ensureConfigured($id);

        return (int) $id;
    }

    /**
     * @throws ConfigurationException
     */
    protected function ensureConfigured(?int $propertyId): void
    {
        if (empty($this->apiKey)) {
            throw new ConfigurationException('SitemapPilot API Key is missing. Set SITEMAPPILOT_API_KEY in your .env file.');
        }

        if (empty($propertyId)) {
            throw new ConfigurationException('SitemapPilot Property ID is missing. Set SITEMAPPILOT_PROPERTY_ID in your .env file or pass a property ID.');
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SitemapPilotException
     */
    protected function handleResponse(Response $response): array
    {
        if ($response->failed()) {
            throw SitemapPilotException::fromResponse($response);
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }
}
