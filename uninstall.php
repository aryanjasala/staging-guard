<?php
/**
 * Removes Staging Guard's settings and email log.
 *
 * @package StagingGuard
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $staging_guard_site_id ) {
		switch_to_blog( $staging_guard_site_id );
		delete_option( 'staging_guard_settings' );
		delete_option( 'staging_guard_mail_log' );
		restore_current_blog();
	}
} else {
	delete_option( 'staging_guard_settings' );
	delete_option( 'staging_guard_mail_log' );
}
