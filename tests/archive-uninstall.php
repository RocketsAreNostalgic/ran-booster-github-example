<?php

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Tests;

use RAN\BoosterGitHubExample\Plugin;
use RAN\BoosterGitHubExample\Tests\Support\UninstallDatabase;

$ran_booster_github_example_root = $argv[1] ?? '';
if ( ! is_file( dirname( $ran_booster_github_example_root ) . '/RAN-EXAMPLE-DISPOSABLE' ) || 'ran-booster-github-example' !== basename( $ran_booster_github_example_root ) || is_link( $ran_booster_github_example_root ) ) {
	throw new \RuntimeException( 'Refuse any unmarked or unresolved fixture root.' );
}
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Exact WordPress uninstall invocation boundary under test.
define( 'WP_UNINSTALL_PLUGIN', 'wrong-invocation' === ( $argv[2] ?? '' ) ? 'unrelated/unrelated.php' : basename( $ran_booster_github_example_root ) . '/' . basename( $ran_booster_github_example_root ) . '.php' );
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Exact resolved disposable plugin root under test.
define( 'WP_PLUGIN_DIR', 'wrong-root' === ( $argv[2] ?? '' ) ? dirname( $ran_booster_github_example_root ) . '/unrelated' : dirname( $ran_booster_github_example_root ) );
require __DIR__ . '/Support/StorageFunctions.php';
require __DIR__ . '/Support/UninstallDatabase.php';
require $ran_booster_github_example_root . '/src/Plugin.php';
$GLOBALS['ran_booster_github_example_options'] = array_fill_keys(
	array(
		'ran_booster_gh_wf_github_example_setup_records',
		'ran_booster_github_provider_release_workflow_setup_records',
		'ran_booster_github_provider_release_workflow_assessment_observations',
		'ran_booster_github_provider_release_workflow_failure_history',
		'ran_booster_release_deployments_setup_records',
		'ran_booster_gh_wf_another_setup_records',
		'ran_booster_activity',
	),
	array( 'synthetic' => true )
);
$GLOBALS['ran_booster_github_example_options']['ran_booster_gh_wf_github_example_preview_keys'] = array( str_repeat( 'a', 32 ) => time() + 60 );
$GLOBALS['ran_booster_github_example_transients'] = array(
	'ran_booster_gh_wf_github_example_preview_' . str_repeat( 'a', 32 ) => 'owned',
	'ran_booster_github_provider_release_workflow_preview_' . str_repeat( 'a', 32 ) => 'bundled',
	'ran_booster_gh_wf_another_preview_' . str_repeat( 'a', 32 ) => 'another',
);
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Replace only this isolated process's database with a no-network synthetic boundary.
$GLOBALS['wpdb'] = new UninstallDatabase();
// Preload the byte-identical state from a second physical copy to detect redeclaration regressions.
if ( in_array( $argv[2] ?? '', array( 'preloaded', 'modified-copy' ), true ) ) {
	require dirname( $ran_booster_github_example_root ) . '/qualified-state.php';
}
$ran_booster_github_example_result = Plugin::uninstall();
if ( in_array( $argv[2] ?? '', array( 'modified-copy', 'wrong-invocation', 'wrong-root' ), true ) ) {
	if ( $ran_booster_github_example_result || 8 !== count( $GLOBALS['ran_booster_github_example_options'] ) || 3 !== count( $GLOBALS['ran_booster_github_example_transients'] ) || array() !== $GLOBALS['wpdb']->deleted ) {
		throw new \RuntimeException( 'Unproven ownership must refuse without modifying any state.' );
	}
	exit( 0 );
}
if ( ! $ran_booster_github_example_result ) {
	throw new \RuntimeException( 'Owned example cleanup failed.' );
}
if ( 6 !== count( $GLOBALS['ran_booster_github_example_options'] ) || 2 !== count( $GLOBALS['ran_booster_github_example_transients'] ) || array( 'ran_booster_gh_wf_github_example_preview_keys' ) !== $GLOBALS['wpdb']->deleted ) {
	throw new \RuntimeException( 'Cleanup crossed a namespace or retained owned state.' );
}
if ( class_exists( 'RAN\\BoosterGitHubProvider\\V1\\GitHubProvider', false ) || class_exists( 'RAN\\RepositoryProvider\\ProviderRegistry', false ) ) {
	throw new \RuntimeException( 'Standalone uninstall loaded a provider factory or Core.' );
}
