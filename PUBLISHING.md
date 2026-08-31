# Publishing `sitemappilot/laravel`

The order of operations for the first Packagist release, written against what
exists today rather than what a release checklist usually assumes.

## What is already true

Checked on 2026-08-31 from a machine with network access:

| Fact | How it was checked | Result |
| --- | --- | --- |
| The standalone source repository exists | `git ls-remote https://github.com/electrictomcat/sitemappilot-laravel.git` | `refs/heads/main` and `refs/tags/v1.0.0`, both at `3b70391` |
| There is **no** `sitemappilot` GitHub organisation | `curl -sI https://github.com/sitemappilot` | `404` |
| The package is **not** on Packagist | `curl https://repo.packagist.org/p2/sitemappilot/laravel.json` | `404` |
| Nothing is published under the `sitemappilot` vendor | `curl 'https://packagist.org/packages/list.json?vendor=sitemappilot'` | `{"packageNames":[]}` |

Two consequences worth stating plainly, because both were assumed the other
way round at some point:

- **Nothing may link to `github.com/sitemappilot/…`.** That org does not
  exist. The source of truth is `github.com/electrictomcat/sitemappilot-laravel`,
  which is what `composer.json`'s `support.source` and the CHANGELOG's compare
  links now point at.
- **You do not need a GitHub organisation named `sitemappilot`.** Packagist
  takes the package name from `composer.json`, not from the repository's owner,
  and the `sitemappilot` vendor prefix is unclaimed. Submitting the repository
  above publishes it as `sitemappilot/laravel`.

## 0. Decide how the monorepo carries this directory — before anything else

`packages/sitemappilot-laravel` is **itself a git repository** (it has its own
`.git` and the remote above), and it is untracked in the outer repository.
`git add packages/` there prints:

```
warning: adding embedded git repository: packages/sitemappilot-laravel
```

which records a *gitlink*, not the files. That matters far beyond this package:
the host application's `bootstrap/providers.php` registers
`SitemapPilot\Laravel\SitemapPilotServiceProvider` on every request, and the
root `composer.json` autoloads it from `packages/sitemappilot-laravel/src/`. A
deploy that clones the outer repository and gets an empty directory boots into
`Class "SitemapPilot\Laravel\SitemapPilotServiceProvider" not found` — on every
request, not just the SDK's.

Pick one and verify it with a throwaway `git clone` of the outer repository
before deploying:

1. **Track the files.** Remove the nested `.git` (every commit in it is already
   pushed to the remote above — `git ls-remote` shows `main` at `3b70391`) and
   `git add packages/sitemappilot-laravel`. Publishing then means pushing a
   copy to the standalone repository: `git subtree split` or a mirror script.
2. **Make it a real submodule**:
   `git submodule add https://github.com/electrictomcat/sitemappilot-laravel packages/sitemappilot-laravel`.
   Confirm the deploy runs `git submodule update --init --recursive`; Laravel
   Cloud's default clone does not necessarily.
3. **Require it from Packagist** once step 4 below is done, and drop the
   directory from the outer repository. The provider then comes from package
   discovery, so the entry in `bootstrap/providers.php` and the `autoload`
   mapping in the root `composer.json` come out at the same time.

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

## 4. Submit it to Packagist

1. Sign in at <https://packagist.org> with the GitHub account that can read the
   repository.
2. <https://packagist.org/packages/submit>, paste
   `https://github.com/electrictomcat/sitemappilot-laravel`.
3. Packagist reads `composer.json` and publishes it as **`sitemappilot/laravel`**.
   If it refuses the vendor prefix, rename the package in `composer.json` — and
   update the host application's `SdkPackageTest::test_the_package_manifest_is_publishable`,
   which asserts the name, plus `config/sitemappilot.php`'s `sdk.package`.
4. Enable the GitHub hook Packagist offers on submit, so new tags appear without
   pressing **Update**.

## 5. Prove it resolves before telling anyone it does

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://repo.packagist.org/p2/sitemappilot/laravel.json   # expect 200
composer require sitemappilot/laravel                                                             # in a scratch Laravel app
```

## 6. Only then, flip the copy

- Set `SITEMAPPILOT_SDK_PUBLISHED=true` in the deployed environment. The
  dashboard's **API & SDK Integrations** page and `/docs/api-reference` start
  printing `composer require sitemappilot/laravel`; the host application's
  `PublicPagesTest` covers both positions of that flag, so neither page can
  print an install command while it is off.
- Delete the **Release status** block from this package's `README.md`, and move
  the CHANGELOG's `1.0.0` heading off "unreleased".
