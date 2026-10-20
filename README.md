# Staging Guard

Stops staging, development and local copies of a WordPress site from emailing real people or getting indexed, and colours the admin bar so nobody edits the wrong copy. On the live site it does nothing.

On any copy that is not production:

- Outgoing email is blocked, or sent to one test address with the real recipients in the subject. Cc and Bcc are stripped
- Addresses and whole domains on an "Always deliver to" list still get email, so the team can test real flows
- The last 50 stopped emails are listed, with message bodies only if that setting is on
- Search engines get an `X-Robots-Tag` header, a `noindex, nofollow` meta tag and a robots.txt that blocks everything. The XML sitemap is turned off
- The admin bar is coloured by environment and every browser tab title starts with it, for example `[Staging] Posts`
- A Site Health check warns when email could still reach real people

## How it decides a copy is not live

1. `WP_ENVIRONMENT_TYPE`, if it is set in wp-config.php or the server environment.
2. Otherwise the **production lock**: set it once on the live site and every copy of that database on another domain counts as staging. The lock stores a hash of the live domain, not the domain itself, so database search-replace during a migration does not rewrite it.

With neither, WordPress assumes production and the plugin stays off.

User-facing docs are in [`readme.txt`](readme.txt), which is what WordPress.org shows.

## Requirements

WordPress 6.6+, PHP 7.4+.

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
| `npm run env:stop` | Removes the site and its database |

The plugin is mounted read-only into the Docker site, so nothing in WordPress can change or delete the source. The end-to-end run empties the site's content before it starts, so point it only at this throwaway site.

## Tests

- **15 unit tests**: environment detection and the production lock (the hash ignores case and never contains the domain), recipient parsing, the allowlist, block, redirect and allow modes, Cc and Bcc filtering, and the email log
- **6 end-to-end tests**: an unmarked site left alone, a copy on another domain guarded everywhere (headers, meta tag, robots.txt, sitemap, admin bar, tab title, Site Health), the test email, redirect mode with an allowlist, the production lock buttons, and the WP-CLI commands

The redirect test hooks `phpmailer_init` and checks the recipients PHPMailer would really send to, not only what the log says.

## CI

| Workflow | Runs on | What it checks |
| --- | --- | --- |
| PHPCS | push to `main`, pull requests | WordPress coding standards |
| PHPStan | push to `main`, pull requests | Static analysis, level 6 |
| Unit tests | push to `main`, pull requests | PHPUnit on PHP 7.4, 8.0, 8.1, 8.2, 8.3 and 8.4 |
| E2E tests | push to `main`, pull requests | Playwright against the latest WordPress in Docker |
| Plugin Check | push to `main`, pull requests | The official WordPress.org Plugin Check, on the built zip |
| Release | tags `v*` | Builds the zip and publishes a GitHub release |

## Releasing

1. Update the version in three places: the plugin header, the `VERSION` constant and `Stable tag` in `readme.txt`. Add a `= x.y.z =` entry under `== Changelog ==`.
2. Push a tag: `git tag v1.2.0 && git push origin v1.2.0`.

The release workflow fails if the tag does not match all three versions or the changelog has no entry for it. Otherwise it runs PHPCS and PHPUnit, builds the zip with `bin/build-zip.sh` (leaving out everything in `.distignore`), and publishes a GitHub release with the changelog entry as notes.

Deploying to WordPress.org is off until the repository variable `WPORG_DEPLOY` is `true` and the secrets `SVN_USERNAME` and `SVN_PASSWORD` are set.

## Layout

```
staging-guard.php       plugin header and PSR-4 autoloader
src/
  Plugin.php            creates the services, guards only load when not production
  Settings.php          the settings option and allowlist parsing
  Environment.php       environment detection and the production lock
  Mail/Guard.php        who an email may reach
  Mail/Log.php          the stopped email list
  Search/Robots.php     noindex header and tag, robots.txt, sitemaps
  Admin/                settings screen, admin bar and tab titles
  Health/SiteHealth.php the Site Health check
  Cli/Command.php       wp staging-guard status|lock-production|log|test-email
tests/
  Unit/                 PHPUnit
  e2e/                  Playwright specs and the Docker site
```

## License

GPL-2.0-or-later
