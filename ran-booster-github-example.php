<?php
/**
 * Plugin Name: RAN Booster — GitHub Example
 * Description: Optional developer example; GitHub support is already bundled with Booster.
 * x-release-please-start-version
 * Version: 0.2.2
 * x-release-please-end
 * Requires at least: 7.0
 * Requires PHP: 8.2
 * Requires Plugins: ran-booster
 * Update URI: https://github.com/RocketsAreNostalgic/ran-booster-github-example
 * Author: Rockets Are Nostalgic
 * License: GPL-2.0-or-later
 * Text Domain: ran-booster-github-example
 * RAN Booster GitHub Replacement: 1
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( \RAN\BoosterGitHubExample\Plugin::class, false ) ) {
	require_once __DIR__ . '/src/Plugin.php';
}

add_action( 'ran_booster_register_providers', array( \RAN\BoosterGitHubExample\Plugin::class, 'declare' ) );
add_action( 'admin_notices', array( \RAN\BoosterGitHubExample\Plugin::class, 'availability_notice' ) );
