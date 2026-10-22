<?php

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Tests;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RAN\BoosterGitHubExample\Plugin;
use RAN\BoosterGitHubProvider\V1\GitHubProvider;
use RAN\BoosterGitHubProvider\V1\ReleaseDeployments\WorkflowAssistance\WorkflowAssistanceState;
use RAN\Deployment\DeploymentAttemptRepository;
use RAN\ManagedRepository;
use RAN\Package;
use RAN\PackageSource;
use RAN\Portability\BlueprintCredential;
use RAN\Portability\BlueprintCredentialAction;
use RAN\Portability\BlueprintPlanItem;
use RAN\Portability\BlueprintRepositoryVerifier;
use RAN\Portability\ManagedPackageBlueprintExporter;
use RAN\Portability\PackageBlueprint;
use RAN\Portability\TargetPackageAction;
use RAN\Portability\TargetPackageReason;
use RAN\RepositoryProvider\AuthenticatedWebhookDeliveryEvidenceReader;
use RAN\RepositoryProvider\GitHubProviderSelection;
use RAN\RepositoryProvider\GitHubReplacementDeclaration;
use RAN\RepositoryProvider\ProviderSecretPolicyCatalog;
use RAN\Secrets\SecretsFile;
use RAN\Storage\Database;
use RAN\Storage\GitHubSelectionStore;
use RAN\Storage\PluginRepository;
use RAN\Storage\ThemeRepository;
use RAN\RepositoryProvider\ProviderRegistry;
use RAN\Tests\Deployment\AttemptRepositoryDatabase;
use RAN\Tests\Secrets\SecretsFileTestFactory;
use RAN\WordPress\WordPressUpdaterLock;

