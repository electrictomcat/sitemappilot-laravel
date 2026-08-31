<?php

namespace SitemapPilot\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SitemapPilot\Laravel\Facades\SitemapPilot;
use Throwable;

class SendSitemapPingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * @param  array<int, string>  $urls
     */
    public function __construct(
        public array $urls,
        public ?int $propertyId = null,
    ) {}

    public function handle(): void
    {
        if (empty($this->urls)) {
            return;
        }

        try {
            SitemapPilot::pingUrls($this->urls, $this->propertyId);
        } catch (Throwable $e) {
            Log::warning("SitemapPilot ping job failed: {$e->getMessage()}", [
                'urls' => $this->urls,
                'property_id' => $this->propertyId,
            ]);

            throw $e;
        }
    }
}
