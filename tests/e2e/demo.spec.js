// Records .github/media/demo.gif for the README. Run on demand: npm run demo
const { test, expect, wp, wpEval } = require( './utils' );
const { prepareSite, recordDemo } = require( './utils/media' );

test( 'demo: a staging copy blocks, then redirects, its email', async ( { browser } ) => {
	test.setTimeout( 180_000 );
	prepareSite( 'Example Shop' );
	// A copy of the live site at shop.example.com, so this domain counts as staging.
	wpEval( `update_option( 'staging_guard_settings', array(
		'production_hash' => AryanX\\StagingGuard\\Environment::hash( 'shop.example.com' ),
		'allowlist'       => array( '@youragency.com' ),
		'keep_bodies'     => true,
	), false );` );
	wp( 'option', 'delete', 'staging_guard_mail_log' );
	const post = wp( 'post', 'create', '--post_title=Spring menu is here', '--post_status=publish', '--post_content=Three new dishes and a lemon tart.', '--porcelain' );

	try {
		await recordDemo( browser, async ( page, demo ) => {
			await page.goto( '/' );
			await demo.start();
			await demo.pause( 1500 );
			await demo.click( page.locator( '#wp-admin-bar-staging-guard > a' ), 300 );
			await expect( page.locator( '.sg-pill' ) ).toHaveText( 'Staging' );
			await demo.pause( 1500 );

			// Email from a staging copy is stopped and logged.
			await demo.type( page.locator( '#sg-test-to' ), 'customer@example.com' );
			await demo.click( page.getByRole( 'button', { name: 'Send', exact: true } ), 300 );
			await expect( page.locator( '.sg-test-result' ) ).toContainText( 'Test email blocked' );
			await demo.moveTo( page.locator( '.sg-log__row' ).first().locator( '.sg-badge' ) );
			await demo.pause( 1800 );

			// Or sent to one test inbox instead.
			await page.locator( '#sg-redirect-to' ).scrollIntoViewIfNeeded();
			await demo.click( page.getByLabel( 'Send everything to one test address' ), 300 );
			await demo.type( page.locator( '#sg-redirect-to' ), 'qa@youragency.com' );
			await demo.click( page.getByRole( 'button', { name: 'Save settings' } ), 300 );
			await expect( page.locator( '.notice-success' ) ).toContainText( 'Settings saved' );
			await page.evaluate( () => window.scrollTo( 0, 0 ) );
			await demo.moveTo( page.locator( '.sg-card' ).nth( 1 ) );
			await demo.pause( 1200 );

			await demo.type( page.locator( '#sg-test-to' ), 'customer@example.com' );
			await demo.click( page.getByRole( 'button', { name: 'Send', exact: true } ), 300 );
			await expect( page.locator( '.sg-log__row' ).first().locator( '.sg-badge' ) ).toHaveText( 'Redirected' );
			await demo.moveTo( page.locator( '.sg-log__row' ).first().locator( '.sg-badge' ) );
			await demo.pause( 2000 );
		} );
	} finally {
		wp( 'option', 'delete', 'staging_guard_settings' );
		wp( 'option', 'delete', 'staging_guard_mail_log' );
		wp( 'post', 'delete', post, '--force' );
	}
} );
