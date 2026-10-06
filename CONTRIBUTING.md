# Contributing to Joshternet for WordPress

Keep this plugin small.

The job is straightforward: let a WordPress origin publish a conforming `/.well-known/josh` declaration. WordPress already provides routing, settings, permissions, JSON encoding, and multisite behavior. Use those things before adding another dependency or abstraction.

Protocol changes belong in the Joshternet specification first. A pull request should not add fields, reinterpret Josh identity, create alternate endpoint paths, or invent discovery behavior only for WordPress.

Please keep a few rules in mind:

- Do not add telemetry, analytics, advertising, or silent outbound requests. The `[joshternet_button]` shortcode may make a cached lookup to the Joshternet Button State service when rendered; that is the only outbound call, and it is not automatic sitewide traffic.
- Do not infer whether somebody is a Josh. Joshness is declared, never derived.
- Do not add filesystem or web-server configuration when a WordPress API can do the job.
- Keep compatibility with managed hosting in mind. WordPress.com and WP Engine are explicit test targets.
- Add tests for behavior changes before they are merged.
- Prefer the smallest implementation that is easy for another person to read six months later.

Before a pull request merges:

```bash
composer install
composer phpcs
composer test
composer test:multisite
```

`composer test` / `composer test:multisite` need the WordPress test library from `bin/install-wp-tests.sh`. Homelab checks for `wp.barf.lan` and `wp-multi.barf.lan` are in `docs/TESTING.md`.

## Releases (WordPress.org zip)

Version lives in three places and must match:

- `joshternet.php` plugin header `Version:`
- `JOSHTERNET_VERSION` in `joshternet.php`
- `Stable tag:` in `readme.txt`

Each release attaches one artifact: `joshternet-<version>.zip` — the installable plugin for wordpress.org review/submission and Plugins → Add New → Upload. Built from `.distignore`; expands to slug folder `joshternet/` (includes screenshots and button assets; excludes `vendor/`, tests, Composer files, and repo docs).

Launch flow:

1. Bump version (optional helper): `bin/set-version.sh 0.1.1`
2. Update `CHANGELOG.md` and `readme.txt` `== Changelog ==`, commit, push; wait for CI green.
3. Publish either way:
   - **Tag (preferred):** `git tag v0.1.1 && git push origin v0.1.1`
   - **Actions UI:** Run workflow **Release**, enter `0.1.1` (dry-run builds the zip without publishing)

Local build:

```bash
bin/build-dist-zip.sh
# or
bin/build-dist-zip.sh 0.1.0
```
