# RAN Booster GitHub Example

This optional WordPress plugin wraps `ran/booster-github-provider`; Booster
already includes GitHub support. Keep the public replacement contract, delayed
factory, explicit implementation/credential consent and private workflow namespace
intact. Core owns selection and mutation authority. Do not add a private Core
container dependency or import Core into the distributed plugin.

## Development contract

- Quality profile: `wordpress-plugin`, with a PHP-only source surface.
- WordPress 7.0; PHP 8.2–8.5 (`>=8.2 <8.6`); Composer 2; Python 3.10+;
  WP-CLI 2.12.0 for gettext generation and disposable installation checks.
- Install from `composer.lock`. Keep PHP declarations, compatibility rules,
  PHPStan semantics, documentation and CI aligned. Do not delete the lock or
  silently increase the PHP floor to match Starter.
- `host-contract.json` records the exact published Core fixture, release ZIP digest
  and Provider API. `RAN_BOOSTER_CORE_PATH` must identify that clean Git checkout
  with its locked **production** Composer dependencies. It is a qualification
  fixture, not an additional runtime version gate.
- Read the organization [quality standards](https://github.com/RocketsAreNostalgic/.github/blob/main/docs/quality/QUALITY_STANDARDS.md)
  before changing quality tooling. Retain shared `RANWordPressPlugin` ancestry,
  opt-in `RANOwnedMethods`, Yoda conditions, narrow justified exceptions and
  direct PHPStan level 8 analysis of maintained PHP, including development code.

Run the canonical checks from this repository:

```sh
composer install --no-interaction --prefer-dist
RAN_BOOSTER_CORE_PATH=/absolute/path/to/pinned-core composer check
composer build
python3 scripts/test-archive.py
python3 scripts/release.py archive
```

`composer check` is read-only. Coverage controls must continue to reject
omitted/new PHP and weakened diagnostics. Keep static archive PHP probes in
directly checked PHP files; do not hide executable PHP inside Python or shell
strings. PHPCBF and PHPCS use the same rules and paths. Regenerate the tracked
gettext catalogue with `composer i18n:pot`; `composer i18n:check` verifies parity.
There are no frontend assets requiring Node or pnpm.

Never commit `vendor/`, caches, generated ZIPs, credentials, local-site state or
private planning. Clean installation tests belong only in purpose-built,
disposable WordPress environments. Synthetic source/archive tests do not prove
installed behavior or human acceptance.

## Dependency updates and releases

Before setting up, changing or operating release automation, read the canonical
[RAN Release Please skill](https://github.com/RocketsAreNostalgic/.github/blob/main/.agents/skills/release-please/SKILL.md)
and local [release guide](RELEASE.md). Follow shared Profile B; do not add a local
publisher or a second version engine.

Production dependency updates use `fix(deps):` or another recognized release-driving
classification. Update manifest, lock, dependency qualification and the exact
host fixture together where affected. Development-only updates may use `chore(deps):`.
Release Please owns versions, changelog, release PR, tag and draft release;
Quality builds and tests the ZIP before shared Profile B promotes those exact
bytes. GitHub publication, WordPress.org publication and site deployment are
separate actions; the latter two are not configured here.

Every PR needs independent agent review after opening against its exact live
base/head SHAs. Resolve or explicitly disposition every finding. Ordinary source
PRs use squash; bot-owned release proposals use merge commits. Ben retains the
specific merge and publication decisions: implementation, green CI and review
do not authorize them. Present the PR tuple, checks, merge method and remaining
risks before requesting a decision.

## Public reporting and agent tools

This public organization repository inherits the organization community-health
files. Follow its [support](https://github.com/RocketsAreNostalgic/.github/blob/main/SUPPORT.md),
[security](https://github.com/RocketsAreNostalgic/.github/blob/main/SECURITY.md)
and [community](https://github.com/RocketsAreNostalgic/.github/blob/main/docs/COMMUNITY_STANDARDS.md)
boundaries. Never expose private sites/repositories, credentials or vulnerability
details in public issues or PRs.

Do not invoke Blacksmith AI/agent features, including codesmith, Autofix, CI
Tuning or Testbox agents. Ordinary explicitly configured Blacksmith runners are
infrastructure only. Diagnose CI from Actions logs without external AI tools.
