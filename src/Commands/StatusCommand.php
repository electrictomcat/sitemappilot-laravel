<?php

namespace SitemapPilot\Laravel\Commands;

use Illuminate\Console\Command;
use SitemapPilot\Laravel\Facades\SitemapPilot;
use Throwable;

class StatusCommand extends Command
{
    protected $signature = 'sitemappilot:status
                            {--property= : Optional custom Property ID}';

    protected $description = 'Query real-time health, drift hashes, and GSC status from SitemapPilot';

    public function handle(): int
    {
        $propertyId = $this->option('property') ? (int) $this->option('property') : null;

        $this->info('Fetching property health and Google Search Console snapshot...');

        try {
            $data = SitemapPilot::status($propertyId);

            $this->components->twoColumnDetail('Property ID', (string) ($data['property_id'] ?? 'N/A'));
            $this->components->twoColumnDetail('Host', (string) ($data['host'] ?? 'N/A'));
            $this->components->twoColumnDetail('Sitemap Hostname', (string) ($data['sitemap_hostname'] ?? 'N/A'));
            $this->components->twoColumnDetail('Cloudflare Status', (string) ($data['cloudflare_status'] ?? 'N/A'));

            if (! empty($data['latest_generation'])) {
                $this->newLine();
                $this->components->info('Latest Sitemap Generation:');
                $this->components->twoColumnDetail('Generation Status', $data['latest_generation']['status'] ?? 'N/A');
                $this->components->twoColumnDetail('URLs Count', (string) ($data['latest_generation']['url_count'] ?? '0'));
                $this->components->twoColumnDetail('SHA-256 Hash', substr($data['latest_generation']['content_hash'] ?? 'N/A', 0, 16).'...');
                $this->components->twoColumnDetail('Auto-Submit Decision', $data['latest_generation']['auto_submit_decision'] ?? 'N/A');
            }

            if (! empty($data['gsc_status'])) {
                $this->newLine();
                $this->components->info('Google Search Console Snapshot:');
                $this->components->twoColumnDetail('Last Downloaded', $data['gsc_status']['last_downloaded'] ?? 'Never');
                $this->components->twoColumnDetail('Pending Status', ($data['gsc_status']['is_pending'] ?? false) ? 'Yes' : 'No');
                $this->components->twoColumnDetail('Indexing Errors', (string) ($data['gsc_status']['errors_count'] ?? '0'));
                $this->components->twoColumnDetail('Warnings', (string) ($data['gsc_status']['warnings_count'] ?? '0'));
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->components->error("Failed to fetch property status: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
