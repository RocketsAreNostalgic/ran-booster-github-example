<?php

declare(strict_types=1);

namespace RAN\BoosterGitHubExample\Tests\Support;

/** Connection/lock ownership and exact index deletion double; no live database. */
final class UninstallDatabase {
	public string $options    = 'wp_options';
	public string $last_error = '';
	public int $dbh           = 7;
	/** @var list<string> */
	public array $deleted = array();

	public function prepare( string $query, mixed ...$args ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Encode exact fixture SQL and arguments without a WordPress runtime.
		return json_encode( array( $query, $args ), JSON_THROW_ON_ERROR );
	}

	public function get_var( string $query ): string {
		$data = json_decode( $query, true, 8, JSON_THROW_ON_ERROR );
		if ( ! is_array( $data ) || ! is_string( $data[0] ?? null ) ) {
			throw new \RuntimeException( 'Malformed fixture SQL.' );
		}
		return str_contains( $data[0], 'GET_LOCK' ) ? '7' : '1';
	}

	public function query( string $query ): int {
		$data = json_decode( $query, true, 8, JSON_THROW_ON_ERROR );
		if ( ! is_array( $data ) || ! is_string( $data[0] ?? null ) || ! str_starts_with( $data[0], 'DELETE FROM' ) || ! is_array( $data[1] ?? null ) || ! is_string( $data[1][1] ?? null ) ) {
			throw new \RuntimeException( 'Only exact preview-index deletion is expected.' );
		}
		$name            = $data[1][1];
		$this->deleted[] = $name;
		unset( $GLOBALS['ran_booster_github_example_options'][ $name ] );
		return 1;
	}
}
