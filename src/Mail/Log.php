<?php
/**
 * Record of stopped email.
 *
 * @package AryanX\StagingGuard
 */

namespace AryanX\StagingGuard\Mail;

use AryanX\StagingGuard\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps the last 50 blocked or redirected emails in one option.
 *
 * Message bodies are only kept when the "keep message bodies" setting is on,
 * because emails can contain personal data and password reset links.
 */
final class Log {

	public const OPTION = 'staging_guard_mail_log';

	private const LIMIT     = 50;
	private const BODY_SIZE = 20000;

	/**
	 * Saved settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Sets up the log.
	 *
	 * @param Settings $settings Saved settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Adds an entry at the top.
	 *
	 * @param array $entry {
	 *     What happened.
	 *
	 *     @type string $to        Original recipients.
	 *     @type string $subject   Subject line.
	 *     @type string $action    blocked, redirected or partly_blocked.
	 *     @type string $delivered Recipients the email still went to.
	 *     @type string $body      Message body.
	 * }
	 */
	public function add( array $entry ): void {
		$keep_body = (bool) $this->settings->get( 'keep_bodies' );
		$log       = $this->all();

		array_unshift(
			$log,
			array(
				'time'      => time(),
				'to'        => (string) ( $entry['to'] ?? '' ),
				'subject'   => (string) ( $entry['subject'] ?? '' ),
				'action'    => (string) ( $entry['action'] ?? 'blocked' ),
				'delivered' => (string) ( $entry['delivered'] ?? '' ),
				'body'      => $keep_body ? substr( (string) ( $entry['body'] ?? '' ), 0, self::BODY_SIZE ) : '',
			)
		);

		update_option( self::OPTION, array_slice( $log, 0, self::LIMIT ), false );
	}

	/**
	 * Entries, newest first.
	 *
	 * @return array[]
	 */
	public function all(): array {
		$log = get_option( self::OPTION, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Most recent entry.
	 *
	 * @return array|null
	 */
	public function latest(): ?array {
		return $this->all()[0] ?? null;
	}

	/**
	 * Number of entries since a time.
	 *
	 * @param int $since Unix time.
	 * @return int
	 */
	public function count_since( int $since ): int {
		return count(
			array_filter(
				$this->all(),
				static function ( array $entry ) use ( $since ): bool {
					return (int) $entry['time'] >= $since;
				}
			)
		);
	}

	/**
	 * Empties the log.
	 */
	public function clear(): void {
		delete_option( self::OPTION );
	}
}
