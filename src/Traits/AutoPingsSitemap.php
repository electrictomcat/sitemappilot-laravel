<?php

namespace SitemapPilot\Laravel\Traits;

use Illuminate\Database\Eloquent\Model;
use SitemapPilot\Laravel\Jobs\SendSitemapPingJob;

trait AutoPingsSitemap
{
    public static function bootAutoPingsSitemap(): void
    {
        static::saved(function (Model $model) {
            /** @var self $model */
            if ($model->shouldPingSitemap()) {
                $model->dispatchSitemapPing();
            }
        });

        static::deleted(function (Model $model) {
            /** @var self $model */
            if ($model->shouldPingSitemap()) {
                $model->dispatchSitemapPing();
            }
        });
    }

    /**
     * Determine if this model event should trigger a search engine ping.
     */
    public function shouldPingSitemap(): bool
    {
        return ! empty($this->getSitemapUrl());
    }

    /**
     * Get the public URL for this model to submit to search engines.
     * Override this method in your Eloquent model.
     */
    public function getSitemapUrl(): ?string
    {
        return $this->url ?? $this->sitemap_url ?? null;
    }

    /**
     * Optional custom Property ID for this specific model type.
     */
    public function getSitemapPropertyId(): ?int
    {
        return null;
    }

    /**
     * Dispatch the background ping job.
     */
    public function dispatchSitemapPing(): void
    {
        $url = $this->getSitemapUrl();

        if (! $url) {
            return;
        }

        $propertyId = $this->getSitemapPropertyId();
        $job = new SendSitemapPingJob([$url], $propertyId);

        $connection = config('sitemappilot.queue.connection');
        $queue = config('sitemappilot.queue.queue');

        if ($connection) {
            $job->onConnection($connection);
        }

        if ($queue) {
            $job->onQueue($queue);
        }

        dispatch($job);
    }
}
