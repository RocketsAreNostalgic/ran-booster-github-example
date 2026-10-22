<?php

declare(strict_types=1);

/** @return mixed */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Isolated WordPress option API fixture.
function get_option( string $option, mixed $default_value = false ): mixed {
	return $GLOBALS['ran_booster_github_example_options'][ $option ] ?? $default_value;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Isolated WordPress option API fixture.
function delete_option( string $option ): bool {
	unset( $GLOBALS['ran_booster_github_example_options'][ $option ] );
	return true;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Isolated WordPress transient API fixture.
function get_transient( string $transient ): mixed {
	return $GLOBALS['ran_booster_github_example_transients'][ $transient ] ?? false;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Isolated WordPress transient API fixture.
function delete_transient( string $transient ): bool {
	unset( $GLOBALS['ran_booster_github_example_transients'][ $transient ] );
	return true;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Isolated WordPress cache API fixture.
function wp_cache_delete( int|string $key, string $group = '' ): bool {
	$GLOBALS['ran_booster_github_example_cache_deletes'][] = array( $key, $group );
	return true;
}
