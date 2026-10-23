# RAN Booster — GitHub Example

**Optional developer example; GitHub support is already bundled with Booster.**

This thin WordPress plugin composes the real `ran/booster-github-provider`
Composer library. It demonstrates explicit replacement of Booster's GitHub.com
implementation, with the same provider code `gh`, separate private workflow
storage and the existing Core review/consent screens. Its GitHub behavior comes
from that package; it adds no administration UI, Enterprise endpoint or GitLab support.

This public repository is a developer reference and has no installable release
or WordPress.org publication. Provider PR #93 published beta.19 at immutable
commit `a01497d6ebdd8df6ea155c2b2b873e36e2d0018b`; Example PR #3 merged this
source at `766cfa1cfb408b9381e119b30112eff54e7b6fb0`. The accepted Core build is
`b026542da75d88a36eb78ca6b9517ca7bb69433e`, an unreleased merged-main CI build
from run `39915421217` that retains the beta.37 header.

The bounded W4 and W5 composition, operator, and uninstall scopes are complete
for [Provider issue #90](https://github.com/RocketsAreNostalgic/ran-booster-github-provider/issues/90).
That evidence does not publish a new Core or Example release, and it does not
make a newly built ZIP from this checkout installed or accepted. Unsupported
Core installations are refused; changed source or archive bytes need their own
qualification.

## Construct and install

Use PHP 8.2+, WordPress 7.0+ and a compatible Booster installation. Install the
tracked Composer lock, run the repository checks, and build the plugin:

```sh
git clone https://github.com/RocketsAreNostalgic/ran-booster-github-example.git
cd ran-booster-github-example
composer install --no-interaction --prefer-dist --no-progress
RAN_BOOSTER_CORE_PATH=/absolute/path/to/compatible-core composer check
composer build
python3 scripts/test-archive.py
```

Tests require the supported Core source checkout and its installed dependencies;
Core is not a production Composer dependency of this plugin. To include the
historical incompatible-copy test, also set `RAN_BOOSTER_OLD_CORE_PATH` to a
separate Core assembly containing Provider beta 18 (`b5ffb6c1f9f4546a1006bcb74a5296419cff0106`).
Without that optional fixture, the test reports a skip. All HTTP and credential
material in these tests is synthetic. Archive probes use fresh marked temporary
directories; they do not establish installed WordPress activation or UI acceptance.

Install the ZIP produced under `dist/` on a disposable test site. A source
checkout without Composer dependencies and the dependency-qualification map is
incomplete. `Requires Plugins: ran-booster` supplies WordPress's dependency
hint; runtime checks also require exact Provider API 15 and both native-update
checkpoint methods. No Core container or private host service is accessed.

The recorded qualification assembly uses merged Core
`b026542da75d88a36eb78ca6b9517ca7bb69433e` and Provider beta.19 at
`a01497d6ebdd8df6ea155c2b2b873e36e2d0018b`, updater beta 12 at
`4e89b6c61ae57ebf02a74cea697ab0ee06d95f66` and updater-support beta 4 at
`357db930b407941bd19a9e890c925d3d16ae9b15`. The root requirement permits
`~1.0.0-beta.19`; the committed lock and qualification map bind the exact
Provider publication and complete dependency source hashes. A changed source or
archive tuple requires fresh qualification.

Stable versions are preferred for other dependencies. No dependency publishing
is performed by these commands.

## Identity and approval

| Meaning | Value |
| --- | --- |
| Repository and plugin slug | `ran-booster-github-example` |
| Supplying plugin basename | `ran-booster-github-example/ran-booster-github-example.php` |
| Replacement implementation | `ran/booster-github-example` |
| Provider code | `gh` |
| Private workflow namespace | `github_example` |

The main-file replacement header and adjacent JSON manifest are passive. The
registration callback calls `declare_github_replacement()` with that declaration
and an exact three-input factory. It does not compete through direct `gh`
registration. Loading or activating the example does not construct a provider,
read retained credential material or approve replacement.

Core invokes the factory only after its current exact selection and consent
checks. The factory uses the real library and public updater bootstrap, passing
lazy artifact limits, fresh operation authorization and both native mutation
checkpoints. Native begin must still be approved immediately before installed
files change. Completion remains available to record the exact operation's
outcome after revocation. The manifest claims the actual aggregate, including
workflow V4; it does not claim the independent V3 interface.

Activate the intended example, open Booster's GitHub page, review the supplying
implementation and affected packages/credentials, and make the explicit consent
choices. Compatible retained API credentials, including a configured constant
overlay, are used only under Core's approval rules. Incompatible credentials
stay unavailable. Missing selection or credential authority never falls back
to another profile, constant, anonymous access or bundled implementation.

Multiple candidates require an explicit choice after deactivating extras. A
missing selected implementation stays unavailable. To return to bundled GitHub,
deactivate all replacement candidates and explicitly review/approve the return
in Core. Stale forms, queued work and old previews do not grant new authority.

## Storage ownership and uninstall

`github_example` is explicitly supplied to the library factory. Setup records,
cache invalidation, review previews, advisory locks and cleanup stay inside that
immutable namespace. Bundled keys and retired bundled cleanup targets remain
separate. Deactivation retains example state. Changing the namespace starts a
new independent store; it does not migrate or discover another store.

Uninstall verifies the exact plugin basename and resolved target path, refuses
a symlinked development plugin directory, checks qualified dependency bytes,
and removes only indexed previews and durable records demonstrably owned by
`github_example`. Unknown ownership or incomplete cleanup refuses and reports
uncertainty. No global cache flush, Core credential/package/history deletion,
bundled or retired bundled cleanup, remote webhook deletion or workflow removal
occurs. Test uninstall only on a marked disposable installation with copied
files, never a retained site or shared development checkout.

An empty private store does not mean a remote repository has no automation.
The library retains repository identity, workflow/path conflicts, exact remote
branch/PR/file checks and uncertain-result readback. Matching draft recovery is
bounded by the existing base/template identity; it cannot reconstruct every old
setup PR or promise zero additional drafts. Preview receipts and locks are never
portable authority.

## Dependency copies and plugin load order

The build qualification map records exact runtime source hashes for the
Provider, shared updater and updater-support packages. Before the approved
factory loads dependencies, the example verifies its vendored files and every
already-loaded symbol in those three namespaces. Byte-identical physical-copy
reuse is supported; incompatible or unknown copies are refused. The approved
factory resolves the complete qualified Provider class set before construction,
preventing later source mixing through another Composer loader. This introduces
no runtime broker or class-scoping system. The updater continues to use its own
public bootstrap and existing runtime arbitration.

Core reads the bundled Provider's passive compatibility contract early in
bootstrap. A compatible assembly must therefore bundle the same qualified
Provider beta.19 bytes and provide the required context methods. The accepted
Core-first selection used Core's qualified physical Provider copy; the Example
copy was accepted because its qualified runtime bytes were equal. That records
loaded-copy attribution for the accepted assembly. It does not promise that the
Example vendor tree is the physical origin of loaded Provider classes. An older
Core copy that preloads the pre-namespace factory is an explicit negative case,
regardless of which plugin file loads first. Do not assume that installing a
second physical Composer package replaces classes PHP has already loaded. Exact
Core/provider/example/updater revisions and loaded implementation paths/digests
belong in qualification evidence.

## Transporter limits

`tests/TransporterTest.php` covers a controlled composition with synthetic
repository responses. It exercises supported managed-package data and explicitly
selected supported Core-held credential data through an already available
destination provider. It does not install this example implicitly. Missing
capabilities, unavailable providers and incompatible/inaccessible credentials
block the affected operation; Disabled defaults and fresh selection/credential
checks remain.

Private setup records, preview keys/indexes, locks, private external credentials
and remote webhook configuration are excluded. This example adds no Blueprint
version, provider-option scanner or private-state import channel. This is not
new live remote-delivery evidence and does not migrate private workflow state.

## Adapt the example

Use these concrete touchpoints when creating a separate replacement. They are
coupled: changing the visible identity without the matching runtime and archive
inputs creates an invalid assembly.

| Concern | Files and required alignment |
| --- | --- |
| Main identity | Rename `ran-booster-github-example.php`; update its WordPress header and the adjacent `ran-booster-github-example.gh-replacement.json` `plugin_basename` and distinct `implementation_id` together. Also update both manifest paths in `Plugin::declare()` and `Plugin::availability_error()` so they name that manifest. Keep `gh` only for the supported GitHub replacement contract, and declare the actual credential/signing formats and aggregate capabilities. |
| Runtime identity and storage | Update `src/Plugin.php`: `Plugin::BASENAME` must match the main file, and `Plugin::WORKFLOW_NAMESPACE` must be distinct and stable. The namespace owns only its own workflow records, previews, locks, and cleanup. |
| Autoload and package root | Update `composer.json` package identity, `autoload.psr-4`, and `autoload-dev.psr-4` when the PHP namespace changes. Update the main-file bootstrap, `uninstall.php`, and test namespace references to that same namespace. Regenerate and commit the matching lock and qualification map. |
| Uninstall boundary | Keep `uninstall.php` delegating to the plugin's guarded cleanup. Update the exact basename and resolved `WP_PLUGIN_DIR` path checks in `Plugin::uninstall()` with the renamed main file; retain the symlink refusal. |
| Build inputs | Update `scripts/build.py` `SLUG` and its fixed input list together: `composer.json`, `composer.lock`, the main file, `uninstall.php`, replacement manifest, `qualified-provider.json`, `README.md`, and `LICENSE`. |
| Dependency qualification | After lock changes, run `php scripts/qualify-dependencies.php` to regenerate `qualified-provider.json`, then run `php scripts/qualify-dependencies.php --check`. Keep it aligned with the lock and installed runtime files before `composer build`. |
| Tests and proof | Update focused composition, dependency-copy, archive-uninstall, and Transporter tests for the new identity. In `scripts/test-archive.py`, update the ZIP path, ZIP root prefix, expected main filename, extracted plugin directory, and embedded `RAN\\BoosterGitHubExample` namespace. Qualify both plugin load orders and the built archive; then prove consent, no fallback, switching, return, and disposable uninstall separately. |

Private workflow state separation prevents accidental collisions; it does not
sandbox trusted active PHP plugins or authenticate a third-party publisher.
