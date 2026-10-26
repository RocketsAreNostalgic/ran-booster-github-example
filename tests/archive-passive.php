<?php

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Tests;

use RAN\BoosterGitHubExample\Plugin;
use RAN\BoosterGitHubExample\Tests\Support\ArchiveCurrentContext;
use RAN\BoosterGitHubExample\Tests\Support\ArchiveOldContext;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Exact synthetic WordPress bootstrap boundary; no installed site is used.
define( 'ABSPATH', '/synthetic-only/' );
$GLOBALS['ran_booster_github_example_hooks'] = array();
require __DIR__ . '/Support/ArchiveWordPress.php';
if ( 'missing-core' !== ( $argv[2] ?? '' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- The passive probe exercises the real expected host API version.
	define( 'RAN_BOOSTER_PROVIDER_API_VERSION', 15 );
	require __DIR__ . '/Support/ArchiveOldContext.php';
	require __DIR__ . '/Support/ArchiveCurrentContext.php';
	class_alias( 'missing-dependencies' === ( $argv[2] ?? '' ) ? ArchiveCurrentContext::class : ArchiveOldContext::class, 'RAN\RepositoryProvider\ProviderRegistrationContext' );
}
$ran_booster_github_example_root = $argv[1] ?? '';
require $ran_booster_github_example_root . '/ran-booster-github-example.php';
if ( array( 'ran_booster_register_providers', 'admin_notices' ) !== $GLOBALS['ran_booster_github_example_hooks'] ) {
	throw new \RuntimeException( 'Unexpected plugin hooks.' );
}
if ( class_exists( 'RAN\BoosterGitHubProvider\V1\GitHubProvider', false ) ) {
	throw new \RuntimeException( 'Passive entry executed the provider.' );
}
ob_start();
Plugin::availability_notice();
$ran_booster_github_example_notice   = ob_get_clean();
$ran_booster_github_example_expected = 'missing-dependencies' === ( $argv[2] ?? '' ) ? 'dependencies are missing' : 'requires compatible Booster';
if ( ! is_string( $ran_booster_github_example_notice ) || ! str_contains( $ran_booster_github_example_notice, $ran_booster_github_example_expected ) ) {
	throw new \RuntimeException( 'Missing host/dependency did not give a bounded unavailable notice.' );
}
