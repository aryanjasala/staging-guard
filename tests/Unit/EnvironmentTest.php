<?php
/**
 * Tests for environment detection and the production lock.
 *
 * @package AryanX\StagingGuard
 */

namespace AryanX\StagingGuard\Tests\Unit;

use AryanX\StagingGuard\Environment;
use AryanX\StagingGuard\Settings;
use Brain\Monkey\Functions;

final class EnvironmentTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );
		Functions\when( 'home_url' )->justReturn( 'https://www.example.com' );
	}

	private function environment( string $production_hash = '' ): Environment {
		$this->options[ Settings::OPTION ] = array( 'production_hash' => $production_hash );

		return new Environment( new Settings() );
	}

	public function test_an_unmarked_site_is_production_and_unguarded(): void {
		$environment = $this->environment();

		$this->assertSame( 'production', $environment->type() );
		$this->assertSame( 'default', $environment->source() );
		$this->assertFalse( $environment->is_guarded() );
	}

	public function test_the_locked_domain_is_production(): void {
		$environment = $this->environment( Environment::hash( 'www.example.com' ) );

		$this->assertSame( 'production', $environment->type() );
		$this->assertSame( 'lock', $environment->source() );
	}

	public function test_a_copy_on_another_domain_is_staging(): void {
		$environment = $this->environment( Environment::hash( 'live.example.com' ) );

		$this->assertSame( 'staging', $environment->type() );
		$this->assertTrue( $environment->is_guarded() );
		$this->assertSame( '#b34700', $environment->colour() );
	}

	public function test_the_hash_survives_search_replace_and_ignores_case(): void {
		$hash = Environment::hash( 'WWW.Example.com' );

		$this->assertSame( Environment::hash( 'www.example.com' ), $hash );
		$this->assertStringNotContainsString( 'example', $hash, 'The domain itself must not appear, or search-replace would rewrite it.' );
	}

	public function test_the_port_is_part_of_the_domain(): void {
		Functions\when( 'home_url' )->justReturn( 'http://localhost:8895' );

		$this->assertSame( 'localhost:8895', $this->environment()->domain() );
	}

	public function test_locking_stores_the_hash_of_the_current_domain(): void {
		$environment = $this->environment();
		$environment->lock();

		$this->assertSame( Environment::hash( 'www.example.com' ), $this->options[ Settings::OPTION ]['production_hash'] );
		$this->assertTrue( $environment->is_production_domain() );
	}

	public function test_allowlist_parsing_keeps_valid_addresses_and_domains_once(): void {
		Functions\when( 'is_email' )->alias(
			static function ( $email ) {
				return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
			}
		);

		$this->assertSame(
			array( '@agency.test', 'qa@client.test' ),
			Settings::parse_allowlist( "@Agency.test\nqa@client.test, not-an-email @nodot\n@agency.test" )
		);
	}
}
