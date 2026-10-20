<?php
/**
 * Constants PHPStan needs to know about. Each one is optional and set in
 * wp-config.php, so the code only reads it behind defined().
 *
 * @package AryanX\StagingGuard
 */

defined( 'STAGING_GUARD_MAIL_MODE' ) || define( 'STAGING_GUARD_MAIL_MODE', 'block' );
defined( 'STAGING_GUARD_REDIRECT_TO' ) || define( 'STAGING_GUARD_REDIRECT_TO', '' );
defined( 'STAGING_GUARD_ALLOWLIST' ) || define( 'STAGING_GUARD_ALLOWLIST', '' );
defined( 'STAGING_GUARD_KEEP_BODIES' ) || define( 'STAGING_GUARD_KEEP_BODIES', false );
defined( 'STAGING_GUARD_NOINDEX' ) || define( 'STAGING_GUARD_NOINDEX', true );
defined( 'STAGING_GUARD_LOG_DAYS' ) || define( 'STAGING_GUARD_LOG_DAYS', 7 );
defined( 'STAGING_GUARD_PRODUCTION_HOSTS' ) || define( 'STAGING_GUARD_PRODUCTION_HOSTS', '' );

// Core constant set in wp-settings.php, which the WordPress stubs do not declare.
defined( 'WPINC' ) || define( 'WPINC', 'wp-includes' );
