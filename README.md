# Joshternet for WordPress

The Joshternet started as a weird little web experiment about finding Joshes on the independent web, but the useful part is the protocol underneath it. A website should be able to say for itself that it is part of the network. This plugin gives WordPress that one small capability.

When enabled, the plugin publishes a JSON declaration at `/.well-known/josh` following [RFC-JOSH-0002](https://github.com/joshternet/spec/blob/main/rfcs/0002-well-known-josh.md). It does not create a physical `.well-known` file, rewrite your server configuration, phone home, add analytics, or try to turn WordPress into something else. WordPress already knows how to answer web requests. This plugin teaches it one more.

Being part of the Joshternet does not automatically make anyone a Josh. [RFC-JOSH-0001](https://github.com/joshternet/spec/blob/main/rfcs/0001-josh-identity.md) keeps identity separate from participation. A participant can affirm Josh identity, explicitly decline it, or make no identity declaration at all. Joshness is declared, never derived.

## What it publishes

A participating site can publish the minimum declaration:

```json
{"version":1}
```

A Josh can affirm Josh identity:

```json
{"version":1,"josh":true}
```

A non-Josh can participate and explicitly decline Josh identity:

```json
{"version":1,"josh":false}
```

That is intentionally the whole payload for version 1. Profile data, feeds, discovery metadata, and anything else belong in their own specifications if the Joshternet ever actually needs them.

## Why the endpoint is virtual

Writing a literal file into a site's document root sounds simple until WordPress is running on managed hosting, WordPress.com, a read-only deployment, or a multisite network. The plugin therefore serves `/.well-known/josh` through WordPress instead of trying to manage Apache, Nginx, `.htaccess`, or the filesystem.

That keeps the plugin small and gives us one implementation to test across ordinary WordPress hosting, WordPress.com, WP Engine, and other managed platforms. It is designed for those environments, but we do not call a platform supported until the plugin has been exercised there. The compatibility matrix belongs in tests, not marketing copy.

## Multisite

The Joshternet declaration belongs to an **origin**, not to a WordPress database record.

A subdomain site such as `josh.example.com` has a different origin from `www.example.com`, so it can publish its own declaration. A domain-mapped site can do the same when its mapped URL has its own origin.

Path-based sites are different. `example.com/site-a/` and `example.com/site-b/` share the `https://example.com` origin, and there is only one `https://example.com/.well-known/josh`. The plugin therefore leaves that declaration with the main site instead of pretending two paths can own the same origin-root resource.

If a WordPress site itself is published below the origin root, the plugin warns the administrator. That arrangement can work only when the host routes the root `/.well-known/josh` request into WordPress, and that is something we need to verify on the actual host.

## Button shortcode

`[joshternet_button]` is optional. When it needs a button state and there is no usable local cache, WordPress (not the visitor's browser) queries the Joshternet Button State service at `https://joshternet.org/api/button-state` with this site's normalized website origin. A successful result is cached locally for up to 6 hours; a failed lookup is cached for 15 minutes. A bundled 88x31 image is then served from the plugin (origin-root `wp-content/plugins/`, including on path-based Multisite). Page HTML does not load a remote script or remote image. Button links are limited to HTTPS `joshternet.org` hosts. If the registry is unreachable or returns an unexpected payload, the shortcode renders nothing.

Publishing `/.well-known/josh` itself still does not call joshternet.org. Terms and Privacy: `https://joshternet.org/terms/` and `https://joshternet.org/privacy/`.

## What it does not do

No telemetry, cron, filesystem writes, custom tables, or Joshternet accounts. No crawl. No central signup. Publishing the declaration is the declaration.

That is deliberate. If the protocol can stay boring, it probably should.

## Installation

1. Install the plugin ZIP through WordPress or place the `joshternet` directory in `wp-content/plugins/`.
2. Activate **Joshternet**. Multisite network activation is supported by the code and is part of the test matrix for the first release.
3. Open **Settings > Joshternet**.
4. Enable participation.
5. Choose whether to make a Josh identity declaration.
6. Verify the public `/.well-known/josh` endpoint.

## Status

This is pre-release code. The implementation has been reviewed against RFC-JOSH-0001 and RFC-JOSH-0002 and is being prepared for a public repository. Host compatibility claims will be added only after the repository test matrix passes against the relevant WordPress and hosting environments.

## Releases

Version is controlled in-repo (`joshternet.php` + `readme.txt` Stable tag). GitHub Actions workflow **Release** builds one WordPress.org install zip (`joshternet-<version>.zip`) on `v*` tags or manual **workflow_dispatch** (optional dry-run). Full steps: `CONTRIBUTING.md`.

## Development rule

Protocol behavior comes from the Joshternet specifications. A plugin change should not quietly invent new protocol behavior because it seems convenient in WordPress. If the protocol needs to change, change the specification first and then change the implementation.

## License

The WordPress plugin is copyright 2026 Joshua Morris and is licensed under the GNU General Public License, version 2 or any later version, `GPL-2.0-or-later`.

The Joshternet RFCs are separate specification documents and retain the licenses stated in their source repository. RFC-JOSH-0001 and RFC-JOSH-0002 are licensed under CC BY 4.0. Referencing or implementing those specifications does not change the license of this plugin.
