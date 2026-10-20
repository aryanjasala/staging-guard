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
	);

	/**
	 * All settings, merged with defaults.
	 *
	 * @return array{mail_mode: string, redirect_to: string, allowlist: string[], keep_bodies: bool, noindex: bool, production_hash: string}
	 */
	public function all(): array {
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
		update_option( self::OPTION, array_merge( $this->all(), array_intersect_key( $changes, self::DEFAULTS ) ), false );
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
