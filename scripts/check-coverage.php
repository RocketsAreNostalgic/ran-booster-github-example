<?php

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Scripts;

use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Files\FileList;
use PHP_CodeSniffer\Runner;
use RuntimeException;

/**
 * Compare independent source discovery with the locked checkers' actual selection.
 *
 * The optional root is used only by disposable negative controls. Nonstandard
 * PHP fails closed until both checker selections deliberately support it.
 */
function check_coverage( string $root ): void {
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_passthru -- Run the fixed AST/shell lexical guard on local maintained helpers, without evaluating their source.
	passthru( 'python3 ' . escapeshellarg( __DIR__ . '/check-embedded-php.py' ) . ' ' . escapeshellarg( $root ), $status );
	if ( 0 !== $status ) {
		throw new RuntimeException( 'Unsupported embedded PHP must move into directly checked files.' );
	}
	require_once $root . '/vendor/autoload.php';
	require_once $root . '/vendor/squizlabs/php_codesniffer/autoload.php';
	$xml = new \DOMDocument();
	if ( ! $xml->load( $root . '/.phpcs.xml', LIBXML_NONET ) ) {
		throw new RuntimeException( 'Cannot read the local PHPCS ruleset.' );
	}
	$xpath     = new \DOMXPath( $xml );
	$forbidden = $xpath->query( '//rule/exclude | //rule/exclude-pattern | //include-pattern | //severity | //type | //*[@phpcs-only or @phpcbf-only]' );
	if ( false === $forbidden || 0 !== $forbidden->length ) {
		throw new RuntimeException( 'Review changed rule exclusions, severities or fixer/checker divergence.' );
	}
	$exclusions = array();
	foreach ( $xml->getElementsByTagName( 'exclude-pattern' ) as $node ) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM exposes nodeValue as its foreign inspection contract.
		$exclusions[] = $node->getAttribute( 'type' ) . ':' . $node->nodeValue;
	}
	if ( array( 'relative:^vendor/*', 'relative:^dist/*', 'relative:^\.phpstan/*', 'relative:^\.phpunit\.cache/*' ) !== $exclusions ) {
		throw new RuntimeException( 'Review changed root-only PHPCS exclusions.' );
	}
	$rules = array();
	foreach ( $xml->getElementsByTagName( 'rule' ) as $node ) {
		$rules[] = $node->getAttribute( 'ref' );
	}
	if ( array( 'RANWordPressPlugin', 'RANOwnedMethods', 'WordPress.NamingConventions.ValidVariableName', 'WordPress.PHP.YodaConditions', 'WordPress.NamingConventions.PrefixAllGlobals' ) !== $rules ) {
		throw new RuntimeException( 'Review changed shared standards or owned naming rules.' );
	}
	$arguments = array();
	foreach ( $xml->getElementsByTagName( 'arg' ) as $node ) {
		$arguments[] = $node->getAttribute( 'name' ) . ':' . $node->getAttribute( 'value' );
	}
	$configurations = array();
	foreach ( $xml->getElementsByTagName( 'config' ) as $node ) {
		$configurations[] = $node->getAttribute( 'name' ) . ':' . $node->getAttribute( 'value' );
	}
	if ( array( 'basepath:.', 'extensions:php', ':sp' ) !== $arguments || array( 'minimum_wp_version:7.0', 'testVersion:8.2-8.5' ) !== $configurations ) {
		throw new RuntimeException( 'Review changed checker arguments or supported compatibility boundaries.' );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read local command metadata as data; never execute commands from it.
	$composer = json_decode( (string) file_get_contents( $root . '/composer.json' ), true, 512, JSON_THROW_ON_ERROR );
	if ( 'phpcs --standard=.phpcs.xml --report=summary' !== ( $composer['scripts']['standards'] ?? null )
		|| 'phpcbf --standard=.phpcs.xml --report=summary' !== ( $composer['scripts']['standards:fix'] ?? null )
		|| array( '@check:host', 'phpstan analyse --configuration=phpstan.neon --no-progress --memory-limit=512M --debug' ) !== ( $composer['scripts']['analyze'] ?? null ) ) {
		throw new RuntimeException( 'Canonical checker commands must retain the verified selection.' );
	}
	// @phpstan-ignore phpstanApi.constructor, phpstanApi.method (Use the locked tool's own configuration reader rather than reimplementing Neon.)
	$config = ( new \PHPStan\DependencyInjection\NeonAdapter( array() ) )->load( $root . '/phpstan.neon' );
	if ( array( 'vendor/szepeviktor/phpstan-wordpress/extension.neon' ) !== ( $config['includes'] ?? null )
		|| 8 !== ( $config['parameters']['level'] ?? null )
		|| 80200 !== ( $config['parameters']['phpVersion'] ?? null )
		|| false !== ( $config['parameters']['treatPhpDocTypesAsCertain'] ?? null )
		|| isset( $config['parameters']['bootstrapFiles'] )
		|| array( '.' ) !== ( $config['parameters']['paths'] ?? null )
		|| array( 'analyseAndScan' => array( 'vendor/*', 'dist/*', '.git/*', '.phpstan/*', '.phpunit.cache/*' ) ) !== ( $config['parameters']['excludePaths'] ?? null )
		|| isset( $config['parameters']['ignoreErrors'] ) || isset( $config['parameters']['fileExtensions'] ) ) {
		throw new RuntimeException( 'Review weakened PHPStan level, inherited config or maintained-file scope.' );
	}
	$expected = array();
	$iterator = new \RecursiveCallbackFilterIterator(
		new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ),
		static fn( \SplFileInfo $entry ): bool => ! $entry->isDir() || ! in_array( substr( $entry->getPathname(), strlen( $root ) + 1 ), array( 'vendor', 'dist', '.git', '.phpstan', '.phpunit.cache' ), true )
	);
	foreach ( new \RecursiveIteratorIterator( $iterator ) as $entry ) {
		if ( ! $entry->isFile() ) {
			continue;
		}
		$path = $entry->getPathname();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Discover local maintained code without executing it, independently of checker paths.
		$source = file_get_contents( $path );
		if ( false === $source || is_link( $path ) ) {
			throw new RuntimeException( 'Unreadable or symlinked maintained source requires review.' );
		}
		$extension = strtolower( $entry->getExtension() );
		// Genuine leading XML declarations are data; later PHP tags remain visible.
		$without_xml = preg_replace( '~\A(?:\xEF\xBB\xBF)?<\?xml\s+version\s*=\s*[\x22\x27]1\.[01][\x22\x27](?:\s+encoding\s*=\s*[\x22\x27][A-Za-z0-9._-]+[\x22\x27])?\s*\?>~', '', $source ) ?? $source;
		$data        = in_array( $extension, array( 'md', 'py' ), true ) || ( 'json' === $extension && null !== json_decode( $source ) ) || ( 'sh' === $extension && str_starts_with( $source, '#!' ) );
		$php         = in_array( $extension, array( 'php', 'phtml' ), true ) || 1 === preg_match( $data ? '/\A(?:\xEF\xBB\xBF)?(?:#![^\n]*\n)?\s*<\?/' : '/<\?/', $without_xml );
		if ( ! $php ) {
			continue;
		}
		if ( 'php' !== $entry->getExtension() ) {
			throw new RuntimeException( 'Nonstandard PHP must enter both checker selections: ' . substr( $path, strlen( $root ) + 1 ) );
		}
		$expected[] = $path;
		foreach ( token_get_all( $source ) as $token ) {
			if ( ! is_array( $token ) || ! in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			if ( preg_match( '/@codingStandards(?:Ignore|ChangeSetting)|phpcs:(?:set\b|ignoreFile|disable\b)|@phpstan-ignore-(?:line|next-line)/i', $token[1] ) ) {
				throw new RuntimeException( 'Blanket or persistent checker suppression requires review: ' . $path );
			}
			if ( preg_match_all( '/phpcs:ignore\b([^\r\n]*)/i', $token[1], $matches ) ) {
				foreach ( $matches[1] as $directive ) {
					if ( ! preg_match( '/--\s+\S/', $directive ) ) {
						throw new RuntimeException( 'Checker exceptions need a concrete local reason: ' . $path );
					}
					foreach ( explode( ',', explode( '--', $directive, 2 )[0] ) as $selector ) {
						if ( count( explode( '.', trim( $selector, " \t*/" ) ) ) < 4 ) {
							throw new RuntimeException( 'Checker exceptions must identify an exact diagnostic: ' . $path );
						}
					}
				}
			}
			$allowed_annotations = array(
				'// @phpstan-ignore phpstanApi.constructor, phpstanApi.method (Use the locked tool\'s own configuration reader rather than reimplementing Neon.)',
				'// @phpstan-ignore phpstanApi.constructor (Use the locked tool\'s actual post-discovery stub exclusion.)',
				'// @phpstan-ignore phpstanApi.method (Use the locked tool\'s actual exclusion predicate.)',
			);
			if ( preg_match( '/@phpstan-ignore\b/i', $token[1] ) && ( __FILE__ !== $path || ! in_array( $token[1], $allowed_annotations, true ) ) ) {
				throw new RuntimeException( 'New analysis exemptions require scoped review: ' . $path );
			}
		}
	}
	if ( array() === $expected ) {
		throw new RuntimeException( 'Empty maintained PHP discovery is not success.' );
	}
	$runner         = new Runner();
	$runner->config = new Config( array( '--standard=' . $root . '/.phpcs.xml' ) );
	$runner->init();
	$selected = array();
	foreach ( new FileList( $runner->config, $runner->ruleset ) as $path => $file ) {
		$selected[] = $path;
	}
	if ( array() !== array_diff( $expected, $selected ) || array() !== array_diff( $selected, $expected ) ) {
		throw new RuntimeException( 'PHPCS selection differs from independently discovered maintained PHP.' );
	}
	// PHPCS emulates newer PHP tokens with string constants on older runtimes.
	// PHPStan's parser requires native integer token IDs, so obtain its selection
	// in a clean process instead of allowing one tool to change the other's lexer.
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Run only this fixed read-only selection worker under the same PHP binary, without a shell or arbitrary commands.
	$process = proc_open(
		array( PHP_BINARY, __FILE__, $root, '--phpstan-files' ),
		array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$pipes,
		$root
	);
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'Cannot start the isolated PHPStan selection worker.' );
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- The fixed selection worker accepts no input.
	fclose( $pipes[0] );
	$output = stream_get_contents( $pipes[1] );
	$error  = stream_get_contents( $pipes[2] );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close only this worker's completed output pipe.
	fclose( $pipes[1] );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close only this worker's completed diagnostic pipe.
	fclose( $pipes[2] );
	if ( 0 !== proc_close( $process ) || false === $output || false === $error ) {
		throw new RuntimeException( 'The isolated PHPStan selection worker failed: ' . ( false === $error ? 'unreadable diagnostics' : $error ) );
	}
	$selected = json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
	if ( ! is_array( $selected ) || ! array_is_list( $selected ) || count( array_filter( $selected, is_string( ... ) ) ) !== count( $selected ) ) {
		throw new RuntimeException( 'The PHPStan selection worker returned invalid file identities.' );
	}
	if ( array() !== array_diff( $expected, $selected ) || array() !== array_diff( $selected, $expected ) ) {
		throw new RuntimeException( 'PHPStan direct analysis differs from independently discovered maintained PHP: ' . implode( ', ', array_diff( $expected, $selected ) ) . '; unexpected: ' . implode( ', ', array_diff( $selected, $expected ) ) );
	}
	printf( "PHPCS and PHPStan directly cover %d independently discovered maintained PHP files.\n", count( $expected ) );
}

