<?php
/**
 * Plugin bootstrap.
 *
 * @package AryanX\StagingGuard
 */

namespace AryanX\StagingGuard;

use AryanX\StagingGuard\Admin\AdminBar;
use AryanX\StagingGuard\Admin\SettingsPage;
use AryanX\StagingGuard\Cli\Command;
use AryanX\StagingGuard\Health\SiteHealth;
use AryanX\StagingGuard\Mail\Guard;
use AryanX\StagingGuard\Mail\Log;
use AryanX\StagingGuard\Search\Robots;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the services once and registers their hooks.
 *
 * The admin screen, Site Health check and WP-CLI commands load everywhere.
 * The guards (mail, search engines, admin bar) only load when this copy of
 * the site is not production.
 */
final class Plugin {

	/**
	 * The running instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

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
	 * Email guard.
	 *
	 * @var Guard
	 */
	private Guard $mail;

	/**
	 * Starts the plugin. Runs on `plugins_loaded`.
	 */
	public static function boot(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register();
		}
	}

	/**
	 * The running instance.
	 *
	 * @return Plugin|null
	 */
	public static function instance(): ?Plugin {
		return self::$instance;
	}

	/**
	 * Builds the shared objects.
	 */
	private function __construct() {
		$this->settings    = new Settings();
		$this->environment = new Environment( $this->settings );
		$this->mail        = new Guard( $this->settings, $this->environment, new Log( $this->settings ) );
	}

	/**
	 * Environment detection, for other plugins.
	 *
	 * @return Environment
	 */
	public function environment(): Environment {
		return $this->environment;
	}

	/**
	 * Registers the services.
	 */
	private function register(): void {
		$services = array(
			new SettingsPage( $this->settings, $this->environment, $this->mail ),
			new SiteHealth( $this->settings, $this->environment, $this->mail ),
		);

		if ( $this->environment->is_guarded() ) {
			$services[] = $this->mail;
			$services[] = new AdminBar( $this->environment, $this->mail );
			if ( $this->settings->get( 'noindex' ) ) {
				$services[] = new Robots();
			}
		}

		foreach ( $services as $service ) {
			$service->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'staging-guard', new Command( $this->settings, $this->environment, $this->mail ) );
		}
	}
}
