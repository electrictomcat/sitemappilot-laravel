<?php

namespace SitemapPilot\Laravel\Tests;

use Illuminate\Database\Eloquent\Model;
use SitemapPilot\Laravel\Traits\AutoPingsSitemap;

class TestPingableModel extends Model
{
    use AutoPingsSitemap;

    protected $guarded = [];

    public function getSitemapUrl(): string
    {
        return "https://example.com/articles/{$this->slug}";
    }
}
