<?php

namespace SitemapPilot\Laravel\Tests;

use Illuminate\Support\Facades\Queue;
use SitemapPilot\Laravel\Facades\SitemapPilot;
use SitemapPilot\Laravel\Jobs\SendSitemapPingJob;

class SitemapPilotFakeTest extends TestCase
{
    public function test_the_fake_records_what_the_application_sent(): void
    {
        $fake = SitemapPilot::fake();

        $fake->assertNothingSent();

        SitemapPilot::pingUrls(['https://example.com/blog/new-post']);
        SitemapPilot::generate();
        SitemapPilot::submit();

        $fake->assertPinged('https://example.com/blog/new-post');
        $fake->assertNotPinged('https://example.com/not-touched');
        $fake->assertGenerated(42);
        $fake->assertSubmitted(42);
    }

    public function test_the_fake_returns_canned_responses(): void
    {
        SitemapPilot::fake(['status' => ['host' => 'canned.test']]);

        $this->assertSame('canned.test', SitemapPilot::status()['host']);
    }

    public function test_the_queued_job_pings_through_the_facade(): void
    {
        $fake = SitemapPilot::fake();

        (new SendSitemapPingJob(['https://example.com/queued'], 7))->handle();

        $fake->assertPinged('https://example.com/queued', 7);
    }

    public function test_the_ping_job_is_queued_on_the_configured_connection(): void
    {
        Queue::fake();

        config([
            'sitemappilot.queue.connection' => 'redis',
            'sitemappilot.queue.queue' => 'sitemaps',
        ]);

        $model = new TestPingableModel(['slug' => 'hello-world']);
        $model->dispatchSitemapPing();

        Queue::assertPushed(SendSitemapPingJob::class, function (SendSitemapPingJob $job): bool {
            return $job->urls === ['https://example.com/articles/hello-world']
                && $job->connection === 'redis'
                && $job->queue === 'sitemaps';
        });
    }

    public function test_the_artisan_commands_run_against_the_fake(): void
    {
        SitemapPilot::fake();

        $this->artisan('sitemappilot:ping', ['urls' => ['https://example.com/cli']])->assertSuccessful();
        $this->artisan('sitemappilot:generate')->assertSuccessful();
        $this->artisan('sitemappilot:submit')->assertSuccessful();
        $this->artisan('sitemappilot:status')->assertSuccessful();
    }
}
