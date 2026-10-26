<?php

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RAN\BoosterGitHubExample\Plugin;
use RAN\RepositoryProvider\ProviderCredentialStore;
use RAN\RepositoryProvider\AuthenticatedWebhookDeliveryEvidenceReader;
use RAN\RepositoryProvider\ProviderRegistrationContext;
use RAN\BoosterGitHubProvider\V1\GitHubProvider;
use RAN\BoosterGitHubProvider\V1\ReleaseDeployments\WorkflowAssistance\WorkflowAssistanceState;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
final class DependencyCopiesTest extends TestCase {
	public function test_example_first_loads_the_exact_qualified_library_bytes(): void {
		self::assertFalse( class_exists( WorkflowAssistanceState::class, false ) );
		Plugin::load_dependencies();
		$provider = $this->aggregate();
		$core     = getenv( 'RAN_BOOSTER_CORE_PATH' );
		self::assertIsString( $core );
		require $core . '/autoload.php';
		self::assertSame( 15, GitHubProvider::compatibility_contract()['provider_api_version'] );
		self::assertSame( realpath( dirname( __DIR__ ) . '/vendor/ran/booster-github-provider/src/GitHubProvider.php' ), ( new \ReflectionClass( $provider ) )->getFileName() );
		$reflection = new \ReflectionClass( WorkflowAssistanceState::class );
		self::assertSame( realpath( dirname( __DIR__ ) . '/vendor/ran/booster-github-provider/src/ReleaseDeployments/WorkflowAssistance/WorkflowAssistanceState.php' ), $reflection->getFileName() );
	}

	public function test_core_bootstrap_first_reuses_the_exact_assembled_provider_copy(): void {
		$core = getenv( 'RAN_BOOSTER_CORE_PATH' );
		self::assertIsString( $core );
		$source = realpath( $core . '/vendor/ran/booster-github-provider/src/GitHubProvider.php' );
		self::assertIsString( $source );
		self::assertSame( hash_file( 'sha256', dirname( __DIR__ ) . '/vendor/ran/booster-github-provider/src/GitHubProvider.php' ), hash_file( 'sha256', $source ) );
		require $source;
		self::assertSame( 15, \RAN\BoosterGitHubProvider\V1\GitHubProvider::compatibility_contract()['provider_api_version'] );
		Plugin::load_dependencies();
		$reflection = new \ReflectionClass( \RAN\BoosterGitHubProvider\V1\GitHubProvider::class );
		self::assertSame( $source, $reflection->getFileName() );
		$provider = $this->aggregate();
		self::assertSame( $source, ( new \ReflectionClass( $provider ) )->getFileName() );
	}

	public function test_core_native_updater_copy_coexists_with_the_qualified_example(): void {
		$core = getenv( 'RAN_BOOSTER_CORE_PATH' );
		self::assertIsString( $core );
		$relative = '/vendor/ran/wp-release-updater/src/WordPress/NativePackageUpdater.php';
		$source   = realpath( $core . $relative );
		self::assertIsString( $source );
		self::assertSame( hash_file( 'sha256', dirname( __DIR__ ) . $relative ), hash_file( 'sha256', $source ) );
		require $source;
		Plugin::load_dependencies();
		$symbol = 'RAN\\WPReleaseUpdater\\V1\\WordPress\\NativePackageUpdater';
		if ( ! class_exists( $symbol, false ) ) {
			throw new \RuntimeException( 'The Core native updater copy was not loaded.' );
		}
		self::assertInstanceOf( GitHubProvider::class, $this->aggregate() );
	}

	private function aggregate(): GitHubProvider {
		$credentials = $this->createMock( ProviderCredentialStore::class );
		$credentials->expects( self::never() )->method( 'credential_material' );
		$credentials->expects( self::never() )->method( 'credential_profiles' );
		$provider = Plugin::create(
			$credentials,
			$this->createMock( AuthenticatedWebhookDeliveryEvidenceReader::class ),
			new ProviderRegistrationContext( static fn (): int => 1048576, static fn (): bool => true )
		);
		self::assertInstanceOf( GitHubProvider::class, $provider );
		return $provider;
	}

	public function test_an_older_core_provider_copy_cannot_silently_execute(): void {
		$core = getenv( 'RAN_BOOSTER_OLD_CORE_PATH' );
		if ( ! is_string( $core ) || ! is_file( $core . '/vendor/ran/booster-github-provider/src/GitHubProvider.php' ) ) {
			self::markTestSkipped( 'Set RAN_BOOSTER_OLD_CORE_PATH for the incompatible historical Core package case.' );
		}
		$source = $core . '/vendor/ran/booster-github-provider/src/GitHubProvider.php';
		self::assertNotSame( hash_file( 'sha256', dirname( __DIR__ ) . '/vendor/ran/booster-github-provider/src/GitHubProvider.php' ), hash_file( 'sha256', $source ) );
		require $source;
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'incompatible' );
		Plugin::load_dependencies();
	}

	public function test_identical_already_loaded_state_from_another_copy_is_reused(): void {
		$source = dirname( __DIR__ ) . '/vendor/ran/booster-github-provider/src/ReleaseDeployments/WorkflowAssistance/WorkflowAssistanceState.php';
		$copy   = tempnam( sys_get_temp_dir(), 'ran-qualified-copy-' );
		self::assertIsString( $copy );
		copy( $source, $copy );
		try {
			require $copy;
			Plugin::load_dependencies();
			$reflection = new \ReflectionClass( WorkflowAssistanceState::class );
			self::assertSame( $copy, $reflection->getFileName() );
			self::assertSame( hash_file( 'sha256', $source ), hash_file( 'sha256', $copy ) );
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Delete only the random synthetic dependency copy created by this test.
			unlink( $copy );
		}
	}

	/** @return array<string,array{string}> */
	public static function unqualified_prefixes(): array {
		return array(
			'provider' => array( 'RAN\\BoosterGitHubProvider\\V1' ),
			'updater'  => array( 'RAN\\WPReleaseUpdater\\V1' ),
			'support'  => array( 'RAN\\UpdaterSupport\\V1' ),
		);
	}

	#[DataProvider( 'unqualified_prefixes' )]
	public function test_unqualified_symbols_in_each_dependency_namespace_refuse_before_provider_loading( string $prefix ): void {
		$fixture = tempnam( sys_get_temp_dir(), 'ran-unknown-copy-' );
		self::assertIsString( $fixture );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Synthetic local PHP fixture contains only a deliberately unqualified inert symbol.
		file_put_contents( $fixture, '<?php namespace ' . $prefix . '; final class UnqualifiedExampleCopy {}' );
		try {
			require $fixture;
			try {
				Plugin::load_dependencies();
				self::fail( 'An unqualified runtime symbol must refuse.' );
			} catch ( \RuntimeException $error ) {
				self::assertStringContainsString( 'incompatible', $error->getMessage() );
			}
			self::assertFalse( class_exists( WorkflowAssistanceState::class, false ) );
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Delete only the random synthetic dependency fixture created by this test.
			unlink( $fixture );
		}
	}
}
