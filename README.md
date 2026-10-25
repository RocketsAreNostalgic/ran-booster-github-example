# RAN Booster — GitHub Example

**Optional developer example; GitHub support is already bundled with Booster.**

This repository shows how to package a replacement for Booster's GitHub support
as a small WordPress plugin. In this guide, a *provider* is the code Booster
uses to talk to a service such as GitHub. This plugin does not implement GitHub
again. It wraps the existing `ran/booster-github-provider` Composer library and
connects it to Booster through the public replacement contract.

Start with [Choose GitHub support](https://github.com/RocketsAreNostalgic/ran-booster/blob/b026542da75d88a36eb78ca6b9517ca7bb69433e/docs/github-provider-replacement.md)
if you operate a site, or read the [developer contract](https://github.com/RocketsAreNostalgic/ran-booster/blob/b026542da75d88a36eb78ca6b9517ca7bb69433e/docs/provider-extension-contract.md#github-replacement-presentation)
before adapting this plugin.

This example is intended for a separate WordPress test site you can discard.
There is no WordPress.org listing. Until the first qualified GitHub release is
published, build the repository yourself. See [Releases](RELEASE.md) for the
dependency-update and tested-ZIP release process.

## What this plugin replaces

Booster has one GitHub provider slot, identified by `gh`. Installing this plugin
does not add a second provider or change existing GitHub-managed packages into
another kind of package. Instead, the plugin declares a candidate with its own
plugin basename and replacement implementation ID. Booster decides which candidate
may supply the one `gh` slot.

Activation only makes the candidate discoverable. It does not construct the
provider, read saved credentials, or approve anything. In Booster's GitHub
screen, an administrator reviews the supplying plugin and explicitly approves
it. Reusing compatible saved API credentials requires separate approval.

When more than one candidate is active, when the selected candidate is missing,
or when an old review is stale, GitHub work pauses. Booster does not substitute
another saved profile, anonymous access, a constant setting, or the bundled
provider. To return to bundled GitHub, deactivate every replacement candidate,
then use Booster's explicit return review.

Booster calls `Plugin::create()` only after approval and passes the services and
permission checks it needs. Keep the begin and complete native-update callbacks:
the first authorizes a change immediately before WordPress writes files, and the
second records its outcome. The plugin does not use a private Booster container.

## Build and check a local ZIP

Use PHP 8.2–8.5, WordPress 7.0 or newer, and a compatible Booster build
with Provider beta.20, Provider API 15, and the native-update checkpoints. Booster
and Example must contain matching Provider files. PHP may load Booster's copy
first; Example allows that reuse only when those files match the dependency file
hashes it records.

Development checks also require Composer 2, Python 3.10 or newer and WP-CLI
2.12.0. The quality profile is `wordpress-plugin`; this PHP-only plugin has no
frontend build. `host-contract.json` identifies the exact Core source revision
used for qualification. Check out that revision in a separate directory and
install its locked production dependencies with `composer install --no-dev`.
The recorded host is a test fixture, rather than an additional runtime version
restriction on compatible Booster installations.

```sh
git clone https://github.com/RocketsAreNostalgic/ran-booster-github-example.git
cd ran-booster-github-example
composer install --no-interaction --prefer-dist --no-progress
RAN_BOOSTER_CORE_PATH=/absolute/path/to/compatible-core composer check
composer build
python3 scripts/test-archive.py
python3 scripts/release.py archive
```

`composer check` needs a supported Booster source checkout with its dependencies.
Booster is not a production Composer dependency of this plugin. The checks use
synthetic credentials and HTTP responses, and the archive probe uses temporary
directories rather than a live site or remote repository.

The aggregate runs the shared PHP coding standard, PHPStan level 8, maintained-file
coverage and negative controls, tests, gettext parity and dependency qualification.
The host check verifies Core's revision, tracked source bytes, production lock and
matching runtime dependencies. `composer i18n:pot` regenerates the tracked runtime
translation catalogue; `composer i18n:check` checks it without changing source.

Use `fix(deps):` for production dependency updates so Release Please proposes an
Example release too. Development-only dependency updates may use `chore(deps):`.
Every update still needs qualification and review; see [RELEASE.md](RELEASE.md).

The built ZIP is written under `dist/`. Do not use a source checkout without
its Composer dependencies and `qualified-provider.json`, the file that records
dependency hashes. Do not install the ZIP on a site you rely on.

To try it, install the ZIP on a set-aside WordPress test site. Keep Booster
active and other GitHub replacement plugins inactive, then activate the Example.
In **Booster → GitHub**, open **Review GitHub provider**, make the displayed
approval choices (including saved-credential reuse if prompted), then choose
**Adopt GitHub implementation**. To return to bundled GitHub, deactivate every
replacement candidate and review the return in Booster.

## Private workflow state and uninstall

This example passes `github_example`, a fixed prefix (namespace) for its local
workflow setup records and review previews, to the library. Temporary locks and
cleanup also use that namespace. Deactivation keeps the state so an administrator
can return to the same implementation later. Changing the namespace starts a
different store; it does not migrate or discover old records.

Uninstall checks the expected plugin name and resolved path, rejects symlinked
development directories, and checks files against recorded hashes. It removes
only this namespace's saved records, preview index, and previews. It does not
delete Booster credentials, packages, history, bundled state, or remote workflows
and webhooks. If ownership or cleanup is uncertain, it retains state instead.
Run uninstall only on copied plugin files in a separate test site.

An empty local store does not prove that a remote repository is empty. The
library still checks repository identity, workflow and path conflicts, and the
result of uncertain writes before retrying. It cannot reconstruct every earlier
draft setup pull request, and it does not promise that a new setup produces no
additional drafts.

## Transporter scope

Transporter moves supported managed-package records and explicitly chosen
Booster-held credentials through an available, compatible provider. It does not
install this Example at a destination or move private workflow records, previews,
locks, external credentials, or remote webhook configuration.

## Adapt this example

These names appear in more than one file. Change them as a set.

1. **Plugin and manifest.** Rename `ran-booster-github-example.php`, update its
   WordPress header, and update the adjacent
   `ran-booster-github-example.gh-replacement.json` basename, distinct
   implementation ID, credential and signing formats, and capabilities
   (interfaces the provider implements). In `src/Plugin.php`, update both
   manifest paths used by `declare()` and `availability_error()`.
2. **Runtime identity.** Update `Plugin::BASENAME` and choose a distinct,
   stable `Plugin::WORKFLOW_NAMESPACE`. Keep the namespace responsible only for
   its own records, previews, locks, and cleanup. In `Plugin::uninstall()`, keep
   the hard-coded `WP_PLUGIN_DIR` directory and resolved main-file path aligned
   with the exact basename, while retaining the symlink guards.
3. **PHP and Composer names.** When changing the PHP namespace, update the
   package name plus `autoload.psr-4` and `autoload-dev.psr-4` in
   `composer.json`; then update the main-file bootstrap, `uninstall.php`, test
   namespaces, and the namespace declarations in `scripts/lint.php` and
   `scripts/qualify-dependencies.php`.
4. **Build inputs and qualification.** Update `SLUG` and the fixed input names
   in `scripts/build.py`: Composer files, main file, uninstall entry point,
   manifest, qualification map, README, and license. After changing the lock,
   run `php scripts/qualify-dependencies.php` to regenerate the map, then run
   `php scripts/qualify-dependencies.php --check` before `composer build`.
5. **Archive probe.** In `scripts/test-archive.py`, update the ZIP filename and
   root prefix, expected main filename, extracted plugin directory, and embedded
   `RAN\BoosterGitHubExample` namespace. In `tests/archive-uninstall.php`,
   update the fixture-root basename and expected uninstall invocation.
6. **Fixture tests.** Update the copied main-file and manifest paths and plugin
   root directory in `tests/CompositionTest.php` and
   `tests/TransporterTest.php`. Check both plugin load orders, the built archive,
   approval, no fallback, switching, return, and disposable uninstall.

Private workflow separation prevents accidental collisions. It does not
sandbox a trusted active PHP plugin or authenticate a third-party publisher.

For public reports and contributions, follow the organization
[support guidance](https://github.com/RocketsAreNostalgic/.github/blob/main/SUPPORT.md),
[security policy](https://github.com/RocketsAreNostalgic/.github/blob/main/SECURITY.md)
and [contribution guidance](https://github.com/RocketsAreNostalgic/.github/blob/main/CONTRIBUTING.md).
