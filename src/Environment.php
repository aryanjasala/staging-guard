<?php
/**
 * Works out whether this copy of the site is production.
 *
 * @package AryanX\StagingGuard
 */

namespace AryanX\StagingGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Environment detection and the production lock.
 *
 * WordPress calls every site "production" unless WP_ENVIRONMENT_TYPE is set,
 * and most staging copies never set it. The production lock covers that case:
 * it stores a hash of the live domain, which survives a database copy to
 * staging, because search-replace tools rewrite plain domain names but not
 * their hashes.
 */
final class Environment {

	public const COLOURS = array(
		'local'       => '#1b5e20',
		'development' => '#0d47a1',
		'staging'     => '#b34700',
		'production'  => '#1d2327',
	);

	private const HASH_CONTEXT = 'staging-guard|';

	/**
	 * Saved settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Detected environment, cached for the request.
	 *
	 * @var array{type: string, source: string}|null
	 */
	private ?array $detected = null;

	/**
	 * Sets up detection.
	 *
	 * @param Settings $settings Saved settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Environment type and how it was found.
	 *
	 * @return array{type: string, source: string} Source is constant, lock, default or filter.
	 */
	public function detect(): array {
		if ( null !== $this->detected ) {
			return $this->detected;
		}

		$from_env = getenv( 'WP_ENVIRONMENT_TYPE' );

		if ( defined( 'WP_ENVIRONMENT_TYPE' ) || ( false !== $from_env && '' !== $from_env ) ) {
			$environment = array(
				'type'   => wp_get_environment_type(),
				'source' => 'constant',
			);
		} elseif ( $this->is_locked() ) {
			$environment = array(
				'type'   => $this->is_production_domain() ? 'production' : 'staging',
				'source' => 'lock',
			);
		} else {
			$environment = array(
				'type'   => 'production',
				'source' => 'default',
			);
		}

		/**
		 * Filters the detected environment.
		 *
		 * @param array $environment {
		 *     Detected environment.
		 *
		 *     @type string $type   One of production, staging, development or local.
		 *     @type string $source How it was detected: constant, lock, default or filter.
		 * }
		 */
		$this->detected = apply_filters( 'staging_guard_environment', $environment );

		return $this->detected;
	}

	/**
	 * Environment type: production, staging, development or local.
	 *
	 * @return string
	 */
	public function type(): string {
		return (string) $this->detect()['type'];
	}

	/**
	 * How the type was found: constant, lock, default or filter.
	 *
	 * @return string
	 */
	public function source(): string {
		return (string) $this->detect()['source'];
	}

	/**
	 * Whether the guards should run.
	 *
	 * @return bool
	 */
	public function is_guarded(): bool {
		return 'production' !== $this->type();
	}

	/**
	 * Human name of the environment.
	 *
	 * @param string|null $type Environment type, or null for the current one.
	 * @return string
	 */
	public function label( ?string $type = null ): string {
		$labels = array(
			'production'  => __( 'Production', 'staging-guard' ),
			'staging'     => __( 'Staging', 'staging-guard' ),
			'development' => __( 'Development', 'staging-guard' ),
			'local'       => __( 'Local', 'staging-guard' ),
		);
		$type   = $type ?? $this->type();

		return $labels[ $type ] ?? ucfirst( $type );
	}

	/**
	 * Admin bar colour for the environment.
	 *
	 * @return string Hex colour.
	 */
	public function colour(): string {
		return self::COLOURS[ $this->type() ] ?? self::COLOURS['staging'];
	}

	/**
	 * Domain of this copy of the site, from the Site Address setting.
	 *
	 * @return string
	 */
	public function domain(): string {
		$parts = wp_parse_url( home_url() );
		$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';

		return isset( $parts['port'] ) ? $host . ':' . $parts['port'] : $host;
	}

	/**
	 * Hash the production lock stores for a domain.
	 *
	 * @param string $domain Domain name, with port if any.
	 * @return string
	 */
	public static function hash( string $domain ): string {
		return hash( 'sha256', self::HASH_CONTEXT . strtolower( $domain ) );
	}

	/**
	 * Whether a production lock is set.
	 *
	 * @return bool
	 */
	public function is_locked(): bool {
		return '' !== (string) $this->settings->get( 'production_hash' );
	}

	/**
	 * Whether this copy runs on the domain the production lock was set on.
	 *
	 * @return bool
	 */
	public function is_production_domain(): bool {
		$hash = (string) $this->settings->get( 'production_hash' );

		return '' !== $hash && hash_equals( $hash, self::hash( $this->domain() ) );
	}

	/**
	 * Marks the current domain as the live site.
	 */
	public function lock(): void {
		$this->settings->update( array( 'production_hash' => self::hash( $this->domain() ) ) );
		$this->detected = null;
	}

	/**
	 * Removes the production lock.
	 */
	public function unlock(): void {
		$this->settings->update( array( 'production_hash' => '' ) );
		$this->detected = null;
	}

	/**
	 * One sentence explaining how the environment was detected.
	 *
	 * @return string
	 */
	public function explain(): string {
		switch ( $this->source() ) {
			case 'constant':
				return __( 'Set by WP_ENVIRONMENT_TYPE in wp-config.php or the server environment.', 'staging-guard' );

			case 'lock':
				return $this->is_production_domain()
					/* translators: %s: domain name. */
					? sprintf( __( 'The production lock matches this domain (%s).', 'staging-guard' ), $this->domain() )
					/* translators: %s: domain name. */
					: sprintf( __( 'The production lock was set on a different domain, so this copy on %s is treated as staging.', 'staging-guard' ), $this->domain() );

			case 'default':
				return __( 'Nothing tells WordPress which environment this is, so it assumes production and the guards are off.', 'staging-guard' );

			default:
				return __( 'Set by the staging_guard_environment filter.', 'staging-guard' );
		}
	}
}
