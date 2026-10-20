# Staging Guard

[![PHPCS](https://github.com/aryanjasala/staging-guard/actions/workflows/phpcs.yml/badge.svg)](https://github.com/aryanjasala/staging-guard/actions/workflows/phpcs.yml) [![PHPStan](https://github.com/aryanjasala/staging-guard/actions/workflows/phpstan.yml/badge.svg)](https://github.com/aryanjasala/staging-guard/actions/workflows/phpstan.yml) [![Unit tests](https://github.com/aryanjasala/staging-guard/actions/workflows/unit-tests.yml/badge.svg)](https://github.com/aryanjasala/staging-guard/actions/workflows/unit-tests.yml) [![E2E tests](https://github.com/aryanjasala/staging-guard/actions/workflows/e2e-tests.yml/badge.svg)](https://github.com/aryanjasala/staging-guard/actions/workflows/e2e-tests.yml) [![Plugin Check](https://github.com/aryanjasala/staging-guard/actions/workflows/plugin-check.yml/badge.svg)](https://github.com/aryanjasala/staging-guard/actions/workflows/plugin-check.yml)

Stops staging, development and local copies of a WordPress site from emailing real people or getting indexed, and colors the admin bar so nobody edits the wrong copy. On the live site it does nothing.

![The colored admin bar on a staging copy](.wordpress-org/screenshot-1.png)

## Features

On any copy that is not production:

- Outgoing email is **blocked**, or sent to **one test address** with the real recipients in the subject. Cc and Bcc are stripped
- Addresses and whole domains under **Always deliver to** still get email, so the team can test real flows
- The last 50 stopped emails are listed, with message bodies only if that setting is on
- Search engines get an `X-Robots-Tag` header, a `noindex, nofollow` meta tag and a robots.txt that blocks everything. The XML sitemap is turned off
- The admin bar is colored by environment, and every browser tab title starts with it, for example `[Staging] Posts`
- A **Site Health** check warns when email could still reach real people, or when staging copies of the live site would not be detected

## Installation

1. Download `staging-guard.zip` from the [latest release](https://github.com/aryanjasala/staging-guard/releases/latest).
2. In WordPress, go to **Plugins > Add New Plugin > Upload Plugin**, choose the zip and activate it.

Or with WP-CLI:

```sh
wp plugin install https://github.com/aryanjasala/staging-guard/releases/latest/download/staging-guard.zip --activate
```

Needs WordPress 6.6+ and PHP 7.4+.

## How it knows which copy is live

1. `WP_ENVIRONMENT_TYPE`, if it is set in wp-config.php or the server environment. This is the WordPress standard, and many hosts set it.
2. Otherwise the **production lock**: on the live site, open **Tools > Staging Guard** and click **This is the live site**. From then on, any copy of that database on another domain counts as staging.

The lock stores a hash of the live domain, not the domain itself, so the search-replace step of a migration does not rewrite it. With neither in place, WordPress assumes production and the plugin stays off.

## Usage

**Tools > Staging Guard** shows how this copy was detected, what happens to email and search engines, a test-email button and the list of stopped email:

![Tools > Staging Guard with the email log](.wordpress-org/screenshot-2.png)

### Settings in wp-config.php

Fix the email behavior per environment, so a database copy cannot change it:

```php
define( 'STAGING_GUARD_MAIL_MODE', 'redirect' ); // block, redirect or allow
define( 'STAGING_GUARD_REDIRECT_TO', 'qa@example.com' );
define( 'STAGING_GUARD_ALLOWLIST', '@youragency.com, client@example.com' );
```

### WP-CLI

```sh
wp staging-guard status [--format=json]
wp staging-guard lock-production
wp staging-guard unlock-production
wp staging-guard log [--format=table|csv|json|count]
wp staging-guard test-email <to>
```

### For developers

The `staging_guard_environment` filter receives `[ 'type' => 'production|staging|development|local', 'source' => 'constant|lock|default' ]` and can override the detection.

Email is intercepted through the `wp_mail` and `pre_wp_mail` filters, so it works with SMTP plugins that send through `wp_mail()`. A plugin that replaces `wp_mail()` entirely cannot be intercepted, and the settings screen and Site Health both say so.

## Development

```sh
composer install
npm ci
```

| Command | What it runs |
| --- | --- |
| `composer lint` | PHPCS with the WordPress standard and PHPCompatibility from PHP 7.4 |
| `composer analyse` | PHPStan level 6 with WordPress and WP-CLI stubs |
| `composer test` | PHPUnit unit tests. WordPress functions are mocked with Brain Monkey, so no database is needed |
| `npm run env:start` | WordPress and MariaDB in Docker on http://localhost:8895 |
| `npm run test:e2e` | Playwright end-to-end tests against that site, in headless Chrome |
| `npm run screenshots` | Regenerates the screenshots in `.wordpress-org/` |
| `npm run env:stop` | Removes the site and its database |

The plugin is mounted read-only into the Docker site, so nothing in WordPress can change or delete the source. The end-to-end run empties the site's content before it starts, so point it only at this throwaway site.

## Tests

- **15 unit tests**: environment detection and the production lock (the hash ignores case and never contains the domain), recipient parsing, the allowlist, block, redirect and allow modes, Cc and Bcc filtering, and the email log
- **6 end-to-end tests**: an unmarked site left alone, a copy on another domain guarded everywhere (headers, meta tag, robots.txt, sitemap, admin bar, tab title, Site Health), the test email, redirect mode with an allowlist, the production lock buttons, and the WP-CLI commands

The redirect test hooks `phpmailer_init` and checks the recipients PHPMailer would really send to, not only what the log says.

## Contributing

`main` is protected: every change goes through a pull request, and it can only be merged once these pass:

- PHPCS and PHPStan
- PHPUnit on PHP 7.4, 8.0, 8.1, 8.2, 8.3 and 8.4
- the end-to-end suite against the latest WordPress
- the WordPress.org Plugin Check on the built zip
- the PR title check

Pull requests are squash-merged, so the **PR title becomes the commit on `main`** and decides the next release. Use a [Conventional Commit](https://www.conventionalcommits.org/) title:

| Title | Release |
| --- | --- |
| `feat: add bulk restore` | minor (1.1.0 to 1.2.0) |
| `fix: keep the notice on narrow screens`, `perf: ...` | patch (1.1.0 to 1.1.1) |
| `feat!: drop PHP 7.4`, or a `BREAKING CHANGE:` footer | major (1.1.0 to 2.0.0) |
| `docs:`, `ci:`, `chore:`, `test:`, `refactor:`, `build:` | none |

## Releases

Releases are automatic:

1. After each merge to `main`, the **Prepare release** workflow checks for `feat`, `fix`, `perf` or breaking commits since the last tag. If there are any, it opens (or updates) a `chore(release): x.y.z` pull request that bumps the version in the plugin header, the `VERSION` constant and the readme `Stable tag`, and adds the changelog entry to `readme.txt`.
2. Merging that pull request runs the **Release** workflow: it tags `vx.y.z`, builds the zip with `bin/build-zip.sh` (leaving out everything in `.distignore`) and publishes the GitHub release with the changelog entry as notes.

`bin/release.sh next` and `bin/release.sh notes` show locally what the next release would contain.

Deploying to WordPress.org is off until the repository variable `WPORG_DEPLOY` is `true` and the secrets `SVN_USERNAME` and `SVN_PASSWORD` are set. The deploy uses `.wordpress-org/` for the screenshots.

## License

GPL-2.0-or-later
