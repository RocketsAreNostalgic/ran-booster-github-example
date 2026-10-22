<?php
/** Remove only demonstrably owned example workflow state. */
declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! class_exists( \RAN\BoosterGitHubExample\Plugin::class, false ) ) {
	require_once __DIR__ . '/src/Plugin.php';
}
if ( ! \RAN\BoosterGitHubExample\Plugin::uninstall() ) {
	wp_die( 'GitHub Example cleanup could not establish complete ownership or removal. State has been retained where removal was uncertain.' );
}