/** @return list<string> */
function phpstan_files( string $root ): array {
	require_once $root . '/vendor/autoload.php';
	$container = ( new \PHPStan\DependencyInjection\ContainerFactory( $root ) )->create( $root . '/.phpstan/coverage', array( $root . '/phpstan.neon' ), array() );
	$selected  = $container->getService( 'fileFinderAnalyse' )->findFiles( $container->getParameter( 'paths' ) )->getFiles();
	// @phpstan-ignore phpstanApi.constructor (Use the locked tool's actual post-discovery stub exclusion.)
	$stub_excluder = new \PHPStan\File\FileExcluder( $container->getByType( \PHPStan\File\FileHelper::class ), $container->getParameter( 'stubFiles' ) );
	// @phpstan-ignore phpstanApi.method (Use the locked tool's actual exclusion predicate.)
	return array_values( array_filter( $selected, static fn( string $file ): bool => ! $stub_excluder->isExcludedFromAnalysing( $file ) ) );
}

$ran_booster_github_example_coverage_root = realpath( $argv[1] ?? dirname( __DIR__ ) );
if ( false === $ran_booster_github_example_coverage_root ) {
	throw new RuntimeException( 'Coverage root is unavailable.' );
}
if ( '--phpstan-files' === ( $argv[2] ?? '' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Return only local file identities as JSON to the parent CLI coverage process; no HTML or credentials are emitted.
	echo json_encode( phpstan_files( $ran_booster_github_example_coverage_root ), JSON_THROW_ON_ERROR );
} else {
	check_coverage( $ran_booster_github_example_coverage_root );
}
