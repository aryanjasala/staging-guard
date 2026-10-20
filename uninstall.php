<?php
/**
 * Removes Staging Guard's settings, email log and schedule, on every site.
 *
 * @package StagingGuard
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes Staging Guard data from the current site.
 */
function staging_guard_uninstall_site(): void {
	wp_clear_scheduled_hook( 'staging_guard_purge_log' );
	delete_option( 'staging_guard_settings' );
	delete_option( 'staging_guard_mail_log' );

	do {
		$staging_guard_entries = get_posts(
			array(
				'post_type'   => 'staging_guard_mail',
				'post_status' => 'any',
				'numberposts' => 100,
				'fields'      => 'ids',
			)
		);

		foreach ( $staging_guard_entries as $staging_guard_entry ) {
			wp_delete_post( $staging_guard_entry, true );
		}
	} while ( $staging_guard_entries );
}

if ( is_multisite() ) {
	$staging_guard_offset = 0;
	do {
		$staging_guard_sites = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'offset' => $staging_guard_offset,
			)
		);

		foreach ( $staging_guard_sites as $staging_guard_site ) {
			switch_to_blog( $staging_guard_site );
			staging_guard_uninstall_site();
			restore_current_blog();
		}

		$staging_guard_offset += 100;
	} while ( $staging_guard_sites );
} else {
	staging_guard_uninstall_site();
}

// The notice dismissals are user meta, which is shared across a network.
delete_metadata( 'user', 0, 'staging_guard_lock_notice_dismissed', '', true );
