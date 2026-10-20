<?php
/**
 * Tests for who an email may reach on a non-production copy.
 *
 * @package AryanX\StagingGuard
 */

namespace AryanX\StagingGuard\Tests\Unit;

use AryanX\StagingGuard\Environment;
use AryanX\StagingGuard\Mail\Guard;
use AryanX\StagingGuard\Mail\Log;
use AryanX\StagingGuard\Settings;
use Brain\Monkey\Functions;

final class MailGuardTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'is_email' )->alias(
			static function ( $email ) {
				return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
			}
		);
		Functions\when( 'home_url' )->justReturn( 'http://staging.example.test' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );
	}

	/**
	 * A guard on a copy whose production lock points at another domain, so it is staging.
	 *
	 * @param array $settings Settings to store.
	 * @return Guard
	 */
	private function guard( array $settings ): Guard {
		$this->options[ Settings::OPTION ] = $settings + array( 'production_hash' => Environment::hash( 'live.example.com' ) );

		$store       = new Settings();
		$environment = new Environment( $store );

		return new Guard( $store, $environment, new Log( $store ) );
	}

	public function test_split_accepts_strings_and_arrays_and_drops_blanks(): void {
		$this->assertSame( array( 'a@example.com', 'b@example.com' ), Guard::split( 'a@example.com, ,b@example.com' ) );
		$this->assertSame( array( 'a@example.com' ), Guard::split( array( ' a@example.com ', '' ) ) );
	}

	public function test_address_reads_the_part_inside_angle_brackets(): void {
		$this->assertSame( 'sam@example.com', Guard::address( 'Sam Lee <Sam@Example.com>' ) );
		$this->assertSame( 'sam@example.com', Guard::address( ' sam@example.com ' ) );
	}

	public function test_allowlist_matches_exact_addresses_and_whole_domains(): void {
		$guard = $this->guard( array( 'allowlist' => array( '@agency.test', 'qa@client.test' ) ) );

		$this->assertTrue( $guard->is_allowed( 'dev@agency.test' ) );
		$this->assertTrue( $guard->is_allowed( 'QA@client.test' ) );
		$this->assertFalse( $guard->is_allowed( 'ceo@client.test' ) );
		$this->assertFalse( $guard->is_allowed( 'dev@notagency.test.evil.com' ) );
	}

	public function test_redirect_without_a_valid_address_falls_back_to_blocking(): void {
		$guard = $this->guard(
			array(
				'mail_mode'   => 'redirect',
				'redirect_to' => 'not-an-email',
			)
		);

		$this->assertSame( 'block', $guard->policy()['mode'] );
	}

	public function test_block_mode_stops_the_email_reports_success_and_logs_it(): void {
		$guard = $this->guard( array( 'mail_mode' => 'block' ) );

		$guard->filter_mail(
			array(
				'to'      => 'customer@example.com',
				'subject' => 'Your order',
				'message' => 'Body',
			)
		);

		$this->assertTrue( $guard->short_circuit( null, array() ) );
		$this->assertNull( $guard->short_circuit( null, array() ), 'Only the email that was blocked is stopped.' );

		$entry = $this->options[ Log::OPTION ][0];
		$this->assertSame( 'blocked', $entry['action'] );
		$this->assertSame( 'customer@example.com', $entry['to'] );
		$this->assertSame( '', $entry['body'], 'Bodies are not kept unless the setting is on.' );
	}

	public function test_block_mode_still_delivers_to_allowlisted_recipients(): void {
		$guard = $this->guard(
			array(
				'mail_mode' => 'block',
				'allowlist' => array( '@agency.test' ),
			)
		);

		$atts = $guard->filter_mail(
			array(
				'to'      => array( 'customer@example.com', 'dev@agency.test' ),
				'subject' => 'Your order',
				'headers' => "Cc: boss@example.com, lead@agency.test\r\nFrom: Shop <shop@example.com>",
			)
		);

		$this->assertSame( array( 'dev@agency.test' ), $atts['to'] );
		$this->assertSame( array( 'Cc: lead@agency.test', 'From: Shop <shop@example.com>' ), $atts['headers'] );
		$this->assertNull( $guard->short_circuit( null, array() ) );
		$this->assertSame( 'partly_blocked', $this->options[ Log::OPTION ][0]['action'] );
	}

	public function test_redirect_mode_sends_to_the_test_address_with_the_real_recipients_in_the_subject(): void {
		$guard = $this->guard(
			array(
				'mail_mode'   => 'redirect',
				'redirect_to' => 'qa@agency.test',
				'allowlist'   => array( '@agency.test' ),
				'keep_bodies' => true,
			)
		);

		$atts = $guard->filter_mail(
			array(
				'to'      => array( 'customer@example.com', 'dev@agency.test' ),
				'subject' => 'Your order',
				'message' => 'Order body',
				'headers' => array( 'Bcc: audit@example.com' ),
			)
		);

		$this->assertSame( array( 'dev@agency.test', 'qa@agency.test' ), $atts['to'] );
		$this->assertSame( '[STAGING → customer@example.com, audit@example.com] Your order', $atts['subject'] );
		$this->assertSame( array(), $atts['headers'] );
		$this->assertSame( 'Order body', $this->options[ Log::OPTION ][0]['body'] );
	}

	public function test_allow_mode_leaves_email_alone(): void {
		$guard = $this->guard( array( 'mail_mode' => 'allow' ) );
		$atts  = array(
			'to'      => 'customer@example.com',
			'subject' => 'Hi',
		);

		$this->assertSame( $atts, $guard->filter_mail( $atts ) );
		$this->assertArrayNotHasKey( Log::OPTION, $this->options );
	}
}
