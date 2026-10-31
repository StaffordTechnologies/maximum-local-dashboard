=== Maximum Local Dashboard ===
Contributors: staffordtechnologies
Donate link: https://staffordtechnologies.net/
Tags: analytics, ga4, google analytics, dashboard, search console
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plain-English campaign dashboard for WordPress. Tracks conversions to GA4 and surfaces campaign results for administrators.

== Description ==

Maximum Local Dashboard is a free plugin from Stafford Technologies. It gives site administrators a Campaign Dashboard that pulls overview, campaign, conversion, traffic, and Search Console data from Google, and it fires front-end conversion events to Google Analytics 4 (GA4).

It is not affiliated with, endorsed by, or sponsored by Google or Alphabet. A Google Cloud service account with access to your GA4 property (and optionally Search Console) is required for the admin reports. A GA4 measurement ID is required for front-end conversion tracking.

Features:

* Admin Campaign Dashboard (Overview, Campaigns, Conversions, Traffic, Search, Settings)
* GA4 Data API reports via a Google service account (JWT / OpenSSL, no Composer)
* Google Search Console query report
* Front-end conversion tracking to GA4 (phone, email, outbound, file download, CTA, form submit)
* Configurable CTA selectors, form selectors, and download extensions
* Production host gate so staging only tracks with `?mld_debug=1` (legacy `?ml_debug=1` still works)
* Service account JSON encrypted at rest (OpenSSL AES-256-CBC derived from WordPress salts, with plaintext fallback if OpenSSL is unavailable)
* Option keys kept from v1.0.0 (`ml_settings`, `ml_service_account_json`) for upgrade compatibility
* No license keys and no remote update server

Author: Stafford Technologies — https://staffordtechnologies.net/
Plugin docs and support: https://staffordtechnologies.net/maximum-local-dashboard/
Support email: helpdesk@staffordtechnologies.net

This plugin is free ($0). No license activation or site-count limit.

== External services ==

This plugin connects to Google APIs for analytics reports and optional front-end tracking.

* **Google Analytics Data API** — When an administrator opens the Campaign Dashboard Overview, Campaigns, Conversions, or Traffic tabs (and a service account plus GA4 property ID are configured), the WordPress server calls `https://analyticsdata.googleapis.com/v1beta/properties/{propertyId}:runReport` via `wp_remote_post` with a short-lived OAuth access token. Request bodies include the date range and metric/dimension names you selected in the UI; no visitor form field contents are sent.
* **Google Search Console API** — When an administrator opens the Search tab (and a service account plus Search Console site URL are configured), the server calls `https://www.googleapis.com/webmasters/v3/sites/{siteUrl}/searchAnalytics/query` via `wp_remote_post`.
* **Google OAuth token endpoint** — Before those API calls, the server exchanges a signed service-account JWT for an access token at `https://oauth2.googleapis.com/token` via `wp_remote_post`. The private key never leaves your WordPress server.
* **Front-end gtag (Google Tag / gtag.js)** — When a GA4 measurement ID is saved and tracking is allowed for the current host, the browser loads `https://www.googletagmanager.com/gtag/js?id={MEASUREMENT_ID}` and this plugin's tracking script sends conversion events (for example phone_click, form_submit) to GA4. UTM parameters may be stored in a first-party cookie (`mld_utm`, SameSite=Lax, Secure on HTTPS) and sessionStorage.

Terms and privacy:

* [Google APIs Terms of Service](https://developers.google.com/terms)
* [Google Privacy Policy](https://policies.google.com/privacy)

== Installation ==

1. In WordPress admin, go to Plugins → Add New → Upload Plugin.
2. Upload `maximum-local-dashboard-1.1.0.zip` (or install from the WordPress.org plugin directory when listed) and click Install Now, then Activate.
3. Open **Campaign Dashboard** in the admin menu.
4. On the **Settings** tab, paste a Google Cloud service account JSON key (type `service_account`), set your GA4 property ID, GA4 measurement ID, optional Search Console site URL, and production hostname, then save.
5. In Google Cloud / GA4 / Search Console, grant that service account Viewer (or equivalent) access to the property and site.
6. Optionally adjust CTA selectors, form selectors, and download extensions under Settings.
7. On production, browse the site and confirm conversion events appear in GA4. On staging, append `?mld_debug=1` to fire debug-mode events.

== Frequently Asked Questions ==

= Is this free? =

Yes. Maximum Local Dashboard is free ($0). There are no license keys and no paid update server.

= Do I need a Google service account? =

Yes for the admin reports. Create a service account in Google Cloud, download the JSON key, paste it under Campaign Dashboard → Settings, and grant it access to your GA4 property (and Search Console if you use the Search tab).

= Is the service account key safe? =

The JSON is stored in a WordPress option and encrypted at rest with OpenSSL AES-256-CBC when OpenSSL is available (key material derived from WordPress salts). It is never returned to the browser after save. Clear it from Settings to remove it. Uninstalling the plugin deletes the options and plugin transients.

= Will tracking run on staging? =

Only if the current hostname matches the Production host setting, or if you add `?mld_debug=1` (or legacy `?ml_debug=1`). When the Production host setting is empty, tracking stays off unless the debug flag is present.

= Does this load Google's gtag.js? =

Yes, when a GA4 measurement ID is configured. See External services.

= Where do I get support? =

Email Stafford Technologies helpdesk at helpdesk@staffordtechnologies.net.
Plugin docs and support page: https://staffordtechnologies.net/maximum-local-dashboard/
Company site: https://staffordtechnologies.net/

= Can I upgrade from the private maximum-local 1.0.0 install? =

Yes. Option keys `ml_settings` and `ml_service_account_json` are kept. On activation, a legacy plaintext service account value is re-encrypted when possible. Replace the old plugin folder with this one (deactivate old, install this zip, activate). Review Settings — client-specific defaults from 1.0.0 are no longer baked in.

== Changelog ==

= 1.1.0 =
* Public GPLv2+ release prepared for WordPress.org as Maximum Local Dashboard (slug `maximum-local-dashboard`).
* Removed all client-specific defaults (GA4 IDs, production host, Search Console URL, Network for Good / support CTA selectors). Settings start empty; tracking selectors are configurable with safe generic defaults.
* Encrypt service account JSON at rest (OpenSSL AES-256-CBC, key derived from `wp_salt( 'auth' )`) with graceful plaintext fallback; re-encrypt legacy plaintext on activate.
* Front-end UTM cookie now sets the Secure flag on HTTPS; load gtag.js when a measurement ID is configured; form submits honor configured form selectors.
* No license keys or remote update server. Stafford Technologies branding (author, URIs, Docs/Support `plugin_row_meta`, icons).
* Document External services (GA Data API, Search Console API, OAuth token endpoint, front-end gtag). Tidied uninstall SQL for `ml_*` / `mld_*` transients. Kept option keys `ml_settings` / `ml_service_account_json` for upgrades.

= 1.0.0 =
* Initial private Campaign Dashboard pilot (GA4 + Search Console reports, front-end conversion tracking).

== Upgrade Notice ==

= 1.1.0 =
Public free release. Client-specific baked-in defaults are removed — re-enter GA4 / Search Console / host settings after upgrade. Service account JSON is encrypted at rest.
