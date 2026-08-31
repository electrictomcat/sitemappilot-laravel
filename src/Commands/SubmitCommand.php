<?php

namespace SitemapPilot\Laravel\Commands;

use Illuminate\Console\Command;
use SitemapPilot\Laravel\Facades\SitemapPilot;
use Throwable;

class SubmitCommand extends Command
{
    protected $signature = 'sitemappilot:submit
                            {--property= : Optional custom Property ID}';

    protected $description = 'Submit the latest sitemap directly to Google Search Console and Bing APIs';

    public function handle(): int
    {
        $propertyId = $this->option('property') ? (int) $this->option('property') : null;

        $this->info('Submitting sitemap to Google Search Console & Bing...');

        try {
            $response = SitemapPilot::submit($propertyId);

            $this->components->info('Sitemap successfully submitted to search engine APIs.');
            $this->table(['Key', 'Value'], [
                ['Status', $response['status'] ?? 'submitted'],
                ['Targets', implode(', ', $response['targets'] ?? ['google', 'bing'])],
                ['Sitemap URL', $response['sitemap_url'] ?? 'N/A'],
                ['Submitted At', $response['submitted_at'] ?? now()->toIso8601String()],
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error("Failed to submit sitemap: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
