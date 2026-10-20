# Reference

## How a copy is classified

1. **`WP_ENVIRONMENT_TYPE`**, from wp-config.php or the server environment, when it is set. `production` turns the guards off; `staging`, `development` and `local` turn them on.
2. Otherwise the **production lock**, set on the live site from **Tools > Staging Guard** or with `wp staging-guard lock-production`. It stores `sha256( 'staging-guard|' . <live domain> )`. Any copy whose Site Address domain hashes differently is staging. Search-replace rewrites domain names in the database but not their hashes, so the lock survives a database copy.
3. With neither, WordPress assumes production and the plugin stays off.

## Constants

| Constant | Values | Effect |
| --- | --- | --- |
| `STAGING_GUARD_MAIL_MODE` | `block`, `redirect`, `allow` | Overrides the email setting. A `redirect` without a valid address falls back to `block` |
| `STAGING_GUARD_REDIRECT_TO` | an email address | Where redirected email goes |
| `STAGING_GUARD_ALLOWLIST` | addresses and `@domains`, comma separated | Recipients who always get email |

When any of these is set, the email settings on the screen are read-only.

## Filter

```php
add_filter( 'staging_guard_environment', function ( array $environment ) {
	// [ 'type' => 'production|staging|development|local', 'source' => 'constant|lock|default' ]
	if ( str_ends_with( $_SERVER['HTTP_HOST'] ?? '', '.test' ) ) {
		return array( 'type' => 'local', 'source' => 'filter' );
	}
	return $environment;
} );
```

## WP-CLI

```sh
wp staging-guard status [--format=json]       # environment, how it was detected, what is guarded
wp staging-guard lock-production              # mark the current domain as the live site
wp staging-guard unlock-production
wp staging-guard log [--format=table|csv|json|count]
wp staging-guard test-email <to>              # prints blocked, redirected, partly_blocked or sent
```

## Email

Email is handled on the `wp_mail` filter (recipients, subject, Cc and Bcc) and `pre_wp_mail` (stopping a blocked email and reporting it as sent, so password resets and forms behave normally). SMTP plugins that send through `wp_mail()` are covered. A plugin that replaces `wp_mail()` entirely cannot be intercepted; the settings screen and Site Health both say so.

## Data

| Option | Contents |
| --- | --- |
| `staging_guard_settings` | Email mode, test address, allowlist, keep-bodies and noindex switches, production lock hash |
| `staging_guard_mail_log` | Last 50 stopped emails |

Both are removed on uninstall.
