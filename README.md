# Teme Companion

A read-only inventory plugin that scans your WordPress site, and a read-only export API, to help you migrate your site to Teme.

## What It Does

Teme Companion performs a one-time scan of your WordPress installation and collects information about your site's structure, content, settings, and plugins. This data is organized into a detailed JSON report that you can download and review. The report never leaves your site unless you choose to download it — no data is sent anywhere, no external requests are made. (This plugin also has a separate read-only Export API for Teme's importer — see the "Export API" section below, which explicitly does return personal data to an authenticated administrator.)

## What It Collects

The report includes 20 sections describing your site:

- **Environment**: PHP/WordPress/database versions, table prefix and charset, the named site settings listed below, theme info, PHP resource limits, and a handful of `wp-config.php` constants
- **Plugins**: Installed plugins, versions, and activation status
- **Content types**: Post types, taxonomies, and their counts
- **Post states**: Drafts, scheduled posts, revisions, trash, and other post statuses
- **Media**: Library size, media types, attachment organization
- **Meta keys**: Custom field key names (and how often each is used) across posts, terms, users, and comments — never the values
- **Options**: WordPress option names, grouped and sized (no values)
- **Tables**: Every database table with the site's prefix, core and plugin alike, with row counts and sizes
- **Users**: User roles, counts per role, and password-hash formats as counts (no usernames, emails, or actual hashes)
- **Comments**: Comment status, thread depth, and which fields are filled in (no comment content or personal data)
- **SEO**: SEO plugin data (Yoast, Rank Math, etc.) if installed, including its title templates (see below)
- **Redirects**: Redirect plugins and their redirect counts
- **Menus**: Navigation menus and their structure
- **Widgets**: Sidebars and the widget types placed in them
- **Templates**: Reusable blocks/patterns, block templates and parts, global styles, and custom CSS
- **Builders**: Page builder plugins (Elementor, Beaver Builder, Divi, etc.)
- **Multilingual**: Language plugins (WPML, Polylang, etc.) and language settings
- **Fields**: Custom field plugins (ACF, etc.) and their field types
- **Cron**: Scheduled tasks and frequency
- **Content**: Scanned blocks, shortcodes, and HTML elements in your posts and pages

## What the Site Report Never Collects

This section describes the **site report** (Tools › Teme site report) only. The separate **Export API**, documented further down, deliberately returns personal data to an authenticated administrator — that's its whole purpose. For privacy and security, the site report never collects:

- Free text a person wrote: post titles, post content, comments, or similar
- Personal data: email addresses, usernames, display names, IP addresses, or user agents
- Sensitive data: actual password hashes, API keys, credentials, or authentication tokens
- Comment text: only metadata about comments is included

There are two deliberate exceptions, and both are site **settings**, not personal data:

