# Test plan

Passing PHP syntax checks is not the same as proving a host routes `/.well-known/josh` into WordPress.

## Static checks

```bash
composer install
composer phpcs
composer test
composer test:multisite
bin/verify-version.sh 0.1.1
bin/build-dist-zip.sh
```

WordPress.org packaging (GitHub Actions **Release** workflow) is documented in `CONTRIBUTING.md`. Plugin Check and the WordPress.org readme validator are **release-gate** commands, not CI jobs: Plugin Check requires WordPress 6.3+, which is newer than the plugin's documented minimum.

GitHub Actions CI (`.github/workflows/ci.yml`) covers:

- PHPCS on PHP 8.2
- PHP 7.4 + WordPress 6.0
- PHP 7.4 + WordPress 7.1.2
- PHP 8.2 + WordPress 7.1.2

Each PHPUnit cell runs single-site and Multisite suites.

- PHP syntax on every PHP file.
- WordPress Coding Standards (PHPCS / WPCS).
- WordPress Plugin Check (release gate).
- License and readme validation (release gate).
- Button faces (`join`, `undeclared`, `verified-josh`, `verified-non-josh`) via stubbed registry HTTP/cache.
- Button State request contract, local cache, fail-closed responses, and declaration isolation (PHPUnit: `declaration()` never triggers Button State HTTP).
- Path-based Multisite origin ownership via `composer test:multisite`.

## Homelab: wp.barf.lan

Stack lives on `barf.lan` at `/srv/docker/wordpress` (Docker WordPress + MariaDB, NPM, AdGuard).

PHP runtime for both homelab stacks is **PHP 7.4** (`wordpress:php7.4-apache`, last official Docker Hub tag that still ships 7.4). Site files stay on the named volume (current WordPress), so we exercise the declared minimum PHP against a modern WP install. Official Docker Hub no longer publishes WordPress 6.8 + PHP 7.4 images.

```bash
# DNS
dig @192.168.1.164 wp.barf.lan +short
# expect 192.168.1.164

# Protocol matrix (from a host that can reach barf)
curl -ksS --resolve wp.barf.lan:443:192.168.1.164 -i https://wp.barf.lan/.well-known/josh
```

### Protocol cases

| Setting | Expect |
| --- | --- |
| Participation off | Not a valid declaration (typical WP 404) |
| On + undeclared | `{"version":1}` |
| On + affirmed | `{"version":1,"josh":true}` |
| On + declined | `{"version":1,"josh":false}` |
| HEAD | Same status/headers, empty body |
| POST/PUT/etc. | `405` with `Allow: GET, HEAD` |
| `/.well-known/josh/` | Not the declaration: **404**, no redirect to a content page |
| `/.well-known/josh?x=1` | Not the declaration: **404**, no redirect to a content page |

### Button shortcode

Put `[joshternet_button]` on the site home (front page or latest posts). Do **not** create a test page titled or slugged Joshternet Button: WordPress 404 permalink guessing matches `josh%` and will 301 `/.well-known/josh` lookalikes onto that page.

- Page HTML must use origin-root `wp-content/plugins/joshternet/assets/buttons/…` (not `/{blog}/wp-content/…` on path Multisite).
- Must not load `joshternet.org/embed/joshternet-button.js`.
- Must not hotlink `joshternet.org/assets/buttons/…`.
- Registry failure → empty shortcode output.
- Live Apache `GET /.well-known/josh` must produce **0** Button State requests (outbound HTTP logger). PHPUnit cannot continue after the real handler `exit`s; that suite only asserts `declaration()` never calls the service.
- Settings > Joshternet and the Privacy Policy Guide must disclose the optional Button State request, the normalized website origin, the 6-hour success cache, the 15-minute failure cache, and Joshternet Terms/Privacy links.
- All four displayable faces are covered in PHPUnit with stubbed registry payloads (no live membership required).

WP-CLI helpers on barf:

```bash
docker exec wordpress wp option update joshternet_enabled 1 --allow-root
docker exec wordpress wp option update joshternet_identity affirmed --allow-root
```

## Homelab Multisite: wp-multi.barf.lan

Path-based network at `/srv/docker/wordpress-multi`.

```bash
dig @192.168.1.164 wp-multi.barf.lan +short
curl -ksS --resolve wp-multi.barf.lan:443:192.168.1.164 https://wp-multi.barf.lan/.well-known/josh
```

| Check | Expect |
| --- | --- |
| Main site participation on | Origin-root declaration from main site settings |
| Path subsite `/site2/` | Settings UI: shared-origin notice; no second origin-root declaration |
| `https://wp-multi.barf.lan/site2/.well-known/josh` | **404**, no redirect, not a second declaration |
| `[joshternet_button]` on `/site2/` home | Same local PNG as the main site (`/wp-content/plugins/joshternet/assets/buttons/…`) |

## Broader WordPress matrix

- Current stable WordPress; declared minimum (6.0).
- PHP 7.4 and current supported PHP 8.x.
- Default and pretty permalinks.
- Single-site Docker (`wp.barf.lan`) and path Multisite Docker (`wp-multi.barf.lan`).

## Withdrawal and caching

Enable, verify, disable, verify withdrawal. Plugin sends `max-age=300, must-revalidate`.
