<?php

declare(strict_types=1);

/** Synthetic WordPress hooks recorded by the standalone passive archive probe. */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Preserve WordPress's actual hook-registration boundary in this isolated no-site probe.
function add_action( string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1 ): bool {
	if ( 10 !== $priority || 1 !== $accepted_args ) {
		throw new \RuntimeException( 'Unexpected passive hook contract.' );
	}
	$GLOBALS['ran_booster_github_example_hooks'][]     = $hook_name;
	$GLOBALS['ran_booster_github_example_callbacks'][] = $callback;
	return true;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Preserve WordPress's actual capability boundary in this isolated no-site probe.
function current_user_can( string $capability, mixed ...$args ): bool {
	return 'activate_plugins' === $capability && array() === $args;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Preserve WordPress's actual escaping boundary in this isolated no-site probe.
function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES );
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Preserve WordPress's actual translation boundary in this isolated no-site probe.
function __( string $text, string $domain = 'default' ): string {
	if ( 'ran-booster-github-example' !== $domain ) {
		throw new \RuntimeException( 'The archive notice must use its own translation domain.' );
	}
	return $text;
}
