# Test plan

Passing PHP syntax checks is not the same as proving a host routes `/.well-known/josh` into WordPress.

## Static checks

```bash
composer install
composer phpcs
composer test
composer test:multisite
```

- PHP syntax on every PHP file.
- WordPress Coding Standards (PHPCS / WPCS).
- WordPress Plugin Check.
- License and readme validation.
- Button faces (`join`, `undeclared`, `verified-josh`, `verified-non-josh`) via stubbed registry HTTP/cache.
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
| `/.well-known/josh/` | Not the declaration |
| `/.well-known/josh?x=1` | Not the declaration |

### Button shortcode

Publish a page containing `[joshternet_button]`.

- Page HTML must use `wp-content/plugins/joshternet/assets/buttons/…`.
- Must not load `joshternet.org/embed/joshternet-button.js`.
- Must not hotlink `joshternet.org/assets/buttons/…`.
- Registry failure → empty shortcode output.
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
| `https://wp-multi.barf.lan/site2/.well-known/josh` | Not the network declaration (path is not origin root) |

## Broader WordPress matrix

- Current stable WordPress; declared minimum (6.0).
- PHP 7.4 and current supported PHP 8.x.
- Default and pretty permalinks.
- Single-site Docker (`wp.barf.lan`) and path Multisite Docker (`wp-multi.barf.lan`).

## Withdrawal and caching

Enable, verify, disable, verify withdrawal. Plugin sends `max-age=300, must-revalidate`.
