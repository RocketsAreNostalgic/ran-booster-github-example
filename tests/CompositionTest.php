<?php

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Tests;

use PHPUnit\Framework\TestCase;
use RAN\BoosterGitHubExample\Plugin;
use RAN\BoosterGitHubProvider\V1\GitHubProvider;
use RAN\RepositoryProvider\AuthenticatedWebhookDeliveryEvidenceReader;
use RAN\RepositoryProvider\GitHubProviderSelection;
use RAN\RepositoryProvider\GitHubReplacementDeclaration;
use RAN\RepositoryProvider\ProviderCredentialStore;
use RAN\RepositoryProvider\ProviderRegistrationContext;
use RAN\RepositoryProvider\ProviderRegistry;
use RAN\Storage\GitHubSelectionStore;
use RAN\Tests\Deployment\AttemptRepositoryDatabase;
use RAN\Deployment\DeploymentAttemptRepository;
use RAN\Secrets\SecretsFile;
use RAN\Storage\Database;
use RAN\WordPress\WordPressUpdaterLock;

final class CompositionTest extends TestCase {
	public function test_real_aggregate_is_delayed_until_exact_selection_and_matches_every_manifest_facet(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Operate only on random synthetic test-owned plugin fixtures and exact WordPress option bytes.
		$manifest = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/ran-booster-github-example.gh-replacement.json' ), true, 8, JSON_THROW_ON_ERROR );
		self::assertIsArray( $manifest );
		$declaration = new GitHubReplacementDeclaration( $manifest );
		$approval    = new \ArrayObject( array( 'approved' => false ) );
		$disclosures = 0;
		$limit_reads = 0;
		$credentials = $this->createMock( ProviderCredentialStore::class );
		$credentials->expects( self::never() )->method( 'credential_material' );
		$credentials->expects( self::never() )->method( 'credential_profiles' );
		$evidence      = $this->createMock( AuthenticatedWebhookDeliveryEvidenceReader::class );
		$guard         = static function () use ( $approval, $declaration ): ?array {
			return $approval['approved'] ? array(
				'provider_code'              => 'gh',
				'selection_generation'       => 1,
				'implementation_fingerprint' => $declaration->implementation_fingerprint,
				'plugin_basename'            => Plugin::BASENAME,
			) : null;
		};
		$context       = new ProviderRegistrationContext(
			static function () use ( &$limit_reads ): int {
				++$limit_reads;
				return 1048576;
			}
		);
		$make_registry = static function () use ( &$disclosures, $credentials, $evidence, $context, $guard, $declaration ): ProviderRegistry {
			return new ProviderRegistry(
				credential_store_factory: static function () use ( &$disclosures, $credentials ): ProviderCredentialStore {
					++$disclosures;
					return $credentials;
				},
				delivery_evidence_reader_factory: static fn (): AuthenticatedWebhookDeliveryEvidenceReader => $evidence,
				registration_context: $context,
				github_selection_guard: $guard,
				github_candidates: array( Plugin::BASENAME => $declaration )
			);
		};
		$registry      = $make_registry();
		Plugin::declare( $registry );
		$registry->seal();
		self::assertSame( 0, $disclosures );
		self::assertSame( 0, $limit_reads );
		$approval['approved'] = true;
		$registry             = $make_registry();
		Plugin::declare( $registry );
		$registry->seal();
		$provider = $registry->get( 'gh' );
		self::assertInstanceOf( GitHubProvider::class, $provider );
		self::assertSame( 1, $disclosures );
		self::assertSame( 0, $limit_reads );
		foreach ( $declaration->facets as $facet ) {
			self::assertTrue( is_a( $provider, $facet ) );
		}
		self::assertSame( 'gh', $provider->get_metadata()->code->value );
		self::assertSame( 'ran/booster-github-example', $declaration->implementation_id );
		$approval['approved'] = false;
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'operation is unavailable' );
		$provider->resolve_repository( new \RAN\RepositoryProvider\RepositoryLookupRequest( 'synthetic/example', 'retained-synthetic' ) );
	}

	public function test_real_selection_requires_fresh_consent_constructs_the_library_and_requires_explicit_return(): void {
		$root = sys_get_temp_dir() . '/ran-example-selection-' . bin2hex( random_bytes( 8 ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Operate only on random synthetic test-owned plugin fixtures and exact WordPress option bytes.
		mkdir( $root . '/ran-booster-github-example', 0700, true );
		copy( dirname( __DIR__ ) . '/ran-booster-github-example.php', $root . '/' . Plugin::BASENAME );
		copy( dirname( __DIR__ ) . '/ran-booster-github-example.gh-replacement.json', $root . '/ran-booster-github-example/ran-booster-github-example.gh-replacement.json' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Operate only on random synthetic test-owned plugin fixtures and exact WordPress option bytes.
		file_put_contents( $root . '/booster.php', '<?php // passive host' );
		try {
			$db = new AttemptRepositoryDatabase();
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Operate only on random synthetic test-owned plugin fixtures and exact WordPress option bytes.
			$db->selection->active_bytes = serialize( array( 'booster.php', Plugin::BASENAME ) );
			$store                       = new GitHubSelectionStore( $db, 'wp_options' );
			$bundled                     = new GitHubReplacementDeclaration(
				array(
					'plugin_basename'        => 'booster.php',
					'implementation_id'      => 'bundled',
					'compatibility_revision' => '1',
					'provider_api_version'   => 15,
					'api_credential_formats' => array( 'github-classic-pat-v1' ),
					'webhook_signing_format' => null,
					'facets'                 => array(),
				)
			);
			$store->initialize_bundled( $bundled );
			$selection = new GitHubProviderSelection( $store, $root, $bundled );
			self::assertArrayHasKey( Plugin::BASENAME, $selection->candidates() );
			$registry = new ProviderRegistry( github_selection_guard: $selection->registry_selection( ... ), github_candidates: $selection->candidates() );
			$selection->bind_registry( $registry );
			Plugin::declare( $registry );
			$selection->complete_collection();
			$registry->seal();
			self::assertSame( 'decision_required', $selection->status()['state'] );
			$inventory = new \ArrayObject(
				array(
					'profiles' => array(
						'synthetic' => array(
							'format'    => 'github-classic-pat-v1',
							'destroyed' => false,
						),
					),
				)
			);
			$secrets   = $this->createMock( SecretsFile::class );
			$secrets->method( 'with_github_adoption_inventory' )->willReturnCallback( static fn ( callable $operation ): mixed => $operation( $inventory['profiles'] ) );
			$lock = $this->createMock( WordPressUpdaterLock::class );
			$lock->method( 'try_acquire_without_reclaim' )->willReturn( '1234' );
			$lock->method( 'current_token' )->willReturn( '1234' );
			$lock->method( 'release' )->willReturn( true );
			$attempts = new DeploymentAttemptRepository( $db, 'wp_attempts', database_lifecycle: $this->createStub( Database::class ), selection: $selection );
			$other    = $selection->candidates()[ Plugin::BASENAME ];
			self::assertInstanceOf( GitHubReplacementDeclaration::class, $other );
			$other_manifest = get_object_vars( $other );
			unset( $other_manifest['implementation_fingerprint'] );
			$other_manifest['plugin_basename'] = 'other.php';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A second inert passive candidate proves ambiguity refusal.
			file_put_contents( $root . '/other.php', "<?php\n/*\nRAN Booster GitHub Replacement: 1\n*/" );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents,WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Exact bounded synthetic passive manifest fixture.
			file_put_contents( $root . '/other.gh-replacement.json', json_encode( $other_manifest, JSON_THROW_ON_ERROR ) );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Exact WordPress active plugin option bytes in the fixture.
			$db->selection->active_bytes = serialize( array( 'booster.php', Plugin::BASENAME, 'other.php' ) );
			$ambiguous                   = new GitHubProviderSelection( $store, $root, $bundled );
			self::assertCount( 2, $ambiguous->candidates() );
			$refused = false;
			try {
				$ambiguous->adoption_review( $secrets );
			} catch ( \RuntimeException ) {
				$refused = true;
			}
			self::assertTrue( $refused );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Restore this fixture's single active candidate.
			$db->selection->active_bytes = serialize( array( 'booster.php', Plugin::BASENAME ) );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Remove only this test's synthetic passive candidate.
			unlink( $root . '/other.php' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Remove only this test's synthetic passive candidate manifest.
			unlink( $root . '/other.gh-replacement.json' );
			$review                = $selection->adoption_review( $secrets );
			$inventory['profiles'] = array(
				'synthetic' => array(
					'format'    => 'github-fine-grained-pat-v1',
					'destroyed' => false,
				),
			);
			try {
				$selection->transition_selection( $review['digest'], true, true, false, $secrets, $attempts, $lock, static fn (): bool => true );
				self::fail( 'A stale review must refuse.' );
			} catch ( \RuntimeException $error ) {
				self::assertStringContainsString( 'review', $error->getMessage() );
			}
			self::assertSame( 0, $store->read()['selection_generation'] );
			$review = $selection->adoption_review( $secrets );
			try {
				$selection->transition_selection( $review['digest'], true, false, false, $secrets, $attempts, $lock, static fn (): bool => true );
				self::fail( 'Compatible retained material still requires explicit reuse consent.' );
			} catch ( \RuntimeException $error ) {
				self::assertStringContainsString( 'consent', $error->getMessage() );
			}
			$selection->transition_selection( $review['digest'], true, true, false, $secrets, $attempts, $lock, static fn (): bool => true );
			self::assertSame( 'replacement', $store->read()['selected'] );
			$fresh       = new GitHubProviderSelection( $store, $root, $bundled );
			$credentials = $this->createMock( ProviderCredentialStore::class );
			$credentials->expects( self::never() )->method( 'credential_material' );
			$evidence = $this->createMock( AuthenticatedWebhookDeliveryEvidenceReader::class );
			$registry = new ProviderRegistry(
				credential_store_factory: static fn (): ProviderCredentialStore => $credentials,
				delivery_evidence_reader_factory: static fn (): AuthenticatedWebhookDeliveryEvidenceReader => $evidence,
				github_selection_guard: $fresh->registry_selection( ... ),
				github_candidates: $fresh->candidates()
			);
			$fresh->bind_registry( $registry );
			Plugin::declare( $registry );
			$fresh->complete_collection();
			$registry->seal();
			self::assertInstanceOf( GitHubProvider::class, $registry->get( 'gh' ) );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Operate only on random synthetic test-owned plugin fixtures and exact WordPress option bytes.
			$db->selection->active_bytes = serialize( array( 'booster.php' ) );
			$missing                     = new GitHubProviderSelection( $store, $root, $bundled );
			self::assertNull( $missing->registry_selection() );
			self::assertFalse( $missing->status()['available'] );
			self::assertSame( 'replacement', $store->read()['selected'] );
			$review = $missing->adoption_review( $secrets );
			self::assertSame( 'bundled', $review['selected'] );
			$refused = false;
			try {
				$missing->transition_selection( $review['digest'], true, false, false, $secrets, $attempts, $lock, static fn (): bool => true );
			} catch ( \RuntimeException ) {
				$refused = true;
			}
			self::assertTrue( $refused, 'Incompatible retained material needs explicit acknowledgement.' );
			$missing->transition_selection( $review['digest'], true, false, true, $secrets, $attempts, $lock, static fn (): bool => true );
			self::assertSame( 'bundled', $store->read()['selected'] );
			self::assertSame( 2, $store->read()['selection_generation'] );
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Operate only on random synthetic test-owned plugin fixtures and exact WordPress option bytes.
			unlink( $root . '/' . Plugin::BASENAME );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Operate only on random synthetic test-owned plugin fixtures and exact WordPress option bytes.
			unlink( $root . '/ran-booster-github-example/ran-booster-github-example.gh-replacement.json' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Operate only on random synthetic test-owned plugin fixtures and exact WordPress option bytes.
			rmdir( $root . '/ran-booster-github-example' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Operate only on random synthetic test-owned plugin fixtures and exact WordPress option bytes.
			unlink( $root . '/booster.php' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Operate only on random synthetic test-owned plugin fixtures and exact WordPress option bytes.
			rmdir( $root );
		}
	}
}