/** Real destination provider and Core portability; remote HTTP is synthetic. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
final class TransporterTest extends TestCase {

	public function test_destination_uses_real_wrapper_for_both_package_types_and_explicit_credentials(): void {
		$core = getenv( 'RAN_BOOSTER_CORE_PATH' );
		self::assertIsString( $core );
		require_once $core . '/tests/Storage/StorageTestEnvironment.php';
		$root = sys_get_temp_dir() . '/ran-example-transporter-' . bin2hex( random_bytes( 8 ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Create only this test's random passive plugin and encrypted sidecar fixture.
		mkdir( $root . '/ran-booster-github-example', 0700, true );
		copy( dirname( __DIR__ ) . '/ran-booster-github-example.php', $root . '/' . Plugin::BASENAME );
		copy( dirname( __DIR__ ) . '/ran-booster-github-example.gh-replacement.json', $root . '/ran-booster-github-example/ran-booster-github-example.gh-replacement.json' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Inert passive host; no installed site is touched.
		file_put_contents( $root . '/booster.php', '<?php // synthetic passive host' );
		$path = $root . '/synthetic-secrets.json';
		try {
			$policies = new ProviderSecretPolicyCatalog();
			$secrets  = SecretsFileTestFactory::create( $path, array(), $policies );
			$db       = new AttemptRepositoryDatabase();
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Exact synthetic WordPress active-plugin option bytes.
			$db->selection->active_bytes = serialize( array( 'booster.php', Plugin::BASENAME ) );
			$bundled                     = new GitHubReplacementDeclaration( array_merge( GitHubProvider::compatibility_contract(), array( 'plugin_basename' => 'booster.php' ) ) );
			$store                       = new GitHubSelectionStore( $db, 'wp_options' );
			$store->initialize_bundled( $bundled );
			$selection = new GitHubProviderSelection( $store, $root, $bundled );
			$review    = $selection->adoption_review( $secrets );
			$lock      = $this->createMock( WordPressUpdaterLock::class );
			$lock->method( 'try_acquire_without_reclaim' )->willReturn( 'synthetic-updater-owner' );
			$lock->method( 'current_token' )->willReturn( 'synthetic-updater-owner' );
			$lock->method( 'release' )->willReturn( true );
			$attempts = new DeploymentAttemptRepository( $db, 'wp_attempts', database_lifecycle: $this->createStub( Database::class ), selection: $selection );
			$selection->transition_selection( $review['digest'], true, false, false, $secrets, $attempts, $lock, static fn (): bool => true );
			$selection = new GitHubProviderSelection( $store, $root, $bundled );
			$evidence  = $this->createMock( AuthenticatedWebhookDeliveryEvidenceReader::class );
			$registry  = new ProviderRegistry(
				secret_policies: $policies,
				credential_store_factory: $secrets->credentials_for( ... ),
				delivery_evidence_reader_factory: static fn (): AuthenticatedWebhookDeliveryEvidenceReader => $evidence,
				github_selection_guard: $selection->registry_selection( ... ),
				github_candidates: $selection->candidates()
			);
			$selection->bind_registry( $registry );
			Plugin::declare( $registry );
			$selection->complete_collection();
			$registry->seal();
			self::assertInstanceOf( GitHubProvider::class, $registry->get( 'gh' ) );
			require_once $core . '/tests/RepositoryProvider/Support/RepositoryResolverWordPressFunctions.php';
			self::assertSame( 'replacement', $store->read()['selected'] );
			$token = 'ghp_' . str_repeat( 'a', 40 );
			$secrets->save_credential(
				'gh',
				'saved-profile',
				array(
					'label'         => 'Synthetic target',
					'kind'          => 'classic',
					'configuration' => array(),
				),
				$token
			);
			$plugins = $this->createStub( PluginRepository::class );
			$themes  = $this->createStub( ThemeRepository::class );
			$plugins->method( 'all_deployment_plugins' )->willReturn( array( $this->managed_package( 'example/example.php' ) ) );
			$themes->method( 'all_deployment_themes' )->willReturn( array( $this->managed_package( 'example-theme' ) ) );
			$private_state = array();
			foreach ( array( new WorkflowAssistanceState(), new WorkflowAssistanceState( Plugin::WORKFLOW_NAMESPACE ) ) as $state ) {
				$private_state[ $state->setup_option() ] = 'workflow-record-canary';
				$private_state[ '_transient_' . $state->preview_prefix() . str_repeat( 'a', 32 ) ] = 'workflow-preview-canary';
			}
			$private_state['ran_booster_gh_wf_github_example_preview_keys'] = 'workflow-index-canary';
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Populate Core's existing isolated option fixture to detect private-state export or mutation.
			$GLOBALS['ran_booster_storage_test_options'] = $private_state;
			$exporter                                    = new ManagedPackageBlueprintExporter( $plugins, $themes, $secrets );
			$plain                                       = $exporter->export();
			$blueprint                                   = $exporter->export( array( 'gh' => array( 'saved-profile' ) ) );
			self::assertSame( array(), $plain->credentials );
			self::assertCount( 1, $blueprint->credentials );
			self::assertSame( array( 'plugin', 'theme' ), array_column( $blueprint->packages, 'type' ) );
			self::assertSame( $blueprint->canonical_json(), PackageBlueprint::from_json( $blueprint->canonical_json() )->canonical_json() );
			foreach ( array_merge( array_keys( $private_state ), array_values( $private_state ), array( 'saved-profile', 'native_mutation' ) ) as $excluded ) {
				self::assertStringNotContainsString( $excluded, $blueprint->canonical_json() );
			}
			$verifier = new BlueprintRepositoryVerifier( $registry, $secrets, $selection );
			foreach ( $blueprint->packages as $package ) {
				$item = new BlueprintPlanItem( $package, TargetPackageAction::INSTALL, TargetPackageReason::NONE );
				foreach ( array( BlueprintCredentialAction::IMPORT, BlueprintCredentialAction::TARGET ) as $action ) {
					\RAN\BoosterGitHubProvider\V1\repository_resolver_http_queue( array( $this->repository_response() ) );
					$result = $verifier->verify( $item, $blueprint->credentials[0], $action, BlueprintCredentialAction::TARGET === $action ? 'saved-profile' : null );
					self::assertSame( TargetPackageAction::INSTALL, $result->action, $result->reason->value );
					self::assertSame( TargetPackageReason::NONE, $result->reason );
					$requests = \RAN\BoosterGitHubProvider\V1\repository_resolver_http_requests();
					self::assertCount( 1, $requests );
					self::assertSame( 'https://api.github.com/repos/synthetic/repository', $requests[0]['url'] );
					self::assertSame( 'Bearer ' . $token, $requests[0]['arguments']['headers']['Authorization'] );
					self::assertSame( array( 'saved-profile' ), array_keys( $secrets->credential_profiles( 'gh' ) ) );
				}
					$incompatible = new BlueprintCredential( 'gh', 'Mismatched synthetic profile', 'fine-grained', array( 'owner' => 'synthetic' ), $token, $blueprint->credentials[0]->packages );
				\RAN\BoosterGitHubProvider\V1\repository_resolver_http_queue( array() );
				self::assertSame( TargetPackageReason::CREDENTIAL_REQUIRED, $verifier->verify( $item, $incompatible, BlueprintCredentialAction::IMPORT )->reason );
				self::assertSame( array(), \RAN\BoosterGitHubProvider\V1\repository_resolver_http_requests() );
			}
			self::assertSame( $private_state, $GLOBALS['ran_booster_storage_test_options'] );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Simulate disappearance through exact active-plugin bytes; never activate or uninstall a real site.
			$db->selection->active_bytes = serialize( array( 'booster.php' ) );
			$missing                     = new GitHubProviderSelection( $store, $root, $bundled );
			$verifier                    = new BlueprintRepositoryVerifier( $registry, $secrets, $missing );
				$missing_item            = new BlueprintPlanItem( $blueprint->packages[0], TargetPackageAction::INSTALL, TargetPackageReason::NONE );
			\RAN\BoosterGitHubProvider\V1\repository_resolver_http_queue( array() );
				self::assertSame( TargetPackageReason::PROVIDER_UNAVAILABLE, $verifier->verify( $missing_item, $blueprint->credentials[0], BlueprintCredentialAction::IMPORT )->reason );
			self::assertSame( array(), \RAN\BoosterGitHubProvider\V1\repository_resolver_http_requests() );
		} finally {
			foreach ( array( $path, $path . '.lock', $root . '/' . Plugin::BASENAME, $root . '/ran-booster-github-example/ran-booster-github-example.gh-replacement.json', $root . '/booster.php' ) as $file ) {
				if ( is_file( $file ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Remove only exact random synthetic fixture files owned by this test.
					unlink( $file );
				}
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Remove only this test's empty random directories.
			rmdir( $root . '/ran-booster-github-example' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Remove only this test's empty random directory.
			rmdir( $root );
		}
	}

	private function managed_package( string $identifier ): Package {
		$package = $this->createStub( Package::class );
		$package->method( 'get_identifier' )->willReturn( $identifier );
		$package->method( 'get_display_name' )->willReturn( 'Synthetic package' );
		$package->method( 'get_provider_code' )->willReturn( 'gh' );
		$package->method( 'get_provider_repository_id' )->willReturn( '123' );
		$package->method( 'get_repository' )->willReturn( new ManagedRepository( 'gh', 'synthetic/repository', '123', 'main', true, 'saved-profile' ) );
		$package->method( 'get_branch' )->willReturn( 'main' );
		$package->method( 'get_subdirectory' )->willReturn( null );
		$package->method( 'get_credential_id' )->willReturn( 'saved-profile' );
		$package->method( 'get_source' )->willReturn( PackageSource::BRANCH );
		return $package;
	}

	/** @return array{response:array{code:int},body:string} */
	private function repository_response(): array {
		return array(
			'response' => array( 'code' => 200 ),
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Controlled synthetic HTTP response exercises the real library without a remote request.
			'body'     => json_encode(
				array(
					'id'             => 123,
					'full_name'      => 'synthetic/repository',
					'name'           => 'repository',
					'private'        => true,
					'default_branch' => 'main',
				),
				JSON_THROW_ON_ERROR
			),
		);
	}
}
