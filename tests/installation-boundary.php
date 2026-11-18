<?php
/** Verify the newly created WordPress target before either plugin is installed. */

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Tests;

use RuntimeException;

$ran_booster_github_example_site = realpath( (string) getenv( 'RAN_EXAMPLE_INSTALL_ROOT' ) );
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || false === $ran_booster_github_example_site
	|| is_link( (string) getenv( 'RAN_EXAMPLE_INSTALL_ROOT' ) )
	|| ! is_file( $ran_booster_github_example_site . '/RAN-EXAMPLE-DISPOSABLE' )
	|| ! is_file( $ran_booster_github_example_site . '/.ran-booster-disposable-test-site' )
	|| rtrim( ABSPATH, '/' ) !== $ran_booster_github_example_site
	|| WP_CONTENT_DIR !== $ran_booster_github_example_site . '/wp-content'
	|| WP_PLUGIN_DIR !== WP_CONTENT_DIR . '/plugins'
	|| realpath( WP_PLUGIN_DIR ) !== WP_PLUGIN_DIR
	|| 'http://localhost' !== get_option( 'siteurl' )
	|| 'http://localhost' !== get_option( 'home' ) ) {
	throw new RuntimeException( 'Refuse an unmarked or unexpected WordPress installation target.' );
}
foreach ( array( 'ran-booster', 'ran-booster-github-example' ) as $ran_booster_github_example_slug ) {
	if ( file_exists( WP_PLUGIN_DIR . '/' . $ran_booster_github_example_slug ) || is_link( WP_PLUGIN_DIR . '/' . $ran_booster_github_example_slug ) ) {
		throw new RuntimeException( 'The disposable plugin installation target must be absent.' );
	}
}
echo "Fresh marked WordPress root, content directory, site URL and plugin targets verified.\n";
