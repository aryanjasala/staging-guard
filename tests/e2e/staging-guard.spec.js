const { test, expect, wp, wpEval } = require( './utils' );

const PAGE = '/wp-admin/tools.php?page=staging-guard';

// The lab has no WP_ENVIRONMENT_TYPE, so these simulate the two real situations.
const live = () => wp( 'option', 'delete', 'staging_guard_settings' );
const copiedToStaging = () =>
	wpEval( `update_option( 'staging_guard_settings', array( 'production_hash' => AryanX\\StagingGuard\\Environment::hash( 'live.example.com' ) ), false );` );

test.describe( 'Staging Guard', () => {
	test.beforeEach( () => {
		live();
		wp( 'option', 'delete', 'staging_guard_mail_log' );
	} );

	test.afterAll( () => {
		live();
		wp( 'option', 'delete', 'staging_guard_mail_log' );
	} );

	test( 'leaves an unmarked site alone and recommends the production lock', async ( { page, request } ) => {
		await page.goto( PAGE );
		await expect( page.locator( '.sg-pill' ) ).toHaveText( 'Production' );
		await expect( page.locator( '.sg-card' ).first() ).toContainText( 'guards off' );
		await expect( page.locator( '#wp-admin-bar-staging-guard' ) ).toHaveCount( 0 );

		const home = await request.get( '/' );
		expect( home.headers()[ 'x-robots-tag' ] ).toBeUndefined();

		await page.goto( '/wp-admin/site-health.php' );
		await expect( page.getByText( 'Staging copies of this site will not be detected' ) ).toBeVisible();
	} );

	test( 'a database copy on another domain is guarded everywhere', async ( { page, request } ) => {
		copiedToStaging();

		await page.goto( PAGE );
		await expect( page.locator( '.sg-pill' ) ).toHaveText( 'Staging' );
		await expect( page.locator( '.sg-card' ).first() ).toContainText( 'production lock was set on a different domain' );
		await expect( page.locator( '#wp-admin-bar-staging-guard' ) ).toHaveText( 'Staging' );
		await expect( page.locator( '#wpadminbar' ) ).toHaveCSS( 'background-color', 'rgb(179, 71, 0)' );
		await expect( page ).toHaveTitle( /^\[Staging\] / );

		const home = await request.get( '/' );
		expect( home.headers()[ 'x-robots-tag' ] ).toBe( 'noindex, nofollow' );
		expect( await home.text() ).toMatch( /<meta name='robots' content='[^']*noindex, nofollow/ );
		expect( await ( await request.get( '/robots.txt' ) ).text() ).toContain( 'Disallow: /' );
		expect( ( await request.get( '/wp-sitemap.xml' ) ).status() ).toBe( 404 );

		await page.goto( '/wp-admin/site-health.php' );
		// Passing checks sit in the collapsed "Passed tests" list.
		await expect( page.getByText( 'This staging copy is guarded' ) ).toBeAttached();
	} );

	test( 'blocks a test email and lists it', async ( { page } ) => {
		copiedToStaging();
		await page.goto( PAGE );

		await page.locator( '#sg-test-to' ).fill( 'customer@example.com' );
		await page.getByRole( 'button', { name: 'Send', exact: true } ).click();

		await expect( page.locator( '.sg-test-result' ) ).toContainText( 'Test email blocked' );
		const row = page.locator( '.sg-log__row' ).first();
		await expect( row ).toContainText( 'customer@example.com' );
		await expect( row ).toContainText( 'Staging Guard test from' );
		await expect( row.locator( '.sg-badge' ) ).toHaveText( 'Blocked' );
	} );

	test( 'redirect mode keeps allowlisted recipients and strips everyone else', async ( { page } ) => {
		copiedToStaging();
		await page.goto( PAGE );

		await page.getByLabel( 'Send everything to one test address' ).check();
		await page.locator( '#sg-redirect-to' ).fill( 'qa@agency.test' );
		await page.locator( '#sg-allowlist' ).fill( '@agency.test' );
		await page.getByLabel( 'Keep message bodies in the log' ).check();
		await page.getByRole( 'button', { name: 'Save settings' } ).click();
		await expect( page.locator( '.notice-success' ) ).toContainText( 'Settings saved' );
		await expect( page.locator( '.sg-card' ).nth( 1 ) ).toContainText( 'Redirected to qa@agency.test' );

		// Capture what PHPMailer would really send.
		const sent = JSON.parse(
			wpEval( `
				add_action( 'phpmailer_init', function ( $m ) {
					echo wp_json_encode( array(
						'to' => array_column( $m->getToAddresses(), 0 ),
						'cc' => array_column( $m->getCcAddresses(), 0 ),
						'subject' => $m->Subject,
					) );
				} );
				wp_mail( array( 'customer@example.com', 'dev@agency.test' ), 'Your order', 'Order body text', array( 'From: Shop <shop@example.com>', 'Cc: boss@example.com, lead@agency.test' ) );
			` )
		);
		expect( sent.to ).toEqual( [ 'dev@agency.test', 'qa@agency.test' ] );
		expect( sent.cc ).toEqual( [ 'lead@agency.test' ] );
		expect( sent.subject ).toBe( '[STAGING → customer@example.com, boss@example.com] Your order' );

		await page.reload();
		const row = page.locator( '.sg-log__row' ).first();
		await expect( row.locator( '.sg-badge' ) ).toHaveText( 'Redirected' );
		await expect( row ).toContainText( 'Still delivered to dev@agency.test' );
		await row.locator( 'summary' ).click();
		await expect( row.locator( '.sg-log__body' ) ).toHaveText( 'Order body text' );
	} );

	test( 'the production lock buttons switch this copy between live and staging', async ( { page } ) => {
		copiedToStaging();
		await page.goto( PAGE );

		await page.getByRole( 'button', { name: 'Remove production lock' } ).click();
		await expect( page.locator( '.sg-pill' ) ).toHaveText( 'Production' );

		await page.getByRole( 'button', { name: /This is the live site: lock to localhost:8895/ } ).click();
		await expect( page.locator( '.notice-success' ) ).toContainText( 'Production is locked to this domain' );
		await expect( page.locator( '.sg-card' ).first() ).toContainText( 'matches this domain (localhost:8895)' );
	} );

	test( 'WP-CLI reports status and tests email', () => {
		copiedToStaging();

		const status = JSON.parse( wp( 'staging-guard', 'status', '--format=json' ) );
		expect( status ).toMatchObject( { environment: 'staging', source: 'lock', guards: 'on', email: 'block' } );
		expect( wp( 'staging-guard', 'test-email', 'someone@example.com' ) ).toBe( 'Result: blocked' );
		expect( wp( 'staging-guard', 'log', '--format=count' ) ).toBe( '1' );
	} );
} );
