<?php

declare(strict_types=1);

namespace RAN\BoosterGitHubExample;

use RAN\BoosterGitHubProvider\V1\GitHubProvider;
use RAN\BoosterGitHubProvider\V1\ReleaseDeployments\WorkflowAssistance\WorkflowAssistanceState;
use RAN\RepositoryProvider\AuthenticatedWebhookDeliveryEvidenceReader;
use RAN\RepositoryProvider\GitHubReplacementDeclaration;
use RAN\RepositoryProvider\ProviderCredentialStore;
use RAN\RepositoryProvider\ProviderRegistrationContext;
use RAN\RepositoryProvider\ProviderRegistry;
use RAN\RepositoryProvider\RepositoryProvider;
use ReflectionClass;
use RuntimeException;

/** One composition root; Core owns provider review, selection and credentials. */
final class Plugin {
	public const BASENAME           = 'ran-booster-github-example/ran-booster-github-example.php';
	public const WORKFLOW_NAMESPACE = 'github_example';
	private const PACKAGES          = array(
		'ran/booster-github-provider' => 'RAN\\BoosterGitHubProvider\\V1\\',
		'ran/wp-release-updater'      => 'RAN\\WPReleaseUpdater\\V1\\',
		'ran/updater-support'         => 'RAN\\UpdaterSupport\\V1\\',
	);

	/** Register only passive metadata and an exact, delayed three-input factory. */
	public static function declare( ProviderRegistry $registry ): void {
		if ( null !== self::availability_error() ) {
			return;
		}
		try {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the adjacent bounded passive manifest without executing provider code or reading credentials.
			$json     = file_get_contents( dirname( __DIR__ ) . '/ran-booster-github-example.gh-replacement.json', false, null, 0, 16385 );
			$manifest = is_string( $json ) && strlen( $json ) <= 16384 ? json_decode( $json, true, 8, JSON_THROW_ON_ERROR ) : null;
			if ( ! is_array( $manifest ) || array_is_list( $manifest ) ) {
				return;
			}
			$registry->declare_github_replacement( new GitHubReplacementDeclaration( $manifest ), self::create( ... ) );
		} catch ( \Throwable ) {
			// Core's passive discovery retains an invalid candidate as unavailable.
			return;
		}
	}

	/** Core invokes this factory only after exact implementation and credential consent. */
	public static function create(
		ProviderCredentialStore $credentials,
		AuthenticatedWebhookDeliveryEvidenceReader $delivery_evidence,
		ProviderRegistrationContext $context
	): RepositoryProvider {
		if ( null !== self::availability_error() || ! $context->operation_allowed() ) {
			throw new RuntimeException( 'The GitHub Example host contract is unavailable.' );
		}
		self::load_dependencies();
		// The updater's existing public bootstrap selects and verifies its runtime copy.
		$registrar = require dirname( __DIR__ ) . '/vendor/ran/wp-release-updater/bootstrap.php';
		if ( ! is_object( $registrar ) ) {
			throw new RuntimeException( 'The GitHub Example updater is unavailable.' );
		}
		return GitHubProvider::create(
			$credentials,
			$delivery_evidence,
			$registrar,
			$context->maximum_artifact_bytes( ... ),
			$context->operation_allowed( ... ),
			$context->begin_native_mutation( ... ),
			$context->complete_native_mutation( ... ),
			self::WORKFLOW_NAMESPACE
		);
	}

	/** Refuse mixed dependency bytes before executing any selected provider factory. */
	public static function load_dependencies(): void {
		$qualified = self::qualified_packages();
		self::assert_loaded_copies( $qualified );
		$loader = require dirname( __DIR__ ) . '/vendor/autoload.php';
		if ( ! $loader instanceof \Composer\Autoload\ClassLoader ) {
			throw new RuntimeException( 'The GitHub Example Composer loader is unavailable.' );
		}
		$loader->unregister();
		$loader->register( true );
		// Resolve every provider class now, using this qualified Composer loader.
		// No provider constructor, credential read or HTTP operation is performed.
		foreach ( array_keys( $qualified['ran/booster-github-provider']['files'] ) as $path ) {
			if ( ! str_starts_with( $path, 'src/' ) || ! str_ends_with( $path, '.php' ) ) {
				continue;
			}
			$symbol = self::PACKAGES['ran/booster-github-provider'] . str_replace( '/', '\\', substr( $path, 4, -4 ) );
			if ( ! class_exists( $symbol, false ) && ! interface_exists( $symbol, false ) && ! trait_exists( $symbol, false ) ) {
				require_once dirname( __DIR__ ) . '/vendor/ran/booster-github-provider/' . $path;
			}
			if ( ! class_exists( $symbol, false ) && ! interface_exists( $symbol, false ) && ! trait_exists( $symbol, false ) ) {
				throw new RuntimeException( 'The qualified GitHub provider is unavailable.' );
			}
		}
		self::assert_loaded_copies( $qualified );
	}

