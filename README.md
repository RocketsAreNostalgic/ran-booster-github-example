# RAN Booster — GitHub Example

**Optional developer example; GitHub support is already bundled with Booster.**

This thin WordPress plugin composes the real `ran/booster-github-provider`
Composer library. It demonstrates explicit replacement of Booster's GitHub.com
implementation, with the same provider code `gh`, separate private workflow
storage and the existing Core review/consent screens. Its GitHub behavior comes
from that package; it adds no administration UI, Enterprise endpoint or GitLab support.

This public repository is a developer reference and an unreleased development
candidate. [Example PR #1](https://github.com/RocketsAreNostalgic/ran-booster-github-example/pull/1)
is merged on `main`; this beta.19 source update is pending owner merge.
[Provider PR #92](https://github.com/RocketsAreNostalgic/ran-booster-github-provider/pull/92)
is merged and published Provider beta.19 as immutable commit
`a01497d6ebdd8df6ea155c2b2b873e36e2d0018b`. [Core PR #499](https://github.com/RocketsAreNostalgic/ran-booster/pull/499)
is merged at `65369e79d281c11180303e5526c7c486abea6afd`, retaining Provider
API 15 and public native-update checkpoints. Installed WordPress/database,
native-update, and operator acceptance are underway. Follow
[Provider issue #90](https://github.com/RocketsAreNostalgic/ran-booster-github-provider/issues/90)
for delivery and acceptance status.

The example requires those Core checkpoints and the qualified Provider
namespace candidate. Unsupported Core installations are refused. Source and
archive checks are complete for the recorded review assembly; installed
WordPress/database, native-update and operator acceptance remain open. No
installable release or WordPress.org publication is available from this project.

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

The current qualification assembly uses merged Core #499 at
`65369e79d281c11180303e5526c7c486abea6afd` and Provider beta.19 at
`a01497d6ebdd8df6ea155c2b2b873e36e2d0018b`, updater beta 12 at
`4e89b6c61ae57ebf02a74cea697ab0ee06d95f66` and updater-support beta 4 at
`357db930b407941bd19a9e890c925d3d16ae9b15`. The root requirement permits
`~1.0.0-beta.19`; the committed lock and qualification map bind the exact
Provider publication and complete dependency source hashes. A changed source
tuple requires fresh qualification.

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
bootstrap. Therefore the supported assembled Core must bundle the same
qualified Provider beta.19 bytes, in addition to supplying the new context methods.
An older Core copy that preloads the pre-namespace factory is an explicit
negative case, regardless of which plugin file loads first. Do not assume that
installing a second physical Composer package replaces classes PHP has already
loaded. Exact Core/provider/example/updater revisions and loaded implementation
paths/digests belong in qualification evidence.

## Transporter limits

With a compatible destination provider already available, Transporter can
reconstruct its supported managed-package data and explicitly selected supported
Core-held credential data through that destination provider. It does not install
this example implicitly. Missing capabilities, unavailable providers and
incompatible/inaccessible credentials block the affected operation; Disabled
defaults and fresh selection/credential checks remain.

Private setup records, preview keys/indexes, locks, private external credentials
and remote webhook configuration are excluded. This example adds no Blueprint
version, provider-option scanner or private-state import channel.

## Adapt the example

1. Keep `gh` only when implementing the supported GitHub replacement contract.
2. Choose your own plugin basename and replacement implementation identity.
3. Choose a distinct stable workflow namespace and own only its cleanup.
4. Declare the actual credential/signing formats and aggregate capabilities.
5. Retain delayed construction, fresh guards and both native-update checkpoints.
6. Lock and qualify dependency copies, both plugin orders and the built archive.
7. Prove explicit consent, no fallback, switching, return and disposable uninstall.

Private workflow state separation prevents accidental collisions; it does not
sandbox trusted active PHP plugins or authenticate a third-party publisher.
