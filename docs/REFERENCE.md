# Reference

## How a copy is classified

1. **`WP_ENVIRONMENT_TYPE`**, from wp-config.php or the server environment, when it is set. `production` turns the guards off; `staging`, `development` and `local` turn them on.
2. Otherwise **`STAGING_GUARD_PRODUCTION_HOSTS`**, a list of the live site's domains. A domain on the list is production, anything else is staging. This is the most reliable option when the live site answers on several domains, and on multisite networks with mapped domains.
3. Otherwise the **production lock**, set on the live site from **Tools > Staging Guard** or with `wp staging-guard lock-production`. It stores `sha256( 'staging-guard|' . <live domain> )`. Any copy whose Site Address domain hashes differently is staging. Search-replace rewrites domain names in the database but not their hashes, so the lock survives a database copy. `www.example.com` and `example.com` count as the same domain.
4. With none of these, WordPress assumes production and the plugin stays off.

When the lock is what makes a copy staging, every admin screen shows a warning until each admin dismisses it for that domain, and Site Health lists it as recommended. That is right on a real staging copy, and it is the only sign you would get if the live site's own address changed. Setting `WP_ENVIRONMENT_TYPE` on staging copies makes it explicit and removes the warning.

## Constants

| Constant | Values | Effect |
| --- | --- | --- |
| `STAGING_GUARD_MAIL_MODE` | `block`, `redirect`, `allow` | Overrides the email setting. A `redirect` without a valid address falls back to `block` |
| `STAGING_GUARD_REDIRECT_TO` | an email address | Where redirected email goes |
| `STAGING_GUARD_ALLOWLIST` | addresses and `@domains`, comma separated | Recipients who always get email |
| `STAGING_GUARD_PRODUCTION_HOSTS` | domains, comma separated, with a port if they need one | The live site's domains. Decides production or staging before the lock |
| `STAGING_GUARD_KEEP_BODIES` | `true`, `false` | Whether the log keeps message bodies |
| `STAGING_GUARD_NOINDEX` | `true`, `false` | Whether staging copies are hidden from search engines |
| `STAGING_GUARD_LOG_DAYS` | a number | Days a stopped email stays in the log. Default 7 |

When a constant is set, its setting on the screen is read-only.

## Hooks

| Hook | Type | Use |
| --- | --- | --- |
| `staging_guard_environment` | filter | `( array $environment )`. Change the detected environment, see below |
| `staging_guard_production_hosts` | filter | `( string[] $hosts )`. The live site's domains. Default from `STAGING_GUARD_PRODUCTION_HOSTS` |
| `staging_guard_allowlist` | filter | `( string[] $allowlist )`. Addresses and `@domains` that still get email |
| `staging_guard_log_days` | filter | `( int $days )`. Days a stopped email stays in the log |
| `staging_guard_capability` | filter | `( string $capability )`. Who may see and change Staging Guard. Default `manage_options`, or `manage_network_options` on multisite |
| `staging_guard_mail_stopped` | action | `( array $entry, array $atts )`. Fires when an email is blocked or redirected. `$entry` has `id`, `to`, `subject`, `action`, `delivered` and `body` |

## Changing the detected environment

```php
add_filter( 'staging_guard_environment', function ( array $environment ) {
	// [ 'type' => 'production|staging|development|local', 'source' => 'constant|hosts|lock|default' ]
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

| Where | Contents |
| --- | --- |
| `staging_guard_settings` option (autoloaded) | Email mode, test address, allowlist, keep-bodies and noindex switches, production lock hash |
| `staging_guard_mail` posts (private) | One per stopped email: recipients, subject, what happened and, only if keep-bodies is on, the message. Deleted after 7 days, and never more than 1,000 |
| `staging_guard_lock_notice_dismissed` user meta | Which domain each admin hid the lock warning for |

The `staging_guard_purge_log` cron event runs hourly. Uninstalling removes all of it on every site of a multisite network.

## Privacy

The log can hold personal data: email addresses, subjects and, if you turn it on, message bodies, which may include password reset links. So:

- message bodies are off by default
- entries are deleted after 7 days (`STAGING_GUARD_LOG_DAYS`)
- the log takes part in **Tools > Export Personal Data** and **Tools > Erase Personal Data**
- **Settings > Privacy** gets suggested text for your privacy policy

The live site keeps no log at all.

## Multisite

On a network, only network admins (`manage_network_options`) can see and change Staging Guard, so one site admin on a staging network cannot switch email back on for everyone. Put `WP_ENVIRONMENT_TYPE` or `STAGING_GUARD_PRODUCTION_HOSTS` with every mapped domain in wp-config.php, and it covers the whole network.

## WordPress VIP

VIP caches `robots.txt`. After the first deploy of Staging Guard to a VIP staging environment, flush it from **Settings > Reading** by toggling "Discourage search engines" on and off.
