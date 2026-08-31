# Releasing `sitemappilot/laravel`

The package is published. This is what to do for each release after the first.

## What is true now

| Fact | How to check |
| --- | --- |
| Source repository | [`electrictomcat/sitemappilot-laravel`](https://github.com/electrictomcat/sitemappilot-laravel) |
| Packagist | [`sitemappilot/laravel`](https://packagist.org/packages/sitemappilot/laravel) — `curl https://repo.packagist.org/p2/sitemappilot/laravel.json` |
| Consumed by the app | an ordinary `composer require` in the SitemapPilot application, not a path autoload |

Two things that were assumed the wrong way round while getting here, worth
keeping written down:

- **Nothing may link to `github.com/sitemappilot/…`.** That organisation does
  not exist. The source of truth is the repository above, which is what
  `composer.json`'s `support.source` and the changelog point at. The
  application's suite asserts the declared source is a real repository.
- **The vendor prefix does not need a matching GitHub organisation.** Packagist
  takes the package name from `composer.json`, not from the repository owner.

## The release order

Packagist indexes a tag within a minute or two, but Composer's own metadata
cache can lag behind it — `composer show <package> --all` may still list the
previous version after the API already serves the new one. Do not treat that
delay as a failed release; it resolves on its own.

## 1. Run the package's own suite

```bash
cd packages/sitemappilot-laravel
composer install
composer test
```

Green means 16 tests. This is the gate on the tag: the suite fakes the HTTP
layer, so it needs neither network access nor a SitemapPilot account, and it
runs against the same four endpoints the README documents.

## 2. Push the source repository

From inside `packages/sitemappilot-laravel` (its own repository):

```bash
git status
git commit -am "…"
git push origin main
```

## 3. Re-cut the tag

`v1.0.0` already exists on the remote, at `3b70391` — a commit that predates
these fixes, including the two test failures that made `composer test` fail on
a fresh clone. Nothing can have installed it (it is not on Packagist, and
`{"packageNames":[]}` says nothing under the vendor is), so move it rather than
publishing a broken `v1.0.0`:

```bash
git push origin :refs/tags/v1.0.0   # delete it on the remote
git tag -d v1.0.0
git tag -a v1.0.0 -m "First public release"
git push origin v1.0.0
```

If you would rather never move a tag, tag `v1.0.1` instead — and then bump
`SitemapPilotClient::VERSION` and the CHANGELOG in the same commit, because
that constant goes out on the `User-Agent` of every request the SDK makes and
is how a support ticket gets tied to a release.

## 4. Prove it resolves before telling anyone it does

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://repo.packagist.org/p2/sitemappilot/laravel.json   # expect 200
composer require sitemappilot/laravel                                                             # in a scratch Laravel app
```

## 5. First release only

Submitting the repository to Packagist and turning on
`SITEMAPPILOT_SDK_PUBLISHED` in the consuming application were one-time steps,
both done. The flag stays on unless the package is yanked; the application's
suite fails if the flag and the installed package ever disagree.
