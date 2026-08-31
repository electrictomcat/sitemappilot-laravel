<?php

namespace SitemapPilot\Laravel\Commands;

use Illuminate\Console\Command;
use SitemapPilot\Laravel\Facades\SitemapPilot;
use Throwable;

class PingCommand extends Command
{
    protected $signature = 'sitemappilot:ping
                            {urls* : One or more URLs to ping to search engines}
                            {--property= : Optional custom Property ID}';

    protected $description = 'Instantly ping URLs to the IndexNow search engine network (Bing, Yandex, etc.)';

    public function handle(): int
    {
        $urls = (array) $this->argument('urls');
        $propertyId = $this->option('property') ? (int) $this->option('property') : null;

        $this->info('Sending '.count($urls).' URL(s) to SitemapPilot / IndexNow...');

        try {
            $response = SitemapPilot::pingUrls($urls, $propertyId);

            $this->components->info('Successfully dispatched IndexNow ping for '.count($urls).' URL(s).');
            $this->table(['Key', 'Value'], [
                ['Status', $response['status'] ?? 'queued'],
                ['Engine', $response['engine'] ?? 'indexnow'],
                ['URLs Submitted', $response['urls_submitted'] ?? count($urls)],
                ['Timestamp', $response['timestamp'] ?? now()->toIso8601String()],
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error("Failed to ping URLs: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
