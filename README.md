<p align="center"><img src=".wordpress-org/icon-256x256.png" alt="" width="88"></p>

<h1 align="center">Staging Guard</h1>

<p align="center">
  <a href="https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/aryanjasala/staging-guard/main/blueprint.json"><img src="https://img.shields.io/badge/Try%20in-WordPress%20Playground-3858e9?logo=wordpress&logoColor=white" alt="Try in WordPress Playground"></a>
  <a href="https://github.com/aryanjasala/staging-guard/releases/latest"><img src="https://img.shields.io/github/v/release/aryanjasala/staging-guard?label=release&color=2271b1" alt="Latest release"></a>
  <img src="https://img.shields.io/badge/WordPress-6.6%2B-3858e9?logo=wordpress&logoColor=white" alt="WordPress 6.6+">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white" alt="PHP 7.4+">
  <a href="LICENSE.md"><img src="https://img.shields.io/badge/license-GPL--2.0--or--later-blue" alt="License: GPL-2.0-or-later"></a>
  <br>
  <a href="https://github.com/aryanjasala/staging-guard/actions/workflows/phpcs.yml"><img src="https://github.com/aryanjasala/staging-guard/actions/workflows/phpcs.yml/badge.svg" alt="PHPCS"></a>
  <a href="https://github.com/aryanjasala/staging-guard/actions/workflows/phpstan.yml"><img src="https://github.com/aryanjasala/staging-guard/actions/workflows/phpstan.yml/badge.svg" alt="PHPStan"></a>
  <a href="https://github.com/aryanjasala/staging-guard/actions/workflows/unit-tests.yml"><img src="https://github.com/aryanjasala/staging-guard/actions/workflows/unit-tests.yml/badge.svg" alt="Unit tests"></a>
  <a href="https://github.com/aryanjasala/staging-guard/actions/workflows/e2e-tests.yml"><img src="https://github.com/aryanjasala/staging-guard/actions/workflows/e2e-tests.yml/badge.svg" alt="E2E tests"></a>
  <a href="https://github.com/aryanjasala/staging-guard/actions/workflows/plugin-check.yml"><img src="https://github.com/aryanjasala/staging-guard/actions/workflows/plugin-check.yml/badge.svg" alt="Plugin Check"></a>
</p>

<p align="center">Stop staging copies from emailing real customers or showing up in Google, and color the admin bar so nobody edits the wrong copy.</p>

<p align="center">
  <img src=".github/media/demo.gif" alt="A staging copy with the orange admin bar blocking a test email, then sending email to one test inbox instead" width="880">
</p>

---

Every agency has a staging story: a copy of the live database goes to staging and emails every customer, Google indexes it, or someone spends an hour editing it thinking it was live. Staging Guard prevents all three on every copy that is not production, and does nothing on the live site.

> [!NOTE]
> Free and open source under the GPL. No pro version, no upsells, no ads in wp-admin, no tracking. And it stays that way.

## Features

- **Email**: blocked, or sent to one test address with the real recipients in the subject. Cc and Bcc stripped
- **Always deliver to**: addresses and whole domains that still get email, so the team can test real flows
- **Email log** of the last 50 stopped emails, with bodies only if you turn that on
- **Search engines**: `X-Robots-Tag` header, `noindex, nofollow` meta tag, a blocking robots.txt, no sitemap
- **Admin bar** colored by environment, and `[Staging]` at the start of every browser tab title
- **Production lock**: mark the live site once, and every database copy on another domain counts as staging
- **Site Health** check that warns when email could still reach real people

## Install

