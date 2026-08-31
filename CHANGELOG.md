# Changelog

All notable changes to `sitemappilot/laravel` are documented here.
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Nothing yet.

## [1.0.1] - 2026-08-31

### Fixed

- Two failures in the package's own test suite. Neither was a defect in `src/`:
  the v1.0.0 runtime code is correct and the consuming application's
  integration suite passes against it unchanged.
  - `test_401_and_403_raise_distinct_exceptions` faked a 401, asserted, then
    faked a 403 in the same method. `Factory::fake()` merges its stubs into
    those already registered rather than replacing them, and the first stub
    whose pattern matches answers — so the 401 kept answering and the 403 case
    could never pass. Split into a data provider.
  - `test_missing_configuration_raises_before_any_request` could not see a
    missing key, because the test case set one for every test.
- `CHANGELOG.md` linked to `github.com/sitemappilot/laravel`, which is not this
  package's source repository.

## [1.0.0] - 2026-08-31

First public release, on Packagist as
[`sitemappilot/laravel`](https://packagist.org/packages/sitemappilot/laravel).

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