	/** Deactivation deliberately has no cleanup hook. */
	public static function uninstall(): bool {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || self::BASENAME !== WP_UNINSTALL_PLUGIN
			|| ! defined( 'WP_PLUGIN_DIR' ) || is_link( WP_PLUGIN_DIR . '/ran-booster-github-example' )
			|| realpath( WP_PLUGIN_DIR . '/' . self::BASENAME ) !== realpath( dirname( __DIR__ ) . '/ran-booster-github-example.php' ) ) {
			return false;
		}
		try {
			$qualified = self::qualified_packages();
			self::assert_loaded_copies( $qualified );
			// Cleanup needs only the standalone state owner, never Core or a provider factory.
			if ( ! class_exists( WorkflowAssistanceState::class, false ) ) {
				require_once dirname( __DIR__ ) . '/vendor/ran/booster-github-provider/src/ReleaseDeployments/WorkflowAssistance/WorkflowAssistanceState.php';
			}
			$state = new WorkflowAssistanceState( self::WORKFLOW_NAMESPACE );
			return $state->remove_ephemeral_state() && $state->remove_durable_state();
		} catch ( \Throwable ) {
			return false;
		}
	}

	public static function availability_notice(): void {
		$error = self::availability_error();
		if ( null !== $error && current_user_can( 'activate_plugins' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( $error ) . '</p></div>';
		}
	}

	private static function availability_error(): ?string {
		if ( ! defined( 'RAN_BOOSTER_PROVIDER_API_VERSION' ) || 15 !== RAN_BOOSTER_PROVIDER_API_VERSION
			|| ! class_exists( ProviderRegistrationContext::class )
			|| array() !== array_diff( array( 'begin_native_mutation', 'complete_native_mutation' ), get_class_methods( ProviderRegistrationContext::class ) ) ) {
			return 'RAN Booster — GitHub Example requires compatible Booster Provider API 15 with native update checkpoints. GitHub support is already bundled with Booster.';
		}
		if ( ! is_file( dirname( __DIR__ ) . '/vendor/autoload.php' ) || ! is_file( dirname( __DIR__ ) . '/qualified-provider.json' ) || ! is_file( dirname( __DIR__ ) . '/ran-booster-github-example.gh-replacement.json' ) ) {
			return 'RAN Booster — GitHub Example dependencies are missing. Install a complete built ZIP; GitHub support is already bundled with Booster.';
		}
		return null;
	}

	/** @return array<string,array{reference:string,files:array<string,string>}> */
	private static function qualified_packages(): array {
		$manifest_path = dirname( __DIR__ ) . '/qualified-provider.json';
		if ( ! is_file( $manifest_path ) || ! is_readable( $manifest_path ) || is_link( $manifest_path ) ) {
			throw new RuntimeException( 'The GitHub Example dependency qualification is unavailable.' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read only bounded local build qualification metadata; it contains no credentials.
		$json = file_get_contents( $manifest_path, false, null, 0, 65537 );
		$data = is_string( $json ) && strlen( $json ) <= 65536 ? json_decode( $json, true, 8, JSON_THROW_ON_ERROR ) : null;
		if ( ! is_array( $data ) || array_keys( $data ) !== array( 'schema', 'packages' ) || 1 !== $data['schema']
			|| ! is_array( $data['packages'] ) || array_keys( self::PACKAGES ) !== array_keys( $data['packages'] ) ) {
			throw new RuntimeException( 'The GitHub Example dependency qualification is unavailable.' );
		}
		$validated = array();
		foreach ( array_keys( self::PACKAGES ) as $package ) {
			$qualification = $data['packages'][ $package ];
			if ( ! is_array( $qualification ) || array_keys( $qualification ) !== array( 'reference', 'files' )
				|| ! is_string( $qualification['reference'] ) || 1 !== preg_match( '/\A[a-f0-9]{40}\z/D', $qualification['reference'] )
				|| ! is_array( $qualification['files'] ) || array() === $qualification['files'] || count( $qualification['files'] ) > 300 ) {
				throw new RuntimeException( 'The GitHub Example dependency qualification is invalid.' );
			}
			$root  = dirname( __DIR__ ) . '/vendor/' . $package . '/';
			$files = array();
			foreach ( $qualification['files'] as $path => $hash ) {
				if ( ! is_string( $path ) || 1 !== preg_match( '#\A[a-zA-Z0-9][a-zA-Z0-9_./-]*\z#D', $path )
					|| str_contains( $path, '..' ) || ! is_string( $hash ) || 1 !== preg_match( '/\A[a-f0-9]{64}\z/D', $hash )
					|| ! is_file( $root . $path ) || is_link( $root . $path ) || realpath( $root . $path ) !== $root . $path
					|| hash_file( 'sha256', $root . $path ) !== $hash ) {
					throw new RuntimeException( 'The GitHub Example dependency bytes are unqualified.' );
				}
				$files[ $path ] = $hash;
			}
			$validated[ $package ] = array(
				'reference' => $qualification['reference'],
				'files'     => $files,
			);
		}
		return $validated;
	}

	/** @param array<string,array{reference:string,files:array<string,string>}> $qualified */
	private static function assert_loaded_copies( array $qualified ): void {
		foreach ( array_merge( get_declared_classes(), get_declared_interfaces(), get_declared_traits() ) as $symbol ) {
			foreach ( self::PACKAGES as $package => $prefix ) {
				if ( 0 !== strncasecmp( $symbol, $prefix, strlen( $prefix ) ) ) {
					continue;
				}
				$reflection = new ReflectionClass( $symbol );
				$name       = $reflection->getName();
				$path       = 'src/' . str_replace( '\\', '/', substr( $name, strlen( $prefix ) ) ) . '.php';
				$file       = $reflection->getFileName();
				$expected   = $qualified[ $package ]['files'][ $path ] ?? null;
				if ( ! is_string( $file ) || null === $expected || ! is_file( $file ) || ! is_readable( $file ) || hash_file( 'sha256', $file ) !== $expected ) {
					throw new RuntimeException( 'An incompatible GitHub Example dependency copy is already loaded.' );
				}
			}
		}
	}
}
