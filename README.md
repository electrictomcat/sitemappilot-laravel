# SitemapPilot Laravel SDK

Official Laravel SDK for **[SitemapPilot](https://sitemappilot.com)** — automated
Google Search Console, Bing and IndexNow delivery for your sitemaps.

Your app already knows the moment a page is published. This package tells
SitemapPilot about it, so the sitemap is regenerated and resubmitted without
anyone opening a dashboard.

- **Requires:** PHP 8.2+ · Laravel 10, 11, 12 or 13
- **License:** MIT — see [LICENSE](LICENSE)
- **Changes:** [CHANGELOG.md](CHANGELOG.md)

> ### Release status
>
> **Not on Packagist yet.** `composer require sitemappilot/laravel` does not
> resolve today: `repo.packagist.org/p2/sitemappilot/laravel.json` answers 404.
> The source repository and its `v1.0.0` tag do exist, so Composer can install
> it from VCS in the meantime — see below. This block comes out when the
> package is published; [PUBLISHING.md](PUBLISHING.md) is the order of
> operations for getting it there.

---

## Installation

Once the package is on Packagist:

```bash
composer require sitemappilot/laravel
```

Until then, install it from the source repository:

```jsonc
// composer.json
"repositories": [
    { "type": "vcs", "url": "https://github.com/electrictomcat/sitemappilot-laravel" }
],
"require": {
    "sitemappilot/laravel": "^1.0"
}
```

```bash
composer update sitemappilot/laravel
```

The service provider and the `SitemapPilot` alias are registered by package
discovery. Publish the config only if you want to edit it directly — every
value is readable from the environment:

```bash
php artisan vendor:publish --tag=sitemappilot-config
```

## Configuration

Mint a token on the **API & SDK Integrations** page of your SitemapPilot
dashboard. It is shown once, at the moment you generate it: SitemapPilot stores
only a SHA-256 hash of it and cannot show it to you again. Lose it and you
regenerate — which immediately invalidates the old one.

```ini
SITEMAPPILOT_API_KEY=sp_live_your_workspace_api_key
SITEMAPPILOT_PROPERTY_ID=12
```

| Variable | Default | Purpose |
| --- | --- | --- |
| `SITEMAPPILOT_API_KEY` | — | Workspace API token, sent as `Authorization: Bearer …` |
| `SITEMAPPILOT_PROPERTY_ID` | — | Default property for every call; overridable per call |
| `SITEMAPPILOT_BASE_URL` | `https://sitemappilot.com/api/v1` | Change this only if you run SitemapPilot yourself |
| `SITEMAPPILOT_TIMEOUT` | `10` | HTTP timeout in seconds |
| `SITEMAPPILOT_QUEUE_CONNECTION` / `SITEMAPPILOT_QUEUE_NAME` | default queue | Where `SendSitemapPingJob` is dispatched |

A token is scoped to one workspace. Calling an endpoint for a property in
another workspace answers `403`, not `404`.

---

## Usage

### 1. Ping URLs as your models change

Add `AutoPingsSitemap` to a model and tell it the public URL:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use SitemapPilot\Laravel\Traits\AutoPingsSitemap;

class Article extends Model
{
    use AutoPingsSitemap;

    public function getSitemapUrl(): ?string
    {
        return route('articles.show', $this->slug);
    }
}
```

Every `saved` and `deleted` event queues a `SendSitemapPingJob`, so nothing is
added to the request the user is waiting on. Override `shouldPingSitemap()` to
skip drafts, and `getSitemapPropertyId()` to route a model at a specific
property.

> **The URL has to be on the property's own host.** The API rejects the whole
> batch with a `422` — it does not quietly drop the strays — because IndexNow
> would refuse it anyway: the key file only exists on that host.

### 2. Ping URLs by hand

```php
use SitemapPilot\Laravel\Facades\SitemapPilot;

SitemapPilot::pingUrls([
    'https://example.com/blog/my-new-post',
    'https://example.com/pricing',
]);
```

### 3. Regenerate and submit from a deploy script

```php
SitemapPilot::generate();   // crawl the site again, hash the result
SitemapPilot::submit();     // push the current sitemap to Google and Bing
```

`generate()` returns `202` as soon as the crawl is queued; it does not wait for
it. The crawl only runs on a property whose domain ownership has been verified
in the dashboard (a DNS TXT record) — on an unverified property the refusal is
recorded against the generation instead, where you can read it in the panel.

`submit()` answers `422` when the property has no sitemap URL yet.

### 4. Read status back

```php
$status = SitemapPilot::status();
```

```php
[
    'property_id'       => 12,
    'host'              => 'example.com',
    'sitemap_hostname'  => 'sitemap.example.com',
    'cloudflare_status' => 'active',
    'latest_generation' => [
        'id' => 91, 'status' => 'success', 'finished_at' => '…',
        'url_count' => 540, 'content_hash' => '…', 'auto_submit_decision' => '…',
    ],
    'gsc_status' => [
        'fetched_at' => '…', 'last_downloaded' => '…', 'is_pending' => false,
        'errors_count' => 0, 'warnings_count' => 0, 'contents' => [...],
    ],
]
```

`latest_generation` and `gsc_status` are `null` until the property has been
generated and Search Console has reported back.

### 5. Several properties from one app

```php
SitemapPilot::property(42)->pingUrls(['https://tenant-domain.com/new-page']);
SitemapPilot::property(42)->submit();
```

`property()` returns a clone, so the configured default is left alone.

### 6. Artisan commands

```bash
php artisan sitemappilot:ping https://example.com/new-page https://example.com/pricing
php artisan sitemappilot:generate
php artisan sitemappilot:submit
php artisan sitemappilot:status
```

Each accepts `--property=` to override the configured property. They print the
API's error and exit `1` rather than throwing.

### 7. Testing

```php
use SitemapPilot\Laravel\Facades\SitemapPilot;

public function test_publishing_an_article_notifies_search_engines(): void
{
    SitemapPilot::fake();

    Article::create(['title' => 'My New Article', 'slug' => 'my-new-article']);

    SitemapPilot::assertPinged('https://example.com/articles/my-new-article');
}
```

| Assertion | |
| --- | --- |
| `assertPinged($urls\|$callback, $propertyId = null)` | URLs were pushed |
| `assertNotPinged($urls, $propertyId = null)` | URLs were not pushed |
| `assertGenerated($propertyId = null)` | A crawl was requested |
| `assertSubmitted($propertyId = null)` | A submission was requested |
| `assertNothingSent()` | Nothing at all left the app |

`SitemapPilot::fake([...])` takes canned responses keyed `generate`, `submit`,
`ping_urls` and `status`.

---

## Errors and rate limits

Every failure raises a subclass of `SitemapPilot\Laravel\Exceptions\SitemapPilotException`,
which extends `RuntimeException` — so one `catch` covers the package, and code
that already caught `RuntimeException` keeps working.

| Exception | Raised on | Carries |
| --- | --- | --- |
| `ConfigurationException` | before any request — no API key, or no property ID | — |
| `AuthenticationException` | `401` — token missing, wrong or regenerated | `status()`, `payload()` |
| `AuthorizationException` | `403` — token belongs to another workspace | `status()`, `payload()` |
| `ValidationException` | `422` — URL off-host, or nothing to submit | `errors()` keyed by field |
| `RateLimitException` | `429` — over the limit | **`retryAfter()`** in seconds |
| `ConnectionFailedException` | DNS, TLS, refused, or timeout | `getPrevious()`, `status()` is `null` |
| `SitemapPilotException` | any other non-2xx | `status()`, `payload()`, `body()` |

The API allows **60 requests per minute per token**, plus a looser per-address
backstop. Over the limit it answers `429` with a JSON body and a `Retry-After`
header; `retryAfter()` reads that header back so you can actually back off:

```php
use SitemapPilot\Laravel\Exceptions\RateLimitException;

try {
    SitemapPilot::pingUrls($urls);
} catch (RateLimitException $e) {
    $this->release($e->retryAfter() ?? 60);
}
```

`SendSitemapPingJob` sets `$tries = 3`, logs a warning and rethrows, so a
transient failure is retried by your queue rather than lost.

---

## Endpoints this package calls

Nothing else. The SDK is a wrapper around these four routes:

| Method | Path | Success |
| --- | --- | --- |
| `POST` | `/api/v1/properties/{id}/generate` | `202` |
| `POST` | `/api/v1/properties/{id}/submit` | `200` |
| `POST` | `/api/v1/properties/{id}/ping-urls` | `200` |
| `GET` | `/api/v1/properties/{id}/status` | `200` |

All four take `Authorization: Bearer <token>` and nothing else — there is no
session and no CSRF token involved.

---

## Contributing

```bash
composer install
composer test
```

The suite runs on [Orchestra Testbench](https://github.com/orchestral/testbench)
and fakes the HTTP layer; no network and no SitemapPilot account are needed. It
is the gate on a release tag, so it has to be green on a clean checkout, not
just on yours.

Releasing is [PUBLISHING.md](PUBLISHING.md).

---

## License

MIT. See [LICENSE](LICENSE).
