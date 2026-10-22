<?php

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Scripts;

/** Recursively lint every maintained PHP file, including root entry points. */
function lint(): int {
	$root     = dirname( __DIR__ );
	$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ) );
	foreach ( $iterator as $file ) {
		$path     = $file->getPathname();
		$relative = substr( $path, strlen( $root ) + 1 );
		if ( 1 === preg_match( '#\A(?:vendor|dist|\.git|\.phpunit\.cache|\.phpstan)/#', $relative ) || 'php' !== $file->getExtension() ) {
			continue;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_passthru -- Standalone local build tooling uses PHP directly without a WordPress runtime; paths are repository-owned and shell arguments escaped.
		passthru( escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $path ), $status );
		if ( 0 !== $status ) {
			return $status;
		}
	}
	return 0;
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Standalone local build tooling uses PHP directly without a WordPress runtime; paths are repository-owned and shell arguments escaped.
exit( lint() );
