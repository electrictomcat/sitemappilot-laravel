<?php

namespace SitemapPilot\Laravel\Commands;

use Illuminate\Console\Command;
use SitemapPilot\Laravel\Facades\SitemapPilot;
use Throwable;

class GenerateCommand extends Command
{
    protected $signature = 'sitemappilot:generate
                            {--property= : Optional custom Property ID}';

    protected $description = 'Trigger a cloud sitemap crawl & drift detection in SitemapPilot';

    public function handle(): int
    {
        $propertyId = $this->option('property') ? (int) $this->option('property') : null;

        $this->info('Triggering cloud sitemap generation...');

        try {
            $response = SitemapPilot::generate($propertyId);

            $this->components->info($response['message'] ?? 'Sitemap generation successfully dispatched.');
            $this->table(['Key', 'Value'], [
                ['Status', $response['status'] ?? 'queued'],
                ['Dispatched At', $response['dispatched_at'] ?? now()->toIso8601String()],
            ]);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error("Failed to trigger sitemap generation: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
