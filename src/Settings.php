<?php
/**
 * Saved settings.
 *
 * @package AryanX\StagingGuard
 */

namespace AryanX\StagingGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the plugin's single settings option.
 *
 * The option is autoloaded because the guards read it on every request.
 * STAGING_GUARD_KEEP_BODIES and STAGING_GUARD_NOINDEX in wp-config.php win
 * over the saved values, the same way the mail constants do.
 */
final class Settings {

	public const OPTION     = 'staging_guard_settings';
	public const MAIL_MODES = array( 'block', 'redirect', 'allow' );

	private const DEFAULTS = array(
		'mail_mode'       => 'block',
		'redirect_to'     => '',
		'allowlist'       => array(),
		'keep_bodies'     => false,
		'noindex'         => true,
		'production_hash' => '',
		'log_version'     => 0,
	);

	/**
	 * Settings a wp-config.php constant can fix: setting => constant.
	 */
	private const CONSTANTS = array(
		'keep_bodies' => 'STAGING_GUARD_KEEP_BODIES',
		'noindex'     => 'STAGING_GUARD_NOINDEX',
	);

	/**
	 * All settings in effect: saved values, defaults, then wp-config.php constants.
	 *
	 * @return array{mail_mode: string, redirect_to: string, allowlist: string[], keep_bodies: bool, noindex: bool, production_hash: string, log_version: int}
	 */
	public function all(): array {
		$all = $this->saved();
		foreach ( self::CONSTANTS as $key => $constant ) {
			if ( defined( $constant ) ) {
				$all[ $key ] = (bool) constant( $constant );
			}
		}

		return $all;
	}

	/**
	 * Whether a setting is fixed by a constant in wp-config.php.
	 *
	 * @param string $key Setting name.
	 * @return bool
	 */
	public function is_fixed( string $key ): bool {
		return isset( self::CONSTANTS[ $key ] ) && defined( self::CONSTANTS[ $key ] );
	}

	/**
	 * Saved settings merged with defaults, without constants.
	 *
	 * @return array
	 */
	private function saved(): array {
		$saved = get_option( self::OPTION, array() );

		return array_merge( self::DEFAULTS, is_array( $saved ) ? array_intersect_key( $saved, self::DEFAULTS ) : array() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public function get( string $key ) {
		return $this->all()[ $key ] ?? null;
	}

	/**
	 * Changes some settings and keeps the rest.
	 *
	 * @param array $changes Setting name => value.
	 */
	public function update( array $changes ): void {
		update_option( self::OPTION, array_merge( $this->saved(), array_intersect_key( $changes, self::DEFAULTS ) ), true );
	}

	/**
	 * Cleans a submitted allowlist: one address or @domain per line or comma.
	 *
	 * @param string $raw Submitted text.
	 * @return string[]
	 */
	public static function parse_allowlist( string $raw ): array {
		$entries = array();

		foreach ( preg_split( '/[\s,]+/', strtolower( $raw ) ) as $entry ) {
			$entry = trim( $entry );
			if ( '' === $entry ) {
				continue;
			}

			if ( '@' === $entry[0] ) {
				$domain = preg_replace( '/[^a-z0-9.\-]/', '', substr( $entry, 1 ) );
				if ( '' !== $domain && false !== strpos( $domain, '.' ) ) {
					$entries[] = '@' . $domain;
				}
			} elseif ( is_email( $entry ) ) {
				$entries[] = $entry;
			}
		}

		return array_values( array_unique( $entries ) );
	}
}
