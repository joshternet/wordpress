=== Joshternet ===
Contributors: thewoollybully
Tags: joshternet, well-known, indieweb, identity
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let a WordPress site declare itself part of the Joshternet at /.well-known/josh.

== Description ==

The Joshternet is a weird little web experiment with a useful idea underneath it: a website should be able to say for itself that it participates in the network.

This plugin publishes the RFC-JOSH-0002 declaration at the origin-root URI:

`/.well-known/josh`

The smallest valid declaration is:

`{"version":1}`

Josh identity is optional and separate from participation. A Josh can publish:

`{"version":1,"josh":true}`

A non-Josh can still participate and explicitly publish:

`{"version":1,"josh":false}`

Joshness is declared, never derived.

The declaration endpoint is served through WordPress. The plugin does not create a physical `.well-known` file, change Apache or Nginx configuration, add telemetry, register accounts, or require JavaScript.

Optional shortcode `[joshternet_button]`: WordPress calls the Joshternet registry service at `https://joshternet.org/api/button-state` for this origin's membership state, caches the result, and displays a bundled local 88x31 image. No remote script or remote image is loaded on the page.

= Multisite =

Joshternet declarations belong to origins, not WordPress site records.

Subdomain and domain-mapped sites can have independent declarations when they have different origins. Path-based subsites that share the main site's origin also share its single `/.well-known/josh` URI, so the plugin directs those administrators back to the main site.

A site published below the origin root may require hosting configuration to route `/.well-known/josh` into WordPress. The plugin warns about that case instead of pretending every hosting platform handles it the same way.

== Installation ==

1. Install and activate Joshternet.
2. Open Settings > Joshternet.
3. Enable Joshternet participation.
4. Choose whether to declare Josh identity.
5. Verify the public endpoint shown on the settings page.

== Frequently Asked Questions ==

= Does this create a physical .well-known file? =

No. WordPress serves the endpoint. That avoids depending on a writable document root or host-specific Apache and Nginx configuration.

= Does joining the Joshternet make me a Josh? =

No. Participation and Josh identity are separate. A participant may affirm Josh identity, decline it, or leave identity undeclared.

= Can a non-Josh participate? =

Yes. The Joshternet exists primarily to connect Joshes, but it is not necessarily only for Joshes.

= How does WordPress Multisite work? =

The declaration belongs to an origin. Subdomain and mapped-domain sites can publish independently when they use distinct origins. Path-based subsites on the same origin cannot each own the same origin-root `/.well-known/josh` endpoint.

= Does the plugin send anything to joshternet.org? =

Publishing `/.well-known/josh` does not call joshternet.org. There is no telemetry or registration.

The optional `[joshternet_button]` shortcode asks the Joshternet registry API for this origin's button state, caches the answer, and serves a bundled local image. If the registry cannot be read, the shortcode outputs nothing.

= How do I show the Joshternet button? =

Add `[joshternet_button]` to a post or page (Shortcode block works). Images are shipped in the plugin. No remote script is loaded.

== Screenshots ==

1. Settings > Joshternet participation and identity options.
2. Optional `[joshternet_button]` shortcode showing Join the Joshternet for an origin that is not yet a registry member.

== Changelog ==

= 0.1.0 =
* Initial pre-release implementation.
* RFC-JOSH-0002 version 1 declaration support.
* Separate affirmed, declined, and undeclared Josh identity states.
* WordPress Multisite origin awareness.
* Virtual endpoint with no filesystem or server-configuration writes.
* Optional `[joshternet_button]` shortcode with local images and registry lookup.
