=== Joshternet ===
Contributors: TheWoollyBully
Tags: joshternet, well-known, indieweb, identity
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Join the Joshternet from WordPress. Joshes and non-Joshes participate equally; Josh identity is optional.

== Description ==

The Joshternet is an open network for independent websites. Everyone is welcome: Joshes and non-Joshes are equal participants, with the same standing in the network. Being a Josh is not a requirement for joining.

A website should be able to say for itself that it participates in the network.

This plugin publishes the RFC-JOSH-0002 declaration at the origin-root URI:

`/.well-known/josh`

The smallest valid declaration is:

`{"version":1}`

Josh identity is optional and separate from participation. Affirming, declining, or leaving Josh identity undeclared does not change your status in the network. A Josh can publish:

`{"version":1,"josh":true}`

A non-Josh can participate equally and explicitly publish:

`{"version":1,"josh":false}`

Joshness is declared, never derived.

The declaration endpoint is served through WordPress. The plugin does not create a physical `.well-known` file, change Apache or Nginx configuration, add telemetry, register accounts, or require JavaScript.

Optional shortcode `[joshternet_button]`: WordPress may query the Joshternet Button State service for this origin, caches the result locally, and displays a bundled 88x31 image. See External services. No remote script or remote image is loaded on the page.

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

== External services ==

The plugin's primary function is publishing a Joshternet declaration at `/.well-known/josh`. Publishing that declaration does not contact Joshternet.org or any other external service.

The optional `[joshternet_button]` shortcode uses the Joshternet Button State service:

https://joshternet.org/api/button-state

When the shortcode is rendered and there is no usable cached result, this site's WordPress server sends an HTTPS GET request to that URL. The request includes this site's normalized website origin as the `origin` query parameter, for example `https://example.com`. The User-Agent identifies the plugin version and repeats that same origin. The service uses the origin to determine the site's state in the public Joshternet registry and returns the appropriate button information. Ordinary connection metadata such as the WordPress server's IP address can be visible to the service.

The plugin does not send WordPress usernames, email addresses, posts, comments, visitor identities, cookies, analytics identifiers, the current visitor's page URL, or the contents of `/.well-known/josh` to the Button State service.

A successful result is cached locally in WordPress for up to 6 hours. A failed or invalid lookup is cached for 15 minutes so an outage does not contact the service on every page view. If the service is unavailable or its response cannot be validated, the shortcode displays nothing.

The button images are bundled with the plugin. The shortcode does not load a remote image or remote JavaScript.

Joshternet Terms of Service:
https://joshternet.org/terms/

Joshternet Privacy Policy:
https://joshternet.org/privacy/

== Frequently Asked Questions ==

= Does this create a physical .well-known file? =

No. WordPress serves the endpoint. That avoids depending on a writable document root or host-specific Apache and Nginx configuration.

= Does joining the Joshternet make me a Josh? =

No. Participation and Josh identity are separate. A participant may affirm Josh identity, decline it, or leave identity undeclared. All three choices have equal status in the network.

= Can a non-Josh participate? =

Yes. Non-Joshes are welcome as equal participants, with the same standing as Joshes. Josh identity is optional; it does not determine membership or status in the network.

= How does WordPress Multisite work? =

The declaration belongs to an origin. Subdomain and mapped-domain sites can publish independently when they use distinct origins. Path-based subsites on the same origin cannot each own the same origin-root `/.well-known/josh` endpoint.

= Does the plugin send anything to joshternet.org? =

Publishing `/.well-known/josh` does not call joshternet.org. There is no telemetry or registration.

The optional `[joshternet_button]` shortcode is the only feature that contacts Joshternet.org. See **External services** for when that request happens, what normalized website origin is sent, how long the result is cached, and the Joshternet Terms of Service and Privacy Policy.

= How do I show the Joshternet button? =

Add `[joshternet_button]` to a post or page (Shortcode block works). Images are shipped in the plugin. No remote script is loaded.

== Screenshots ==

1. Settings > Joshternet participation and identity options.
2. Optional `[joshternet_button]` shortcode showing Join the Joshternet for an origin that is not yet a registry member.

== License and authorship ==

Copyright 2026 Joshua Morris.

This plugin is licensed under GPLv2 or later. The full GPL version 2 text is in LICENSE.

The Joshternet specifications (RFC-JOSH-0001, RFC-JOSH-0002) are separate works authored by Joshua Morris for the Joshternet and licensed under CC BY 4.0. This plugin implements those specifications; it does not relicense them under the GPL, and the specification licenses do not relicense this plugin.

The 88x31 PNGs in assets/buttons/ are Joshternet button artwork authored by Joshua Morris for the Joshternet project. For distribution with this plugin they are licensed under GPLv2 or later, the same as the plugin code.

== Changelog ==

= 0.1.1 =
* Documented the optional Joshternet Button State external service in readme, settings, and the Privacy Policy Guide.
* Linked Joshternet Terms of Service and Privacy Policy.
* Hardened the Button State HTTP request and expanded external-service and cache tests.
* Cache a successful Button State for 6 hours and a failed lookup for 15 minutes.
* Reconciled WordPress compatibility CI with the published Tested up to claim.
* Path-based Multisite shortcode images load from origin-root `wp-content/plugins/`, not `/{blog}/wp-content/`.

= 0.1.0 =
* Initial pre-release implementation.
* RFC-JOSH-0002 version 1 declaration support.
* Separate affirmed, declined, and undeclared Josh identity states.
* WordPress Multisite origin awareness.
* Virtual endpoint with no filesystem or server-configuration writes.
* Optional `[joshternet_button]` shortcode with local images and registry lookup.
