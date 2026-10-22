<?php

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Scripts;

/** Generate a bounded immutable file digest set from Composer's exact lock. */
function qualify_dependencies( bool $check ): void {
	$root = dirname( __DIR__ );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Standalone local build tooling uses PHP directly without a WordPress runtime; paths are repository-owned and shell arguments escaped.
	$lock     = json_decode( (string) file_get_contents( $root . '/composer.lock' ), true, 512, JSON_THROW_ON_ERROR );
	$packages = array();
	foreach ( $lock['packages'] as $package ) {
		$packages[ $package['name'] ] = $package;
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Composer's local installed metadata must match the immutable lock reference before attributing any source bytes.
	$installed_data = json_decode( (string) file_get_contents( $root . '/vendor/composer/installed.json' ), true, 512, JSON_THROW_ON_ERROR );
	$installed      = array();
	if ( ! is_array( $installed_data ) || ! is_array( $installed_data['packages'] ?? null ) ) {
		throw new \RuntimeException( 'Composer installed metadata is unavailable.' );
	}
	foreach ( $installed_data['packages'] as $package ) {
		if ( is_array( $package ) && is_string( $package['name'] ?? null ) ) {
			$installed[ $package['name'] ] = $package;
		}
	}
	$data = array(
		'schema'   => 1,
		'packages' => array(),
	);
	foreach ( array( 'ran/booster-github-provider', 'ran/wp-release-updater', 'ran/updater-support' ) as $name ) {
		$package = $packages[ $name ] ?? null;
		if ( ! is_array( $package ) || ! is_string( $package['source']['reference'] ?? null ) ) {
			throw new \RuntimeException( 'Missing locked runtime package.' );
		}
		$actual = $installed[ $name ] ?? null;
		if ( ! is_array( $actual ) || ( $actual['source']['reference'] ?? null ) !== $package['source']['reference'] || ( $actual['dist']['reference'] ?? null ) !== $package['source']['reference'] || ( $actual['version'] ?? null ) !== ( $package['version'] ?? null ) ) {
			throw new \RuntimeException( 'Installed runtime package does not match its exact Composer lock.' );
		}
		$package_root = $root . '/vendor/' . $name;
		$paths        = array( 'composer.json' );
		$iterator     = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $package_root . '/src', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( $file->isLink() || ! $file->isFile() || 'php' !== $file->getExtension() ) {
				throw new \RuntimeException( 'Unexpected runtime source path.' );
			}
			$paths[] = substr( $file->getPathname(), strlen( $package_root ) + 1 );
		}
		if ( 'ran/wp-release-updater' === $name ) {
			$paths = array_merge( $paths, array( 'bootstrap.php', 'runtime.php', 'runtime-copy.json' ) );
		}
		if ( count( $paths ) > 300 ) {
			throw new \RuntimeException( 'Runtime qualification exceeds its bound.' );
		}
		sort( $paths, SORT_STRING );
		$files = array();
		foreach ( $paths as $path ) {
			$hash = hash_file( 'sha256', $package_root . '/' . $path );
			if ( false === $hash ) {
				throw new \RuntimeException( 'Missing runtime source bytes.' );
			}
			$files[ $path ] = $hash;
		}
		$data['packages'][ $name ] = array(
			'reference' => $package['source']['reference'],
			'files'     => $files,
		);
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Standalone local build tooling uses PHP directly without a WordPress runtime; paths are repository-owned and shell arguments escaped.
	$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
	if ( strlen( $json ) > 65536 ) {
		throw new \RuntimeException( 'Runtime qualification exceeds its byte bound.' );
	}
	$target = $root . '/qualified-provider.json';
	if ( $check ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Standalone local build tooling uses PHP directly without a WordPress runtime; paths are repository-owned and shell arguments escaped.
		if ( ! is_file( $target ) || file_get_contents( $target ) !== $json ) {
			throw new \RuntimeException( 'Dependency qualification differs from the locked installed bytes.' );
		}
		return;
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Standalone local build tooling uses PHP directly without a WordPress runtime; paths are repository-owned and shell arguments escaped.
	file_put_contents( $target, $json );
}

qualify_dependencies( in_array( '--check', $argv ?? array(), true ) );
