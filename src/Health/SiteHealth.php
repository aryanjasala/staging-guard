<?php
/**
 * Site Health check.
 *
 * @package AryanX\Axle\StagingGuard
 */

namespace AryanX\Axle\StagingGuard\Health;

use AryanX\Axle\StagingGuard\Admin\SettingsPage;
use AryanX\Axle\StagingGuard\Environment;
use AryanX\Axle\StagingGuard\Mail\Guard;
use AryanX\Axle\StagingGuard\Service;
use AryanX\Axle\StagingGuard\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a Staging Guard test to Tools > Site Health.
 */
final class SiteHealth implements Service {

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
	 * Sets up the check.
	 *
	 * @param Settings    $settings    Saved settings.
	 * @param Environment $environment Environment detection.
	 * @param Guard       $mail        Email guard.
	 */
	public function __construct( Settings $settings, Environment $environment, Guard $mail ) {
		$this->settings    = $settings;
		$this->environment = $environment;
		$this->mail        = $mail;
	}

	/**
	 * Registers the test.
	 */
	public function register(): void {
		add_filter( 'site_status_tests', array( $this, 'add_test' ) );
	}

	/**
	 * Adds the test to the direct (server-side) tests.
	 *
	 * @param array $tests Site Health tests.
	 * @return array
	 */
	public function add_test( array $tests ): array {
		$tests['direct']['staging_guard'] = array(
			'label' => __( 'Staging Guard', 'staging-guard' ),
			'test'  => array( $this, 'run' ),
		);

		return $tests;
	}

	/**
	 * Runs the test.
	 *
	 * @return array Site Health result.
	 */
	public function run(): array {
		$guarded = $this->environment->is_guarded();
		$action  = sprintf( '<p><a href="%s">%s</a></p>', esc_url( admin_url( 'tools.php?page=' . SettingsPage::SLUG ) ), esc_html__( 'Open Staging Guard', 'staging-guard' ) );

		if ( $guarded && $this->mail->wp_mail_is_replaced() ) {
			return $this->result( 'critical', __( 'Email from this staging copy can reach real people', 'staging-guard' ), __( 'Another plugin has replaced wp_mail(), so Staging Guard cannot stop email here. Turn off sending in that plugin on this copy.', 'staging-guard' ), $action );
		}

		if ( $guarded && 'allow' === $this->mail->policy()['mode'] ) {
			return $this->result( 'recommended', __( 'This staging copy sends email normally', 'staging-guard' ), __( 'Staging Guard is set to send email normally, so customers can receive email from this copy.', 'staging-guard' ), $action );
		}

		$summary = sprintf(
			/* translators: 1: what happens to email, e.g. "Blocked", 2: "hidden" or "not hidden". */
			__( 'Email: %1$s. Search engines: %2$s.', 'staging-guard' ),
			$this->mail->describe(),
			$this->settings->get( 'noindex' ) ? __( 'hidden', 'staging-guard' ) : __( 'not hidden', 'staging-guard' )
		);

		if ( $this->environment->is_staging_by_lock() ) {
			return $this->result(
				'recommended',
				__( 'This site is treated as staging because of the production lock', 'staging-guard' ),
				$this->environment->explain() . ' ' . $summary . ' ' . __( 'If this is the live site and its address changed, lock it to this domain again. On a real staging copy, set WP_ENVIRONMENT_TYPE to staging in its wp-config.php to make it explicit.', 'staging-guard' ),
				$action
			);
		}

		if ( $guarded ) {
			return $this->result(
				'good',
				/* translators: %s: environment name, e.g. "Staging". */
				sprintf( __( 'This copy is guarded (%s)', 'staging-guard' ), $this->environment->label() ),
				$summary,
				$action
			);
		}

		if ( 'default' === $this->environment->source() ) {
			return $this->result( 'recommended', __( 'Staging copies of this site will not be detected', 'staging-guard' ), __( 'Nothing marks this site as the live one, so a copy of it on staging would also count as production and send real email. Set the production lock, or set WP_ENVIRONMENT_TYPE on each environment.', 'staging-guard' ), $action );
		}

		return $this->result( 'good', __( 'Staging copies of this site will be guarded', 'staging-guard' ), $this->environment->explain(), $action );
	}

	/**
	 * Builds a Site Health result.
	 *
	 * @param string $status      good, recommended or critical.
	 * @param string $label       Heading.
	 * @param string $description Explanation.
	 * @param string $actions     Action links HTML.
	 * @return array
	 */
	private function result( string $status, string $label, string $description, string $actions ): array {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Security', 'staging-guard' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html( $description ) . '</p>',
			'actions'     => $actions,
			'test'        => 'staging_guard',
		);
	}
}