- **SEO title templates** (Yoast, Rank Math): patterns like `%title% | %sitename%`, not content.
- **Markup examples**, only when "Include examples" is ticked (it's ticked by default): up to 400
  characters of block or shortcode markup per distinct block/shortcode name, with any email
  addresses or IPv4 addresses inside them replaced with `[email]`/`[ip]` before the report is saved.
  Untick the box before scanning if you'd rather leave these out entirely.

## How to Run It

1. In the WordPress admin, go to **Tools › Teme site report**
2. "Include examples" is ticked by default — untick it if you'd rather leave out the short markup
   snippets described above
3. Click **Scan this site** and wait for the scan to complete
4. Once finished, click **Download JSON** to save the report to your computer

The scan may take a few minutes depending on your site's size.

## Command Line (WP-CLI)

If you have WP-CLI installed, you can generate the report from the command line:

```bash
wp teme report --output=teme-site-report.json
wp teme report --no-examples
```

Options:
- `--output=<file>`: Save the report to a specific file path (default: `teme-site-report.json` in the current directory)
- `--examples`: Include example data (default: true)
- `--no-examples`: Exclude example data for a smaller file

## Safety and Privacy

- **Read-only**: The plugin only reads data; it never modifies anything on your site
- **No outbound requests**: The report is generated locally and never sent anywhere
- **No tracking**: There are no external APIs, webhooks, or communication with other services
- **Complete control**: The report is stored only in your database until you download it; you decide what to do with it

## Removing the Plugin

To remove Teme Companion:

1. Go to **Plugins** in the WordPress admin
2. Deactivate **Teme Companion**
3. Delete **Teme Companion**

When you delete the plugin, it automatically removes its two database options:
- `teme_companion_state`: Current scan progress
- `teme_companion_report`: The downloaded report JSON

No data is left behind.

## Export API

Teme Companion also provides a read-only JSON API at `/wp-json/teme/v1/` for migrating your site's content to Teme. The API is entirely read-only — it never modifies anything on your site.

### Routes

| Route | Parameters | Default/max `limit` |
|---|---|---|
| `GET probe` | — (public) | — |
| `GET analyze` | — | — |
| `GET settings` | — | — |
| `GET users` | `after_id`, `limit` | 200 / 500 |
| `GET terms` | `after_id`, `limit` | 500 / 1000 |
| `GET media` | `after_id`, `limit`, `modified_after` | 200 / 500 |
| `GET content` | `after_id`, `limit`, `modified_after`, `types`, `statuses`, `include` | 50 / 200; **20** with `include=rendered` |
| `GET comments` | `after_id`, `limit` | 500 / 1000 |
| `GET menus` | — | — |
| `GET languages` | — (Polylang's languages, settings and translated site title and tagline; `"active": false` without Polylang) | — |
| `GET redirects` | `source`, `after_id`, `limit` | 500 / 1000 |
| `GET urls` | `kind`, `after_id`, `limit` | 1000 / 2000 |

### Authentication

The API uses **WordPress application passwords**. Only administrators can access it, and only over HTTPS.

To authenticate:
1. In the WordPress admin, go to **Users › Your Profile** (or **Users › Edit** for any administrator account)
2. Scroll down to **Application Passwords**
3. Enter an application name (e.g., "Teme import")
4. Click **Add New Application Password**
5. Copy the generated password

When making requests to the API, use HTTP Basic Authentication with your WordPress username and the application password:

```bash
curl -u "username:application_password" https://yoursite.com/wp-json/teme/v1/users
```

**When the migration is complete, revoke the application password** in Users › Profile › Application Passwords to prevent future use.

### Personal Data

The API returns personal data necessary for the migration:
- **Users:** email addresses and password hashes
- **Comments:** author names, emails, URLs, IP addresses, user agents, and comment content

This data is:
- **Readable only by administrators**, over HTTPS
- **Never logged, cached, or stored** by the plugin
- Protected by `Cache-Control: no-store` headers

### HTTPS and Proxy Environments

By default, the API requires HTTPS.

There are two ways the API accepts plain HTTP instead of requiring it:

1. **Automatically**, if WordPress's own environment type (`wp_get_environment_type()`, set via the `WP_ENVIRONMENT_TYPE` constant or server environment variable) is `local` or `development` — no plugin configuration needed. Make sure a site is set to `production` (WordPress's own default when nothing is configured) before it holds real user data you don't want served over plain HTTP.
2. **Explicitly**, via the constant below, for a reverse proxy or load balancer that terminates HTTPS before WordPress ever sees the request (so WordPress itself only sees plain HTTP even though the actual connection to the visitor was secure).

If you use a reverse proxy or load balancer that terminates HTTPS before WordPress (so WordPress only sees plain HTTP), define this constant in `wp-config.php`:

```php
define('TEME_COMPANION_ALLOW_HTTP', true);
```

This allows the API to accept HTTP requests in that scenario only. Do not enable it otherwise.

## License

GPL-2.0-or-later
