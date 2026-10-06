# Changelog

## 0.1.1

- Documented the optional Button State external service in `readme.txt`, the settings screen, and the WordPress Privacy Policy Guide.
- Linked Joshternet Terms of Service and Privacy Policy.
- Hardened the Button State HTTP request (`redirection` 0, 16 KB response limit, plugin User-Agent).
- Cache a successful Button State for 6 hours and a failed lookup for 15 minutes.
- Expanded request, cache, failure, declaration-isolation, admin, and readme tests.
- CI now tests WordPress 6.0 and 7.1.2 on PHP 7.4, plus WordPress 7.1.2 on PHP 8.2.
- Path-based Multisite button images use the origin-root plugin URL, not `/{blog}/wp-content/`.
- Homelab button checks use the site home; non-canonical `/.well-known/josh` URLs must 404 with no content redirect.
- Removed the test-only runtime switch that skipped well-known endpoint headers and exit during PHPUnit.

## 0.1.0

- Publish RFC-JOSH-0002 version 1 at `/.well-known/josh`.
- Affirmed, declined, and undeclared Josh identity.
- Multisite origin awareness for path-based subsites.
- Virtual endpoint; no document-root writes or server config changes.
- Optional `[joshternet_button]` shortcode: registry lookup in PHP, bundled local 88x31 images.
- Button links allow-listed to HTTPS `joshternet.org` hosts.
- Declaration matching uses path-only URI parse (no `sanitize_text_field` on `REQUEST_URI`).
- `readme.txt` Description discloses the optional registry service; button art licensed GPL-2.0-or-later in `NOTICE.md`.
- Directory screenshots for settings and `[joshternet_button]`.
- PHPUnit coverage for all button faces and path-based Multisite origin ownership.
- Homelab Multisite testbed at `wp-multi.barf.lan`.
- `readme.txt` Contributors set to wordpress.org slug `thewoollybully`.
- Composer lock pinned to PHP 7.4 platform so CI on 7.4/8.2 can install.
- PHPCompatibility (PHPCS) for PHP 7.4+; homelab WordPress stacks run PHP 7.4.
- License/authorship note moved into `readme.txt`; `NOTICE.md` omitted from the directory zip.
- Release workflow builds one WordPress.org install zip (`bin/build-dist-zip.sh`, `bin/set-version.sh`).
- Uninstall cleanup for options and button cache.
- PHPCS, PHPUnit, and CI harness.
