<?php
/** Verify that the real consumer loads its installed candidate package, not Core's copy. */

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Tests;

use ReflectionClass;
use RuntimeException;
use RAN\BoosterGitHubExample\Plugin;
use RAN\BoosterGitHubProvider\V1\GitHubProvider;

$ran_booster_github_example_assembly = getenv( 'RAN_PROVIDER_CONSUMER_ASSEMBLY' );
if ( ! is_string( $ran_booster_github_example_assembly ) || ! is_file( $ran_booster_github_example_assembly . '/receipt.json' ) ) {
	throw new RuntimeException( 'An explicit prepared candidate assembly is required.' );
}
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read only the local candidate assembly receipt supplied to this standalone consumer probe.
$ran_booster_github_example_receipt = json_decode( (string) file_get_contents( $ran_booster_github_example_assembly . '/receipt.json' ), true, 8, JSON_THROW_ON_ERROR );
if ( true !== ( $ran_booster_github_example_receipt['candidate'] ?? null ) || false !== ( $ran_booster_github_example_receipt['published'] ?? null ) ) {
	throw new RuntimeException( 'Candidate evidence must remain separate from publication.' );
}
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standalone composition uses the host autoloader without booting WordPress or a database.
define( 'ABSPATH', __DIR__ . '/' );
require $ran_booster_github_example_receipt['host']['source_root'] . '/autoload.php';
require $ran_booster_github_example_receipt['example']['source_root'] . '/src/Plugin.php';
Plugin::load_dependencies();
$ran_booster_github_example_actual   = ( new ReflectionClass( GitHubProvider::class ) )->getFileName();
$ran_booster_github_example_expected = $ran_booster_github_example_receipt['example']['source_root'] . '/vendor/ran/booster-github-provider/src/GitHubProvider.php';
$ran_booster_github_example_source   = $ran_booster_github_example_receipt['provider']['source_root'] . '/src/GitHubProvider.php';
if ( $ran_booster_github_example_expected !== $ran_booster_github_example_actual || hash_file( 'sha256', $ran_booster_github_example_expected ) !== hash_file( 'sha256', $ran_booster_github_example_source ) ) {
	throw new RuntimeException( 'The Example did not load its actual candidate package bytes.' );
}
echo "Actual Example candidate package identity and dependency guards passed.\n";
