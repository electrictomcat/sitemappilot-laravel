<?php

namespace SitemapPilot\Laravel\Tests;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @return array<string, array{0: int, 1: class-string}>
     */
    public static function refusalProvider(): array
    {
        return [
            '401 the token is wrong' => [401, AuthenticationException::class],
            '403 the token belongs to another workspace' => [403, AuthorizationException::class],
        ];
    }

    /**
     * One status per test case, deliberately.
     *
     * This was one method that faked a 401, asserted, then faked a 403 and
     * asserted again - and the second half could never pass. Factory::fake()
     * MERGES its stubs into the ones already registered rather than replacing
     * them (Illuminate\Http\Client\Factory::fake -> stubUrl), and the first
     * stub whose pattern matches answers. Both faked the same
     * `BASE/*` pattern, so the 401 kept answering and the 403 case asserted
     * against a 401 response. A data provider gives each status a fresh
     * application, and a fresh factory with it.
     *
     * @param  class-string  $expected
     */
    #[DataProvider('refusalProvider')]
    public function test_each_api_refusal_raises_its_own_exception(int $status, string $expected): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['message' => 'nope'], $status)]);

        try {
            $this->client()->status();
            $this->fail("A {$status} must raise.");
        } catch (SitemapPilotException $e) {
            $this->assertInstanceOf($expected, $e);
            $this->assertSame($status, $e->status());
        }
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

    /**
     * A null argument is NOT the unconfigured case: the constructor falls
     * back to config for everything it is not given, and TestCase's
     * environment configures a key. So this asserted a raise it could not
     * see - the client was fully configured and sent the request instead,
     * which is why the package's own suite failed the first time it was run.
     * The config is emptied here, which is what a consumer's application
     * looks like before SITEMAPPILOT_API_KEY reaches its .env.
     */
    public function test_missing_configuration_raises_before_any_request(): void
    {
        Http::fake();

        config(['sitemappilot.api_key' => null, 'sitemappilot.property_id' => null]);

        $this->assertThrows(
            fn () => (new SitemapPilotClient(null, 42, self::BASE))->generate(),
            ConfigurationException::class,
        );

        $this->assertThrows(
            fn () => (new SitemapPilotClient('sp_live_testing_token', null, self::BASE))->submit(),
            ConfigurationException::class,
        );

        $this->assertThrows(
            fn () => (new SitemapPilotClient('sp_live_testing_token', 0, self::BASE))->status(),
            ConfigurationException::class,
        );

        Http::assertNothingSent();
    }

    /**
     * The other side of the fallback the test above has to work around, and
     * the reason it is not simply deleted: an application that configures the
     * package in .env and then resolves the client with no arguments has to
     * reach its own property with its own token.
     */
    public function test_a_client_given_nothing_uses_the_configured_key_and_property(): void
    {
        Http::fake([self::BASE.'/*' => Http::response([], 200)]);

        (new SitemapPilotClient)->status();

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('Authorization', 'Bearer sp_live_testing_token')
                && str_ends_with($request->url(), '/properties/42/status');
        });
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
