// Regenerates .wordpress-org/screenshot-N.png (readme.txt "== Screenshots ==" and README.md).
// Run on demand: npm run screenshots
const path = require( 'path' );
const { test, expect, wp, wpEval } = require( './utils' );

const shot = ( page, number, options = {} ) => page.screenshot( { path: path.join( __dirname, '..', '..', '.wordpress-org', `screenshot-${ number }.png` ), ...options } );

test.describe( 'Screenshots', () => {
	test.use( { viewport: { width: 1280, height: 900 } } );

	test.beforeAll( () => {
		// A copy of a live site that runs on another domain, with a team allowlist and kept bodies.
		wpEval( `update_option( 'staging_guard_settings', array(
			'production_hash' => AryanX\\StagingGuard\\Environment::hash( 'shop.example.com' ),
			'allowlist'       => array( '@youragency.com' ),
			'keep_bodies'     => true,
		), false );` );
		wp( 'option', 'delete', 'staging_guard_mail_log' );
		wpEval( `
			$from = 'From: Example Shop <shop@example.com>';
			wp_mail( 'sam.lee@example.net', 'Your order #1042 has shipped', "Hi Sam,\\n\\nYour order is on its way.", $from );
			wp_mail( array( 'priya@example.org', 'dev@youragency.com' ), 'New comment on "Spring menu"', 'A new comment is waiting for approval.', $from );
			wp_mail( 'admin@example.com', '[Example Shop] Password reset', 'Someone requested a password reset for your account.', $from );
		` );
	} );

	test.afterAll( () => {
		wp( 'option', 'delete', 'staging_guard_settings' );
		wp( 'option', 'delete', 'staging_guard_mail_log' );
	} );

	test( '1: the coloured admin bar on a staging copy', async ( { page } ) => {
		await page.goto( '/wp-admin/' );
		await expect( page.locator( '#wp-admin-bar-staging-guard' ) ).toHaveText( 'Staging' );
		await shot( page, 1, { clip: { x: 0, y: 0, width: 1280, height: 420 } } );
	} );

	test( '2: Tools > Staging Guard with the email log', async ( { page } ) => {
		await page.goto( '/wp-admin/tools.php?page=staging-guard' );
		await expect( page.locator( '.sg-log__row' ) ).toHaveCount( 3 );
		await shot( page, 2, { fullPage: true } );
	} );
} );
