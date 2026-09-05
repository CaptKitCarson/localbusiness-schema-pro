=== LocalBusiness Schema Pro ===
Contributors: kitmobley
Tags: schema, local seo, structured data, json-ld, localbusiness
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

LocalBusiness JSON-LD schema for service-area operators: charter captains, tour operators, mobile services, and multi-location businesses.

== Description ==

Rank Math and Yoast Local handle one storefront well. This plugin is for the operator with a fleet, a route, or a service area, which is where those plugins stop.

**Built for:**

* Charter captains (fishing, sailing, diving)
* Tour operators (walking, food, adventure)
* Home services (HVAC, plumbing, electricians, roofers)
* Mobile services (mechanics, groomers, detailers)
* Multi-location retail (3-25 storefronts)
* Legal, dental, medical multi-site practices
* Hyperlocal agencies managing 25+ client sites

**How it plays with Yoast / Rank Math / AIOSEO:**

By default, we emit alongside using our own `@id` namespace so the two graphs never collide. Prefer to yield when a Local SEO plugin is active? Flip the coexistence setting to "defer_if_present" and we step aside.

= Free =

* 1 Location with full LocalBusiness JSON-LD
* Address, geo, phone, hours, sameAs, areaServed (city/region names), image
* Auto-inject on homepage
* Per-post location override
* Coexistence with Yoast / Rank Math / AIOSEO

= Solo Pro, $99/yr, 1 site =

* Up to 5 Locations
* Services / Trips catalog with pricing + validFrom / validThrough seasonal availability
* Automatic plugin updates from kitmobley.com
* Priority support

= Agency Pro, $249/yr, 25 sites =

* Everything in Solo, across up to 25 client sites
* 25 Locations per site
* Deactivate + move between clients freely

Buy at [https://kitmobley.com/plugins/localbusiness-schema-pro/](https://kitmobley.com/plugins/localbusiness-schema-pro/)

== External services ==

This plugin does not contact any external service on a normal free install.

If, and only if, you enter a Pro licence key on the plugin settings screen, the
plugin contacts the licence API at https://kitmobley.com/api to validate that key.
It sends the licence key you entered, this plugin's slug, and your site URL, so
the licence can be checked and counted against the seats you bought. Once a key is
stored, that check repeats weekly to confirm the licence is still valid.

No request is made if no licence key has been entered. The free version never
transmits anything, and the plugin does not send visitor, post, or analytics data
at any point.

Terms: https://kitmobley.com/terms
Privacy policy: https://kitmobley.com/privacy

== Installation ==

1. Upload the plugin ZIP through **Plugins → Add New → Upload Plugin**, or extract to `/wp-content/plugins/localbusiness-schema-pro/`
2. Activate through the **Plugins** menu
3. Go to **Locations → Add New**, fill in the fields, publish
4. Go to **Locations → Settings** and select your primary Location for the homepage

== Frequently Asked Questions ==

= How is this different from Rank Math or Yoast Local? =

Rank Math and Yoast Local handle one storefront well. This plugin is for operators with a fleet, a route, or a service area: multiple named locations, a catalog of trips or services with seasonal availability, and areaServed metadata that reaches beyond a single storefront address.

= Will it clash with my existing SEO plugin? =

No. Default is coexistence. We emit alongside with our own @id namespace so the two graphs don't collide. You can flip to "defer_if_present" in settings if you'd rather we step aside when Yoast Local / Rank Math Local / AIOSEO Local is active.

= What does the free tier include? =

One Location with full LocalBusiness JSON-LD (address, geo, phone, hours, sameAs, areaServed, image), auto-injection on the homepage, and per-post location override.

= What's on the v1.1 roadmap? =

Leaflet-based polygon service-area drawer (GeoShape polygons in `areaServed`), CSV bulk import for multi-location clients, and per-tenant GBP sync exploration.

== Changelog ==

= 1.0.1 =
* Internal refactor: license and updater code now sourced from the shared kitmobley/wp-plugin-core library, bundled at build time under includes/vendor/kitmobley-core/. Zero behavior changes: options keys, AJAX action names, and admin UI preserved.

= 1.0.0 =
* Initial release
* Location CPT with 30+ LocalBusiness subtypes
* Service / Trip CPT (Pro) with OfferCatalog integration
* JSON-LD auto-injection with home + singular scope + per-post override
* Coexistence detection for Yoast Local / Rank Math Local / AIOSEO Local
* License activation UI with weekly cron re-validation
* Automatic plugin updates via kitmobley update-server

== Upgrade Notice ==

= 1.0.0 =
First release. Install, add your first Location, set it as primary, and schema goes live.
