<?php

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Tests;

use RAN\BoosterGitHubExample\Plugin;

$ran_booster_github_example_root = $argv[1] ?? '';
require $ran_booster_github_example_root . '/src/Plugin.php';
$ran_booster_github_example_refused = false;
try {
	Plugin::load_dependencies();
} catch ( \RuntimeException $ran_booster_github_example_failure ) {
	$ran_booster_github_example_refused = str_contains( $ran_booster_github_example_failure->getMessage(), 'unqualified' );
}
if ( ! $ran_booster_github_example_refused || class_exists( 'RAN\BoosterGitHubProvider\V1\GitHubProvider', false ) ) {
	throw new \RuntimeException( 'Unqualified vendored bytes executed.' );
}
