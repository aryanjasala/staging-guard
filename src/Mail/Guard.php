<?php
/**
 * Stops or redirects outgoing email.
 *
 * @package AryanX\StagingGuard
 */

namespace AryanX\StagingGuard\Mail;

use AryanX\StagingGuard\Environment;
use AryanX\StagingGuard\Service;
use AryanX\StagingGuard\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Decides who an email may reach on a non-production copy.
 *
 * Recipients on the allowlist always get the email. Everyone else is either
 * dropped (block mode) or replaced by one test address (redirect mode).
 * Both happen on the `wp_mail` filter, which runs before `pre_wp_mail`, so
 * the decision sees the final recipient list.
 */
final class Guard implements Service {

	/**
	 * Saved settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Environment detection.
	 *
	 * @var Environment
	 */
	private Environment $environment;

	/**
	 * Stopped email record.
	 *
	 * @var Log
	 */
	private Log $log;

	/**
	 * Whether the email being sent right now should not go out at all.
	 *
	 * @var bool
	 */
	private bool $block_next = false;

	/**
	 * Sets up the guard.
	 *
	 * @param Settings    $settings    Saved settings.
	 * @param Environment $environment Environment detection.
	 * @param Log         $log         Stopped email record.
	 */
	public function __construct( Settings $settings, Environment $environment, Log $log ) {
		$this->settings    = $settings;
		$this->environment = $environment;
		$this->log         = $log;
	}

	/**
	 * Hooks into wp_mail().
	 */
	public function register(): void {
		add_filter( 'wp_mail', array( $this, 'filter_mail' ), PHP_INT_MAX );
		add_filter( 'pre_wp_mail', array( $this, 'short_circuit' ), PHP_INT_MIN, 2 );
	}

	/**
	 * The stopped email record.
	 *
	 * @return Log
	 */
	public function log(): Log {
		return $this->log;
	}

	/**
	 * Mail handling in effect, after wp-config.php overrides.
	 *
	 * Falls back to blocking when redirect is chosen without a valid address,
	 * so a typo never lets mail through to real recipients.
	 *
	 * @return array{mode: string, redirect_to: string, allowlist: string[], locked: bool}
	 */
	public function policy(): array {
		$mode      = defined( 'STAGING_GUARD_MAIL_MODE' ) ? (string) STAGING_GUARD_MAIL_MODE : (string) $this->settings->get( 'mail_mode' );
		$to        = defined( 'STAGING_GUARD_REDIRECT_TO' ) ? (string) STAGING_GUARD_REDIRECT_TO : (string) $this->settings->get( 'redirect_to' );
		$allowlist = defined( 'STAGING_GUARD_ALLOWLIST' ) ? Settings::parse_allowlist( (string) STAGING_GUARD_ALLOWLIST ) : (array) $this->settings->get( 'allowlist' );

		if ( ! in_array( $mode, Settings::MAIL_MODES, true ) || ( 'redirect' === $mode && ! is_email( $to ) ) ) {
			$mode = 'block';
		}

		return array(
			'mode'        => $mode,
			'redirect_to' => $to,
			'allowlist'   => $allowlist,
			'locked'      => defined( 'STAGING_GUARD_MAIL_MODE' ) || defined( 'STAGING_GUARD_REDIRECT_TO' ) || defined( 'STAGING_GUARD_ALLOWLIST' ),
		);
	}

	/**
	 * What happens to email, in a few words.
	 *
	 * @return string
	 */
	public function describe(): string {
		$policy = $this->policy();

		switch ( $policy['mode'] ) {
			case 'redirect':
				/* translators: %s: email address. */
				return sprintf( __( 'Redirected to %s', 'staging-guard' ), $policy['redirect_to'] );

			case 'allow':
				return __( 'Sent normally', 'staging-guard' );

			default:
				return __( 'Blocked', 'staging-guard' );
		}
	}

