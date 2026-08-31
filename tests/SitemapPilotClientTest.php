<?php

namespace SitemapPilot\Laravel\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SitemapPilot\Laravel\Exceptions\AuthenticationException;
use SitemapPilot\Laravel\Exceptions\AuthorizationException;
use SitemapPilot\Laravel\Exceptions\ConfigurationException;
use SitemapPilot\Laravel\Exceptions\ConnectionFailedException;
use SitemapPilot\Laravel\Exceptions\RateLimitException;
use SitemapPilot\Laravel\Exceptions\SitemapPilotException;
use SitemapPilot\Laravel\Exceptions\ValidationException;
use SitemapPilot\Laravel\SitemapPilotClient;

class SitemapPilotClientTest extends TestCase
{
    private const BASE = 'https://sitemappilot.com/api/v1';

    private function client(): SitemapPilotClient
    {
        return new SitemapPilotClient('sp_live_testing_token', 42, self::BASE);
    }

    public function test_it_calls_the_four_documented_endpoints(): void
    {
        Http::fake([
            self::BASE.'/properties/42/generate' => Http::response(['status' => 'queued'], 202),
            self::BASE.'/properties/42/submit' => Http::response(['status' => 'submitted'], 200),
            self::BASE.'/properties/42/ping-urls' => Http::response(['status' => 'queued'], 200),
            self::BASE.'/properties/42/status' => Http::response(['host' => 'example.com'], 200),
        ]);

        $client = $this->client();

        $this->assertSame('queued', $client->generate()['status']);
        $this->assertSame('submitted', $client->submit()['status']);
        $this->assertSame('queued', $client->pingUrls('https://example.com/a')['status']);
        $this->assertSame('example.com', $client->status()['host']);

        Http::assertSentCount(4);
    }

    public function test_it_sends_the_bearer_token_and_a_versioned_user_agent(): void
    {
        Http::fake([self::BASE.'/*' => Http::response([], 200)]);

        $this->client()->status();

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('Authorization', 'Bearer sp_live_testing_token')
                && $request->hasHeader('User-Agent', 'SitemapPilot-Laravel-SDK/'.SitemapPilotClient::VERSION);
        });
    }

    public function test_a_rate_limited_response_carries_the_retry_after_header(): void
    {
        Http::fake([
            self::BASE.'/*' => Http::response(
                ['error' => 'Too Many Requests', 'message' => 'API rate limit exceeded.'],
                429,
                ['Retry-After' => '37'],
            ),
        ]);

        try {
            $this->client()->generate();
            $this->fail('A 429 must not be swallowed.');
        } catch (RateLimitException $e) {
            $this->assertSame(429, $e->status());
            $this->assertSame(37, $e->retryAfter());
            $this->assertSame('Too Many Requests', $e->payload()['error']);
        }
    }

    public function test_a_validation_failure_exposes_the_field_errors(): void
    {
        Http::fake([
            self::BASE.'/*' => Http::response([
                'message' => 'The urls.0 field must be a URL on example.com.',
                'errors' => ['urls.0' => ['The urls.0 field must be a URL on example.com.']],
            ], 422),
        ]);

        try {
            $this->client()->pingUrls('https://elsewhere.test/page');
            $this->fail('A 422 must raise.');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->status());
            $this->assertArrayHasKey('urls.0', $e->errors());
        }
    }

    public function test_401_and_403_raise_distinct_exceptions(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['message' => 'Invalid or revoked API token.'], 401)]);
        $this->assertThrows(fn () => $this->client()->status(), AuthenticationException::class);

        Http::fake([self::BASE.'/*' => Http::response(['message' => 'No access to this property.'], 403)]);
        $this->assertThrows(fn () => $this->client()->status(), AuthorizationException::class);
    }

    public function test_every_failure_is_catchable_as_one_type(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['message' => 'boom'], 500)]);

        $this->assertThrows(fn () => $this->client()->status(), SitemapPilotException::class);
    }

    public function test_a_transport_failure_is_wrapped_and_has_no_status(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

        try {
            $this->client()->status();
            $this->fail('A transport failure must raise.');
        } catch (ConnectionFailedException $e) {
            $this->assertNull($e->status());
            $this->assertInstanceOf(ConnectionException::class, $e->getPrevious());
        }
    }

    public function test_missing_configuration_raises_before_any_request(): void
    {
        Http::fake();

        $this->assertThrows(
            fn () => (new SitemapPilotClient(null, 42, self::BASE))->generate(),
            ConfigurationException::class,
        );

        $this->assertThrows(
            fn () => (new SitemapPilotClient('sp_live_testing_token', 0, self::BASE))->generate(),
            ConfigurationException::class,
        );

        Http::assertNothingSent();
    }

    public function test_property_override_changes_only_the_clone(): void
    {
        Http::fake([self::BASE.'/*' => Http::response([], 200)]);

        $client = $this->client();
        $client->property(99)->status();
        $client->status();

        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/properties/99/status'));
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/properties/42/status'));
    }
}
