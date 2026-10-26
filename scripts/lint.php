<?php

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Scripts;

/** Recursively lint every maintained PHP file, including root entry points. */
function lint(): int {
	$root = dirname( __DIR__ );
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_passthru -- Run the fixed AST/shell lexical guard on local maintained helpers, without evaluating their source.
	passthru( 'python3 ' . escapeshellarg( __DIR__ . '/check-embedded-php.py' ) . ' ' . escapeshellarg( $root ), $status );
	if ( 0 !== $status ) {
		return $status;
	}
	$iterator = new \RecursiveIteratorIterator(
		new \RecursiveCallbackFilterIterator(
			new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ),
			static fn( \SplFileInfo $entry ): bool => ! $entry->isDir() || ! in_array( substr( $entry->getPathname(), strlen( $root ) + 1 ), array( 'vendor', 'dist', '.git', '.phpunit.cache', '.phpstan' ), true )
		)
	);
	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}
		$path = $file->getPathname();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Discover nonstandard local PHP entrypoints without executing the source.
		$source = file_get_contents( $path );
		if ( false === $source || is_link( $path ) ) {
			throw new \RuntimeException( 'Unreadable or symlinked maintained source requires review.' );
		}
		$extension   = strtolower( $file->getExtension() );
		$data        = in_array( $extension, array( 'md', 'py' ), true ) || ( 'json' === $extension && null !== json_decode( $source ) ) || ( 'sh' === $extension && str_starts_with( $source, '#!' ) );
		$without_xml = preg_replace( '~\A(?:\xEF\xBB\xBF)?<\?xml\s+version\s*=\s*[\x22\x27]1\.[01][\x22\x27](?:\s+encoding\s*=\s*[\x22\x27][A-Za-z0-9._-]+[\x22\x27])?\s*\?>~', '', $source ) ?? $source;
		if ( ! in_array( $extension, array( 'php', 'phtml' ), true ) && 1 !== preg_match( $data ? '/\A(?:\xEF\xBB\xBF)?(?:#![^\n]*\n)?\s*<\?/' : '/<\?/', $without_xml ) ) {
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