	/**
	 * Whether an address may receive email from this copy.
	 *
	 * @param string $address Email address.
	 * @return bool
	 */
	public function is_allowed( string $address ): bool {
		$address = strtolower( $address );

		foreach ( $this->policy()['allowlist'] as $entry ) {
			if ( $entry === $address || ( '@' === $entry[0] && substr( $address, -strlen( $entry ) ) === $entry ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Rewrites the recipients of an outgoing email.
	 *
	 * @param array $atts Arguments passed to wp_mail().
	 * @return array
	 */
	public function filter_mail( array $atts ): array {
		$this->block_next = false;
		$policy           = $this->policy();

		if ( 'allow' === $policy['mode'] ) {
			return $atts;
		}

		$to      = self::split( $atts['to'] ?? '' );
		$allowed = array_values( array_filter( $to, array( $this, 'recipient_allowed' ) ) );
		$stopped = array_values( array_diff( $to, $allowed ) );

		list( $headers, $stopped_copies ) = $this->filter_copy_headers( $atts['headers'] ?? array() );
		$stopped                          = array_merge( $stopped, $stopped_copies );

		if ( ! $stopped ) {
			return $atts;
		}

		$atts['headers'] = $headers;
		$entry           = array(
			'to'        => implode( ', ', array_merge( $to, $stopped_copies ) ),
			'subject'   => (string) ( $atts['subject'] ?? '' ),
			'delivered' => implode( ', ', $allowed ),
			'body'      => is_string( $atts['message'] ?? null ) ? $atts['message'] : '',
		);

		if ( 'redirect' === $policy['mode'] ) {
			$atts['to']      = array_merge( $allowed, array( $policy['redirect_to'] ) );
			$atts['subject'] = sprintf( '[%s → %s] %s', strtoupper( $this->environment->label() ), implode( ', ', $stopped ), $entry['subject'] );
			$this->log->add( array( 'action' => 'redirected' ) + $entry );

			return $atts;
		}

		if ( $allowed ) {
			$atts['to'] = $allowed;
			$this->log->add( array( 'action' => 'partly_blocked' ) + $entry );

			return $atts;
		}

		$this->block_next = true;
		$this->log->add( array( 'action' => 'blocked' ) + $entry );

		return $atts;
	}

	/**
	 * Stops an email nobody may receive, and reports it as sent.
	 *
	 * Reporting success keeps flows like password resets from showing errors
	 * while testing. Runs first, so later `pre_wp_mail` senders see a handled value.
	 *
	 * @param null|bool $result Short-circuit value from earlier filters.
	 * @param array     $atts   Arguments passed to wp_mail().
	 * @return null|bool
	 */
	public function short_circuit( $result, $atts ) {
		if ( null !== $result || ! $this->block_next ) {
			return $result;
		}

		$this->block_next = false;

		return true;
	}

	/**
	 * Sends a test email and reports what Staging Guard did with it.
	 *
	 * @param string $to Recipient.
	 * @return string blocked, redirected, partly_blocked, sent or failed.
	 */
	public function send_test( string $to ): string {
		$before = $this->log->latest();
		$sent   = wp_mail(
			$to,
			/* translators: %s: site name. */
			sprintf( __( 'Staging Guard test from %s', 'staging-guard' ), get_bloginfo( 'name' ) ),
			sprintf(
				/* translators: 1: site address, 2: environment name. */
				__( "This is a test email from %1\$s (%2\$s).\n\nIf you are reading it, email from this copy of the site reached you.", 'staging-guard' ),
				home_url(),
				$this->environment->label()
			)
		);
		$after = $this->log->latest();

		if ( $after && $after !== $before ) {
			return (string) $after['action'];
		}

		return $sent ? 'sent' : 'failed';
	}

	/**
	 * Whether another plugin replaced wp_mail(), which this plugin cannot intercept.
	 *
	 * @return bool
	 */
	public function wp_mail_is_replaced(): bool {
		if ( ! function_exists( 'wp_mail' ) ) {
			return false;
		}

		$file = ( new \ReflectionFunction( 'wp_mail' ) )->getFileName();

		return wp_normalize_path( (string) $file ) !== wp_normalize_path( ABSPATH . WPINC . '/pluggable.php' );
	}

	/**
	 * Whether a recipient string ("Name <a@b.c>" or "a@b.c") is allowlisted.
	 *
	 * @param string $recipient Recipient.
	 * @return bool
	 */
	public function recipient_allowed( string $recipient ): bool {
		return $this->is_allowed( self::address( $recipient ) );
	}

	/**
	 * Keeps only allowlisted addresses in Cc and Bcc headers.
	 *
	 * @param string|string[] $headers Headers passed to wp_mail().
	 * @return array{0: string[], 1: string[]} Kept header lines, and stopped addresses.
	 */
	private function filter_copy_headers( $headers ): array {
		$lines   = is_array( $headers ) ? $headers : explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
		$kept    = array();
		$stopped = array();

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}

			if ( ! preg_match( '/^\s*(b?cc)\s*:(.*)$/i', $line, $match ) ) {
				$kept[] = $line;
				continue;
			}

			$recipients = self::split( $match[2] );
			$allowed    = array_filter( $recipients, array( $this, 'recipient_allowed' ) );
			$stopped    = array_merge( $stopped, array_diff( $recipients, $allowed ) );

			if ( $allowed ) {
				$kept[] = $match[1] . ': ' . implode( ', ', $allowed );
			}
		}

		return array( $kept, $stopped );
	}

	/**
	 * Recipients as a list.
	 *
	 * @param string|string[] $recipients Comma-separated string or list.
	 * @return string[]
	 */
	public static function split( $recipients ): array {
		$list = is_array( $recipients ) ? $recipients : explode( ',', (string) $recipients );

		return array_values(
			array_filter(
				array_map( 'trim', $list ),
				static function ( string $recipient ): bool {
					return '' !== $recipient;
				}
			)
		);
	}

	/**
	 * Email address part of "Name <a@b.c>".
	 *
	 * @param string $recipient Recipient.
	 * @return string
	 */
	public static function address( string $recipient ): string {
		if ( preg_match( '/<([^>]+)>/', $recipient, $match ) ) {
			$recipient = $match[1];
		}

		return strtolower( trim( $recipient ) );
	}
}
