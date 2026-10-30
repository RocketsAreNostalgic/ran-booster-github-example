<?php
/** Real WordPress consumer proof for separately identified packaged assemblies. */

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Tests\Consumer;

use RAN\AddOn\ReleaseTracking\ProspectiveReleaseFacade;
use RAN\BoosterGitHubExample\Plugin;
use RAN\BoosterGitHubProvider\V1\GitHubProvider;
use RAN\Deployment\DeploymentAttemptRepository;
use RAN\RepositoryProvider\GitHubProviderSelection;
use RAN\RepositoryProvider\ProviderRegistry;
use RAN\Secrets\SecretsFile;
use RAN\WordPress\WordPressUpdaterLock;
use ReflectionClass;
use RuntimeException;

/** Runs only in fresh processes belonging to the disposable shell runner. */
final class Installed {
	private const BASENAME   = 'ran-booster-github-example/ran-booster-github-example.php';
	private const REPOSITORY = 'RocketsAreNostalgic/ran-booster-github-example';

	private static function check( bool $condition, string $message ): void {
		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}
	}

	/** @return array<string, mixed> */
	private static function receipt(): array {
		$path = (string) getenv( 'RAN_CONSUMER_RECEIPT' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the local preparation receipt; no network transport is involved.
		$value = json_decode( (string) file_get_contents( $path ), true, 32, JSON_THROW_ON_ERROR );
		if ( ! is_array( $value ) ) {
			throw new RuntimeException( 'Invalid candidate preparation receipt.' );
		}
		return $value;
	}

	public static function run( string $phase ): void {
		$site = realpath( (string) getenv( 'RAN_BOOSTER_WORDPRESS_PATH' ) );
		self::check( defined( 'WP_CLI' ) && WP_CLI && '1' === getenv( 'RAN_PROVIDER_CONSUMER_DISPOSABLE' ) && false !== $site && rtrim( ABSPATH, '/' ) === $site, 'Exact disposable CLI site required.' );
		self::check( current_user_can( 'manage_options' ) && current_user_can( 'activate_plugins' ) && current_user_can( 'install_plugins' ), 'Disposable administrator required.' );
		$receipt        = self::receipt();
		$published_host = '1' === getenv( 'RAN_EXAMPLE_PUBLISHED_CONSUMER_PROOF' );
		if ( $published_host ) {
			self::check( true === ( $receipt['published_host'] ?? null ) && false === ( $receipt['candidate'] ?? null ) && false === ( $receipt['published'] ?? null ), 'Published host evidence must not claim candidate substitution or Example publication.' );
			self::check( hash_file( 'sha256', $receipt['host']['archive'] ) === $receipt['host']['sha256'], 'Published Core ZIP receipt mismatch.' );
			self::verify_archive( $receipt['host']['archive'], 'ran-booster' );
		}
		$host = $receipt['host']['source_root'];
		require_once $host . '/tests/fixtures/github-replacement/fixture.php';
		\RAN_Booster_GitHubReplacementFixture\Fixture::guard();
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$container = require $host . '/tests/WordPress/core-container-fixture.php';
		$selection = $container->make( GitHubProviderSelection::class );
		if ( 'refuse' === $phase ) {
			self::check( $published_host, 'The installed refusal belongs to published-host qualification.' );
			require __DIR__ . '/incompatible-updater.php';
			$refused = false;
			try {
				Plugin::load_dependencies();
			} catch ( RuntimeException $failure ) {
				$refused = str_contains( $failure->getMessage(), 'incompatible' );
			}
			self::check( $refused, 'The installed Example admitted an unqualified updater namespace copy.' );
			self::verify_archive( $receipt['host']['archive'], 'ran-booster' );
			self::verify_archive( $receipt['example']['archive'] );
			return;
		}
		if ( 'prepare' === $phase ) {
			$secrets = $container->make( SecretsFile::class );
			$storage = ( new \RAN\Secrets\SecretsStorageProvisioner( secrets: $secrets ) )->status();
			self::check( \RAN\Secrets\SecretsStorageProvisioningResult::PATH_CONFIGURED === $storage->status(), 'Fresh private storage required: ' . $storage->code() );
			$secrets->assert_managed_storage_ready();
			$review = $selection->adoption_review( $secrets );
			self::check( 'bundled' === $review['selected'] && ! $review['has_material'] && array() === $review['profiles'], 'Fresh bundled adoption required.' );
			$selection->transition_selection( $review['digest'], true, false, false, $secrets, $container->make( DeploymentAttemptRepository::class ), $container->make( WordPressUpdaterLock::class ), static fn (): bool => current_user_can( 'manage_options' ) && current_user_can( 'activate_plugins' ) );
			return;
		}
		if ( 'activate' === $phase ) {
			self::check( ! is_wp_error( activate_plugin( self::BASENAME ) ), 'Native Example activation failed.' );
			return;
		}
		if ( 'adopt' === $phase || 'restore' === $phase ) {
			$secrets = $container->make( SecretsFile::class );
			$review  = $selection->adoption_review( $secrets );
			self::check( ( 'adopt' === $phase ? 'replacement' : 'bundled' ) === $review['selected'] && ! $review['has_material'] && array() === $review['profiles'], 'Exact credential-free adoption target required.' );
			if ( 'adopt' === $phase ) {
				self::check( self::BASENAME === ( $review['target']['plugin_basename'] ?? null ), 'Exact Example declaration required.' );
			}
			$selection->transition_selection( $review['digest'], true, false, false, $secrets, $container->make( DeploymentAttemptRepository::class ), $container->make( WordPressUpdaterLock::class ), static fn (): bool => current_user_can( 'manage_options' ) && current_user_can( 'activate_plugins' ) );
			return;
		}
		$registry = $container->make( ProviderRegistry::class );
		$provider = $registry->get( 'gh' );
		self::check( $provider instanceof GitHubProvider, 'Actual GitHub provider required.' );
		$source = ( new ReflectionClass( $provider ) )->getFileName();
		$copies = array( WP_PLUGIN_DIR . '/ran-booster/vendor/ran/booster-github-provider', WP_PLUGIN_DIR . '/ran-booster-github-example/vendor/ran/booster-github-provider' );
		self::check( is_string( $source ) && in_array( $source, array_map( static fn ( string $copy ): string => $copy . '/src/GitHubProvider.php', $copies ), true ), 'Provider must load from an installed candidate package.' );
		foreach ( $copies as $copy ) {
			if ( ! is_dir( $copy ) ) {
				self::check( 'install' === $phase, 'Both installed package copies required.' );
				continue;
			}
			// Core's reviewed runtime allowlist ships package source without Composer metadata.
			if ( str_starts_with( $copy, WP_PLUGIN_DIR . '/ran-booster-github-example/' ) ) {
				self::check( hash_file( 'sha256', $copy . '/composer.json' ) === $receipt['provider']['manifest_sha256'], 'Installed provider manifest differs from the candidate.' );
			}
			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $receipt['provider']['source_root'] . '/src', \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				self::check( ! $file->isLink(), 'Candidate source symlinks are forbidden.' );
				if ( $file->isFile() ) {
					$relative = substr( $file->getPathname(), strlen( $receipt['provider']['source_root'] ) + 1 );
					self::check( is_file( $copy . '/' . $relative ) && hash_file( 'sha256', $file->getPathname() ) === hash_file( 'sha256', $copy . '/' . $relative ), 'Installed provider differs from exact candidate source.' );
				}
			}
		}
		if ( 'install' === $phase ) {
			self::install( $container->make( ProspectiveReleaseFacade::class ), $receipt );
			return;
		}
		self::check( 'exercise' === $phase, 'Unknown consumer proof phase.' );
		$status = $selection->status();
		self::check( $status['available'] && 'replacement' === ( $status['current']['selected'] ?? null ) && self::BASENAME === ( $status['current']['declaration']['plugin_basename'] ?? null ), 'Explicitly adopted Example must supply GitHub.' );
		Plugin::load_dependencies();
		self::check( \Composer\InstalledVersions::getReference( 'ran/booster-github-provider' ) === $receipt['provider']['commit'], 'Composer reference must identify the exact Provider candidate.' );
		ob_start();
		Plugin::availability_notice();
		self::check( '' === ob_get_clean(), 'Installed dependencies are unavailable.' );
		$expected = json_decode( (string) getenv( 'RAN_EXAMPLE_EXPECTED_ORDER' ), true, 4, JSON_THROW_ON_ERROR );
		self::check( get_option( 'active_plugins' ) === $expected, 'Fresh request used a different plugin load order.' );
		if ( $published_host ) {
			self::check_shared_updater();
		}
		self::install( $container->make( ProspectiveReleaseFacade::class ), $receipt, false );
	}

	/** Prove both installed beta.15 copies share the active protocol-7 runtime. */
	private static function check_shared_updater(): void {
		$relative = '/vendor/ran/wp-release-updater/';
		$core     = WP_PLUGIN_DIR . '/ran-booster' . $relative;
		$example  = WP_PLUGIN_DIR . '/ran-booster-github-example' . $relative;
		foreach ( array( $core, $example ) as $copy ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read immutable installed dependency metadata for the disposable composition proof.
			$metadata = json_decode( (string) file_get_contents( $copy . 'runtime-copy.json' ), true, 8, JSON_THROW_ON_ERROR );
			self::check( 7 === ( $metadata['runtime_protocol'] ?? null ) && '1.0.0-beta.15' === ( $metadata['package_version'] ?? null ), 'Installed updater must be genuine beta.15 / protocol 7.' );
		}
		foreach ( array( 'bootstrap.php', 'runtime.php', 'runtime-copy.json' ) as $file ) {
			self::check( hash_file( 'sha256', $core . $file ) === hash_file( 'sha256', $example . $file ), 'Installed shared updater bytes differ.' );
		}
		$broker    = $GLOBALS['ran_wp_release_updater_v1_broker'] ?? null;
		$registrar = require $example . 'bootstrap.php';
		if ( ! is_object( $registrar ) || ! is_callable( array( $registrar, 'diagnostics' ) ) ) {
			throw new RuntimeException( 'Installed updater registrar is unavailable.' );
		}
		$diagnostics = $registrar->diagnostics();
		self::check( is_object( $broker ) && ( $GLOBALS['ran_wp_release_updater_v1_broker'] ?? null ) === $broker && 7 === ( $diagnostics['protocol_version'] ?? null ) && 'active' === ( $diagnostics['state'] ?? null ) && 1 <= ( $diagnostics['candidate_count'] ?? 0 ), 'Both installed copies must share an active protocol-7 broker.' );
	}

	/** Verify every installed payload file against the archive passed to the updater. */
	private static function verify_archive( string $archive, string $slug = 'ran-booster-github-example' ): void {
		$zip = new \ZipArchive();
		self::check( true === $zip->open( $archive ), 'Cannot reopen the admitted archive.' );
		$expected = array();
		try {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive exposes this native property with its documented foreign name.
			for ( $index = 0; $index < $zip->numFiles; ++$index ) {
				$name = $zip->getNameIndex( $index );
				if ( ! is_string( $name ) || str_ends_with( $name, '/' ) ) {
					continue;
				}
				self::check( str_starts_with( $name, $slug . '/' ) && ! str_contains( $name, '..' ), 'Unexpected archive member.' );
				$file  = WP_PLUGIN_DIR . '/' . $name;
				$bytes = $zip->getFromIndex( $index );
				self::check( is_string( $bytes ) && ! is_link( $file ) && is_file( $file ) && hash( 'sha256', $bytes ) === hash_file( 'sha256', $file ), 'Updater installed bytes differ from the admitted archive.' );
				$expected[] = $name;
			}
		} finally {
			$zip->close();
		}
		$actual   = array();
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( WP_PLUGIN_DIR . '/' . $slug, \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			self::check( ! $file->isLink(), 'Installed payload contains a symlink.' );
			if ( $file->isFile() ) {
				$actual[] = substr( $file->getPathname(), strlen( WP_PLUGIN_DIR ) + 1 );
			}
		}
		sort( $actual );
		sort( $expected );
		self::check( $actual === $expected, 'Installed payload has unexpected or missing files.' );
	}

	/** @param array<string, mixed> $receipt */
	private static function install( ProspectiveReleaseFacade $facade, array $receipt, bool $install = true ): void {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$archive = $receipt['example']['archive'];
		self::check( hash_file( 'sha256', $archive ) === $receipt['example']['sha256'], 'Candidate ZIP receipt mismatch.' );
		$zip = new \ZipArchive();
		self::check( true === $zip->open( $archive ), 'Candidate ZIP unavailable.' );
		$header = $zip->getFromName( 'ran-booster-github-example/ran-booster-github-example.php' );
		$zip->close();
		if ( ! is_string( $header ) || 1 !== preg_match( '/^ \* Version:\s*(\S+)/m', $header, $matches ) ) {
			throw new RuntimeException( 'Candidate version header unavailable.' );
		}
		$tag       = 'v' . $matches[1];
		$downloads = 0;
		$requests  = 0;
		$streams   = array();
		$transport = static function ( mixed $pre, array $arguments, string $url ) use ( $archive, $tag, &$downloads, &$requests, &$streams ): array|\WP_Error {
			unset( $pre );
			++$requests;
			foreach ( array_keys( $arguments['headers'] ?? array() ) as $key ) {
				self::check( ! in_array( strtolower( (string) $key ), array( 'authorization', 'proxy-authorization', 'cookie' ), true ), 'Anonymous proof sent credentials.' );
			}
			$base    = 'https://api.github.com/repos/' . self::REPOSITORY;
			$allowed = array( $base, 'https://api.github.com/repositories/123456789', $base . '/releases?per_page=20&page=1', $base . '/releases/424007053', $base . '/releases/tags/' . $tag, $base . '/commits/' . $tag, $base . '/releases/assets/42' );
			if ( ! in_array( $url, $allowed, true ) ) {
				// Deny native optional HTTP without turning a refused request into an installer exception.
				return new \WP_Error( 'consumer_http_denied', 'Unapproved HTTP is unavailable in this disposable proof.' );
			}

			$release = array(
				'id'           => 424007053,
				'tag_name'     => $tag,
				'draft'        => false,
				'prerelease'   => false,
				'immutable'    => true,
				'published_at' => '2026-10-26T00:00:00Z',
				'html_url'     => 'https://github.com/' . self::REPOSITORY . '/releases/tag/' . $tag,
				'assets'       => array(
					array(
						'id'     => 42,
						'name'   => 'ran-booster-github-example.zip',
						'state'  => 'uploaded',
						'size'   => filesize( $archive ),
						'digest' => 'sha256:' . hash_file( 'sha256', $archive ),
					),
				),
			);
			if ( true === ( $arguments['stream'] ?? false ) ) {
				self::check( $base . '/releases/assets/42' === $url && is_string( $arguments['filename'] ?? null ) && copy( $archive, $arguments['filename'] ), 'Uncontrolled download refused.' );
				++$downloads;
				$streams[] = $arguments['filename'];
				$body      = '';
			} elseif ( $base . '/commits/' . $tag === $url ) {
				self::check( 'application/vnd.github.sha' === ( $arguments['headers']['Accept'] ?? null ), 'Commit request must accept the raw SHA representation.' );
				$body = str_repeat( 'a', 40 );
			} else {
				$payload = match ( $url ) {
					$base, 'https://api.github.com/repositories/123456789' => array(
						'id'             => 123456789,
						'name'           => 'ran-booster-github-example',
						'full_name'      => self::REPOSITORY,
						'private'        => false,
						'default_branch' => 'main',
						'owner'          => array( 'login' => 'RocketsAreNostalgic' ),
					),
					$base . '/releases?per_page=20&page=1' => array( $release ),
					$base . '/releases/424007053', $base . '/releases/tags/' . $tag => $release,
					default => throw new RuntimeException( 'Every uncontrolled HTTP request is refused: ' . $url ),
				};
				$body = wp_json_encode( $payload );
			}
			return array(
				'body'     => $body,
				'headers'  => array(),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'filename' => $arguments['filename'] ?? null,
			);
		};
		add_filter( 'pre_http_request', $transport, PHP_INT_MIN, 3 );
		try {
			$request = array(
				'provider'      => 'gh',
				'repository'    => self::REPOSITORY,
				'credential_id' => '',
				'branch'        => 'main',
			);
			$list    = $facade->list_candidates( 'plugin', $request, 'stable', wp_create_nonce( $facade->nonce_action( 'list_candidates', 'plugin', null ) ) );
			self::check( $list->successful() && '424007053' === ( $list->data()['candidates'][0]['release_id'] ?? null ) && 0 === $downloads, 'Real release listing failed: ' . $list->code() . '; candidates=' . count( $list->data()['candidates'] ?? array() ) . '; requests=' . $requests . '; downloads=' . $downloads );
			if ( ! $install ) {
				return;
			}
			$inspection = $facade->inspect( 'plugin', $request, '424007053', $tag, 'stable', wp_create_nonce( $facade->nonce_action( 'inspect', 'plugin', null ) ) );
			self::check( $inspection->successful() && 1 === $downloads, 'Real release inspection failed: ' . $inspection->code() );
			$data   = $inspection->data();
			$result = $facade->install( 'plugin', $request, '424007053', $tag, $data['fingerprint'], 'stable', wp_create_nonce( $facade->nonce_action( 'install', 'plugin', $data['provider_expectation'] ) ), $data['provider_expectation'] );
			self::check( $result->successful() && 'installed' === $result->code() && 2 === $downloads && $requests > 2, 'Real updater install failed: ' . $result->code() . '; result=' . wp_json_encode( $result->data() ) . '; requests=' . $requests . '; downloads=' . $downloads );
			self::check( ! is_plugin_active( self::BASENAME ), 'Prospective installation unexpectedly activated Example.' );
			self::verify_archive( $archive );
			foreach ( $streams as $stream ) {
				self::check( ! file_exists( $stream ), 'Acquired archive was retained.' );
			}
		} finally {
			$hook = $GLOBALS['wp_filter']['pre_http_request'] ?? null;
			if ( $hook instanceof \WP_Hook ) {
				$hook->remove_filter( 'pre_http_request', $transport, PHP_INT_MIN );
			}
		}
	}
}

Installed::run( $args[0] ?? '' );
