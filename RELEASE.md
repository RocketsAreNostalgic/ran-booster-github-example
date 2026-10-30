# Releases

Release Please owns versions, changelog entries, release PRs, tags and draft
GitHub Releases. The pinned organization
[Profile B](https://github.com/RocketsAreNostalgic/.github/blob/3f494749341f23b8c3ed1048380a5f20526f4814/docs/release/RELEASE_PROFILE_B.md)
publishes the exact ZIP and checksum already tested by successful main Quality.
Follow the shared
[release skill](https://github.com/RocketsAreNostalgic/.github/blob/3f494749341f23b8c3ed1048380a5f20526f4814/.agents/skills/release-please/SKILL.md)
and [release trust policy](https://github.com/RocketsAreNostalgic/.github/blob/3f494749341f23b8c3ed1048380a5f20526f4814/docs/release/RELEASE_TRUST.md).

The Example remains optional: Booster already includes GitHub support. A
published Example ZIP does not approve an implementation or credential reuse on
a site, and publication does not establish broader Core or operator acceptance.

## Versions and dependency uptake

The existing `0.1.0` is the deliberate bootstrap baseline, not a claim that a
release was published. `bootstrap-sha` excludes earlier repository history.
The first release advances from this baseline using the reviewed changes after
that boundary. Do not fabricate a baseline tag or edit generated versions to
force a particular outcome.

The simple strategy updates `version.txt`, the plugin header through its bounded
generic updater block, `.release-please-manifest.json`, and `CHANGELOG.md`.
These are the version sources. Composer intentionally has no root `version`.
The gettext catalogue has a fixed package name rather than another version.
`composer release:check` rejects mismatched versions.

Production dependency adoption drives an Example release. Update real released
requirements, the lock, dependency qualification hashes, and the exact compatible
Core host contract as one qualified change. Use `fix(deps):` for a compatible
uptake. A new upstream release alone does not change this repository or authorize
an unqualified dependency update. Versions are independent of dependency version
numbers; the Example does not copy a Provider beta counter.

`feat:`, `fix:`, `perf:` and `revert:` are visible release-driving classifications.
`fix(deps):` gives a patch release, `feat:` a minor release, and breaking `!` or
`BREAKING CHANGE:` follows Release Please's configured pre-1.0 policy. A
development-only dependency change may use `chore(deps):` and does not by itself
need a release. Hidden `build:` or `refactor:` must not conceal a runtime change.

Ordinary PRs normally use squash with the reviewed Conventional Commit PR title.
Merge commits are also supported: release-significant PRs must retain at least
one release-driving commit as well as a release-driving title. CI compares exact
merge-base-to-head production manifests, production lock packages, runtime
sources, translations and packaging inputs. PR title edits rerun that evidence.
Review breaking footers and the actual surviving metadata before merge; prefer
an explicit `!` in the title when the PR changes a compatibility contract.

## Quality and artifact custody

`Quality` supports PRs, pushes to main and input-free manual dispatch. It executes
the exact PR head or dispatch/push SHA with read-only repository permissions and
no persisted checkout credentials. All Actions are pinned to full SHAs.

This PHP-only plugin uses a specialist local workflow because its canonical
checks need the separately checked-out exact Core fixture before PHPStan and
tests can run. It retains the shared PHP baseline's exact checkout, strict
validation, locked installation and canonical checks without introducing Node
or a frontend toolchain. Its matrix covers PHP 8.2 with MySQL 8.4 and PHP 8.5
with MariaDB 11.4 on WordPress 7.0.

Each lane installs Example's complete lock and Core's production-only lock,
verifies the host contract, runs `composer check`, builds and tests the ZIP twice
with identical digests, checks first-party PHP archive bytes against maintained
source, and downloads the published Core release ZIP recorded by the fixture
contract. The archive digest and embedded commit and version must match the
contract before installation. Each lane activates the Example and published Core
ZIPs in disposable WordPress and checks both plugin load orders. A separate
published-Core consumer proof uses those same genuine release bytes and the normal
Example package for release discovery, verified installation, explicit adoption,
consumer operations, protocol-7 shared-runtime checks and incompatible-copy refusal.
It also prepares the locked Provider as a candidate and exercises its installed
consumer in another disposable database; that candidate proof does not replace
published Core qualification. The terminal `quality` job requires every matrix lane to succeed.

The PHP 8.2 lane retains:

- `ran-booster-github-example-<version>.zip`;
- the matching `.zip.sha256`; and
- `ran-profile-b-promotion.json`, binding the repository, exact source/Quality
  SHA, tag, asset names and SHA-256 digests.

The artifact name includes the original Quality run ID and attempt. Shared
Profile B downloads those tested bytes after exact successful-main admission;
it does not rebuild, patch or replace them in the privileged publisher.
An expired artifact, unexpected asset, conflicting digest or changed identity
fails closed. Correct a published build through the next version.

## Setup, first release and owner decisions

Before merging this setup, verify owner-managed Actions/reusable-workflow access,
bot PR creation, immutable-release policy, and the actual branch rules. Require
the ordinary PR-event `quality` check from GitHub Actions once this new workflow
has executed successfully. Repository rules must preserve PR review and resolved
threads. Source configuration and a green local test cannot prove live settings.

Merging setup can start main Quality and create a bot-owned release PR. It does
not authorize merging that release PR. For every source and release PR:

1. Freeze its exact current base/head SHAs, obtain independent agent review and
   resolve or explicitly disposition every finding.
2. Require successful ordinary PR checks. A manually dispatched bot-candidate
   run qualifies the candidate for Profile B but may not satisfy PR-event branch
   rules; approve or rerun the ordinary workflow when required.
3. Review the proposed version, all version-source edits, changelog and built ZIP.
   Bot release candidates may change only those version sources and changelog.
4. Present the exact PR tuple, proposed merge method, checks and remaining risks
   to the owner. Merge only with authorization for that specific PR.
5. After authorized release delivery, observe main Quality and Profile B, then
   read back the actual tag target, non-draft immutable release and both asset
   digests. Workflow success or a merged release PR alone is not publication.
6. After the first genuine release is published and read back, enable periodic
   release-liveness observation in a reviewed follow-up. Do not invent a tag,
   release proposal or lifecycle labels to satisfy the observer during bootstrap.

`Release liveness` is a read-only observer of the root release lifecycle. It does
not certify asset bytes, installed behavior or manual acceptance. It is
manual-only during the unpublished bootstrap: the shared observer expects a real
merged release proposal, which the baseline `0.1.0` intentionally does not claim.
Automatic release proposals and tested-asset publication retain their ordinary
Quality-driven process.

GitHub publication is separate from WordPress.org publishing and deployment.
This repository has no SVN publisher and no retained-site deployment step.
