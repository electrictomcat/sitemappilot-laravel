# Changelog

All notable changes to `sitemappilot/laravel` are documented here.
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Nothing yet.

## [1.0.0] - unreleased

First public release. Everything below describes the package as it stands at
the point of its first Packagist tag. The `v1.0.0` tag exists on the source
repository but the package is not on Packagist yet, so this entry stays
"unreleased" until it is; [PUBLISHING.md](PUBLISHING.md) is the order of
operations for getting there.

### Added

- `SitemapPilotClient` covering the four public REST endpoints:
  `POST /api/v1/properties/{id}/generate`, `POST …/submit`,
  `POST …/ping-urls` and `GET …/status`.
- `SitemapPilot` facade, with `SitemapPilot::fake()` and the
  `assertPinged` / `assertNotPinged` / `assertGenerated` / `assertSubmitted` /
  `assertNothingSent` assertions.
- `AutoPingsSitemap` Eloquent trait, queueing a `SendSitemapPingJob` on `saved`
  and `deleted`.
- Artisan commands `sitemappilot:ping`, `:generate`, `:submit` and `:status`.
- A typed exception hierarchy under `SitemapPilot\Laravel\Exceptions`, all
  extending `SitemapPilotException extends RuntimeException`:
  `ConfigurationException`, `AuthenticationException` (401),
  `AuthorizationException` (403), `ValidationException` (422, with
  `errors()`), `RateLimitException` (429, with `retryAfter()` read from the
  `Retry-After` header) and `ConnectionFailedException` for transport
  failures.
- A Testbench test suite (`composer test`) that fakes the HTTP layer, so it
  needs neither network access nor a SitemapPilot account.

[Unreleased]: https://github.com/electrictomcat/sitemappilot-laravel/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/electrictomcat/sitemappilot-laravel/releases/tag/v1.0.0
