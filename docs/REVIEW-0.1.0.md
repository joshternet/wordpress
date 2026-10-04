# Code review: pre-release 0.1.0

## What the prototype actually does

The original prototype is intentionally small. It adds a Settings > Joshternet page, stores two site-local options, and intercepts the exact well-known request after WordPress has selected the current site. When enabled, it builds an RFC-JOSH-0002 version 1 JSON object from those settings and returns it without writing a physical file.

The prototype does not crawl, register, phone home, create database tables, modify `.htaccess`, modify Nginx configuration, schedule cron work, or add front-end JavaScript. That is the right shape for this plugin.

## Findings addressed in this review

### Canonical URI handling

The prototype compared only the parsed URL path. That meant a request such as `/.well-known/josh?anything=here` was accepted because its parsed path was still `/.well-known/josh`.

RFC-JOSH-0002 defines no query parameters for the declaration URI. The reviewed candidate now rejects a request target containing a query component before considering it the canonical endpoint.

### Authorship and licensing

The prototype header used `Joshua Morris / Joshternet` as the author and declared GPL-2.0-or-later, but the package did not include the license text or explain how the plugin license relates to the Joshternet RFC licenses.

The reviewed candidate identifies Joshua Morris as the author, identifies Joshternet as the project, includes the GPL version 2 license text, uses GPL-2.0-or-later in source metadata, and adds a notice explaining that the Joshternet RFCs remain separately licensed specification documents.

### Project voice

The prototype documentation was technically accurate but read like implementation notes. The reviewed README, WordPress readme, settings copy, contributing guide, and security notes now explain the project in the same terms as the specifications: participation is voluntary, identity is separate, non-Joshes may participate, and Joshness is declared rather than inferred.

### Compatibility claims

The prototype described the package as appropriate for WordPress.com, WP Engine, and similar platforms before those platforms had been exercised.

The reviewed documentation now calls those platforms explicit test targets rather than verified support. This distinction should remain until public endpoint tests pass on the actual hosting environments.

## Security review

No critical or high-severity security issue was found in this small implementation during source review.

The settings page requires `manage_options` and uses the WordPress Settings API, which supplies the normal nonce and options-update flow. The public endpoint intentionally requires no authentication. It reflects no request data, writes no files, executes no submitted code, and makes no outbound requests.

The declaration body is generated with `wp_json_encode()` from a fixed integer and an allow-listed identity state. The administrator-facing preview is escaped before output.

The endpoint returns `X-Content-Type-Options: nosniff` and a JSON content type. Unsupported methods return `405` with an `Allow` header.

## Multisite review

The implementation correctly recognizes the common path-based Multisite case where a child site and the network main site share one origin. It prevents the child settings page from pretending that a path can own a second origin-root declaration.

Subdomain and root domain-mapped sites can keep site-local settings because WordPress resolves the current site before the `parse_request` hook runs.

More unusual combinations of domain mapping and path-based sites still belong in the integration test matrix. The source review is not enough to claim that every Multisite topology behaves correctly on every host.

## Deliberate non-features

The reviewed candidate does not add a central Joshternet registration call, telemetry, profile fields, crawler integration, discovery metadata, custom REST endpoints, or a host-specific cache purge API. None of those are required to implement RFC-JOSH-0002 version 1.

The plugin should stay this small unless a specification or a demonstrated interoperability problem gives us a reason to expand it.

## Still unverified

Before 0.1.0 should be called a release, the repository needs an automated test harness and host testing. In particular, we still need to verify WordPress 7.1.x, the declared WordPress/PHP minimums, Multisite network activation, WordPress.com, WP Engine, ordinary Apache and Nginx routing, and withdrawal behavior through host/CDN caches.

`docs/TESTING.md` is the proposed matrix for that work.
