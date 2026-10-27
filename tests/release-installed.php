<?php
/** Assert the passive wrapper contract on a clean, disposable WordPress install. */

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Tests;

use RAN\BoosterGitHubExample\Plugin;
use RuntimeException;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || '1' !== getenv( 'RAN_EXAMPLE_DISPOSABLE' ) ) {
	throw new RuntimeException( 'The installed probe requires the marked disposable CLI environment.' );
}

$ran_booster_github_example_expected = json_decode( (string) getenv( 'RAN_EXAMPLE_EXPECTED_ORDER' ), true, 4, JSON_THROW_ON_ERROR );
if ( ! is_array( $ran_booster_github_example_expected ) || get_option( 'active_plugins' ) !== $ran_booster_github_example_expected ) {
	throw new RuntimeException( 'The fresh request did not use the requested plugin load order.' );
}
if ( ! defined( 'RAN_BOOSTER_PROVIDER_API_VERSION' ) || 15 !== RAN_BOOSTER_PROVIDER_API_VERSION || ! class_exists( Plugin::class ) ) {
	throw new RuntimeException( 'The installed Core and Example public contract is unavailable.' );
}
// Both files are local fixture metadata, not remote requests.
$ran_booster_github_example_contract = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/host-contract.json' ), true, 8, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture metadata.
$ran_booster_github_example_marker   = json_decode( (string) file_get_contents( WP_PLUGIN_DIR . '/ran-booster/ran-booster-release.json' ), true, 8, JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local installed metadata.
if ( ! is_array( $ran_booster_github_example_marker ) || 'ran-booster-core-release' !== ( $ran_booster_github_example_marker['schema'] ?? null ) || 1 !== ( $ran_booster_github_example_marker['schema_version'] ?? null ) || ( $ran_booster_github_example_marker['commit'] ?? null ) !== $ran_booster_github_example_contract['commit'] || ( $ran_booster_github_example_marker['version'] ?? null ) !== $ran_booster_github_example_contract['fixture_version'] ) {
	throw new RuntimeException( 'The installed Core fixture provenance differs from the contract.' );
}
ob_start();
Plugin::availability_notice();
$ran_booster_github_example_notice = ob_get_clean();
if ( '' !== $ran_booster_github_example_notice ) {
	throw new RuntimeException( 'The exact installed composition reported unavailable dependencies.' );
}
// This validates both installed dependency copies without constructing a provider or reading credentials.
Plugin::load_dependencies();
