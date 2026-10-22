<?php

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Tests;

$ran_booster_github_example_core = getenv( 'RAN_BOOSTER_CORE_PATH' );
if ( ! is_string( $ran_booster_github_example_core ) || ! is_file( $ran_booster_github_example_core . '/RAN/RepositoryProvider/ProviderRegistry.php' ) ) {
	throw new \RuntimeException( 'Set RAN_BOOSTER_CORE_PATH to the exact supported Core source root.' );
}
$ran_booster_github_example_core = realpath( $ran_booster_github_example_core );
if ( false === $ran_booster_github_example_core ) {
	throw new \RuntimeException( 'The Core source root is unavailable.' );
}
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Define the WordPress host boundary for isolated tests.
define( 'ABSPATH', __DIR__ . '/' );
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Use the tested host API constant.
define( 'RAN_BOOSTER_PROVIDER_API_VERSION', (int) substr( 'api15', 3 ) );
require dirname( __DIR__ ) . '/vendor/autoload.php';
spl_autoload_register(
	static function ( string $symbol ) use ( $ran_booster_github_example_core ): void {
		foreach ( array(
			'RAN\\Tests\\'               => '/tests/',
			'RAN\\WPBranchUpdater\\V1\\' => '/vendor/ran/wp-branch-updater/src/',
			'RAN\\'                      => '/RAN/',
		) as $prefix => $directory ) {
			if ( ! str_starts_with( $symbol, $prefix ) ) {
				continue;
			}
			$file = $ran_booster_github_example_core . $directory . str_replace( '\\', '/', substr( $symbol, strlen( $prefix ) ) ) . '.php';
			if ( is_file( $file ) ) {
				require $file;
				return;
			}
		}
	}
);
require $ran_booster_github_example_core . '/tests/Deployment/AttemptRepositoryDatabase.php';