1. Download `staging-guard.zip` from the [latest release](https://github.com/aryanjasala/staging-guard/releases/latest).
2. In WordPress, go to **Plugins > Add New Plugin > Upload Plugin**, choose the zip and activate it.

Or with WP-CLI:

```sh
wp plugin install https://github.com/aryanjasala/staging-guard/releases/latest/download/staging-guard.zip --activate
```

Needs WordPress 6.6+ and PHP 7.4+. To try it first, the **Try in WordPress Playground** badge opens a throwaway WordPress in your browser with the plugin and some demo content.

## Quick look

Pin the email behavior per environment in wp-config.php, so a database copy cannot change it:

```php
define( 'STAGING_GUARD_MAIL_MODE', 'redirect' );   // block, redirect or allow
define( 'STAGING_GUARD_REDIRECT_TO', 'qa@example.com' );
define( 'STAGING_GUARD_ALLOWLIST', '@youragency.com' );
```

It decides a copy is not live from `WP_ENVIRONMENT_TYPE` when that is set, otherwise from the **production lock**: a hash of the live domain, which survives the search-replace step of a migration.

## Screenshots

<table>
  <tr>
    <td width="50%" valign="top"><img src=".wordpress-org/screenshot-1.png" alt="The coloured admin bar on a staging copy"><br><sub>The coloured admin bar on a staging copy</sub></td>
    <td width="50%" valign="top"><img src=".wordpress-org/screenshot-2.png" alt="Tools > Staging Guard with the email log"><br><sub>Tools > Staging Guard with the email log</sub></td>
  </tr>
</table>

## Documentation

| Doc | What it covers |
| --- | --- |
| [Reference](docs/REFERENCE.md) | Environment detection, constants, the filter and WP-CLI |
| [Development](docs/DEVELOPMENT.md) | Local setup, commands and tests |
| [Contributing](docs/CONTRIBUTING.md) | Pull requests, required checks and PR titles |
| [Releasing](docs/RELEASING.md) | How merged pull requests become releases |
| [Security](docs/SECURITY.md) | Reporting a vulnerability |
| [readme.txt](readme.txt) | What WordPress.org shows, with the FAQ and changelog |

## Project structure

```
staging-guard.php          plugin header and PSR-4 autoloader
src/
├── Plugin.php             creates the services, guards load only when not production
├── Settings.php           the settings option and allowlist parsing
├── Environment.php        environment detection and the production lock
├── Mail/
│   ├── Guard.php          who an email may reach
│   └── Log.php            the stopped email list
├── Search/Robots.php      noindex header and tag, robots.txt, sitemaps
├── Admin/                 settings screen, admin bar and tab titles
├── Health/SiteHealth.php  the Site Health check
└── Cli/Command.php        wp staging-guard
tests/
├── Unit/                  PHPUnit
└── e2e/                   Playwright specs and the Docker site
.wordpress-org/            screenshots
docs/                      reference, development, contributing, releasing
```

## More free plugins

Small plugins that each fill one gap in WordPress, all free with no pro version:

| | Plugin | What it does |
| --- | --- | --- |
| <img src="https://raw.githubusercontent.com/aryanjasala/page-template-usage/main/.wordpress-org/icon-128x128.png" alt="" width="32"> | [Page Template Usage](https://github.com/aryanjasala/page-template-usage) | See which template every page uses, and fix the pages on a missing template |
| <img src="https://raw.githubusercontent.com/aryanjasala/category-tag-trash/main/.wordpress-org/icon-128x128.png" alt="" width="32"> | [Category & Tag Trash](https://github.com/aryanjasala/category-tag-trash) | A trash for categories, tags and custom terms, with undo and full restore |
| <img src="https://raw.githubusercontent.com/aryanjasala/site-read-only-mode/main/.wordpress-org/icon-128x128.png" alt="" width="32"> | [Site Read-Only Mode](https://github.com/aryanjasala/site-read-only-mode) | Read-only mode for WordPress during migrations and deploys |
| <img src="https://raw.githubusercontent.com/aryanjasala/restore-blocks-from-revisions/main/.wordpress-org/icon-128x128.png" alt="" width="32"> | [Restore Blocks from Revisions](https://github.com/aryanjasala/restore-blocks-from-revisions) | Restore a single block from an old revision, not the whole post |

## License

[GPL-2.0-or-later](LICENSE.md) © Aryan Jasala
