# v0.5.4
## unreleased

1. [](#bugfix)
    * `limit` parameter (`type="timeline"`) incorrectly defaulted to `5` in code, while the
      docblock comment in `EmbedRenderer.php` and the README parameter table already documented
      `10` - the actual default in code never got updated when instance timelines were added and
      the documented default was raised. Code now matches the documented value.
2. [](#improved)
    * Release tags are now bare semantic versions (`0.5.3`) instead of `v
	-prefixed, matching Grav's GPM convention for version sorting and
	`releases/latest`

# v0.5.3
## 08/09/2026 ([cab1586](https://codeberg.org/chschmidt/grav-plugin-social-linking/commit/cab15864562d98472848d89ea1ab9dfb1a3ef3f3))

1. [](#new)
    * Estonian language file added

# v0.5.2
## 08/04/2026 ([54c3e6d](https://codeberg.org/chschmidt/grav-plugin-social-linking/commit/54c3e6d86fe75a2ba13f6b3133ed0b1979fcabc4))

1. [](#security)
    * fixed SSRF vulnerability: outgoing HTTP requests (API calls in `MastodonProvider` *and*
      media downloads in `MediaCache`) previously followed the `url` shortcode parameter and any
      URL returned by a remote instance's API response without validation - a crafted URL/redirect
      could reach internal addresses (loopback, private ranges, link-local/cloud metadata such as
      169.254.169.254, ...)
    * new `classes/Http/SsrfGuard.php`: validates scheme (http/https only) and resolves+checks the
      target IP (IPv4 and IPv6) against private/loopback/link-local/reserved ranges before every
      connection
    * `SimpleHttpClient` no longer follows redirects automatically; each redirect target is
      re-validated through the guard before being followed, and the validated IP is pinned via
      `CURLOPT_RESOLVE` to mitigate DNS-rebinding
    * new opt-in config `allowed_private_hosts` (YAML-only, empty by default) for site owners who
      deliberately want to embed a private/internal instance
2. [](#bugfix)
    * `composer.json` incorrectly declared `php: >=7.4.0` even though the code has used PHP 8.0
      syntax (constructor property promotion, `match`, `str_starts_with()`) since early on -
      corrected to `>=8.0.0`, matching the actual minimum. README/CONTRIBUTING.md now distinguish
      that PHP 8.0+ floor from the currently tested/supported PHP 8.3/8.5 (the version the test
      environment happens to run), instead of stating 8.3 as if the code required it.

# v0.5.1
## 08/02/2026 ([ad3b782](https://codeberg.org/chschmidt/grav-plugin-social-linking/commit/ad3b782be5252ee16edd944ef5f554639d15c2e1))

1. [](#bugfix)
    * v0.5.0 shipped language files and a translated admin form, but the actual `|t`/`|tl` calls in the templates and in `EmbedRenderer` were still missing - i18n now actually applies everywhere
    * removed an accidental duplicated copy of large parts of the plugin nested under `classes/`

# v0.5.0
## 08/01/2026 ([f848e22](https://codeberg.org/chschmidt/grav-plugin-social-linking/commit/f848e22248cf78241d7b1f95f5bfbb535d3998b6))

1. [](#new)
    * internationalization: all user-facing strings now go through Grav's language system (`languages/de.yaml`, `languages/en.yaml`, `PLUGIN_SOCIAL_LINKING.*`) - covers rendered templates, `EmbedRenderer` error messages, and the admin config form labels/help in `blueprints.yaml`

# v0.4.0
## 08/01/2026

1. [](#new)
    * sensitive media (`sensitive: true`) is now blurred by default with a click-to-reveal overlay, pure CSS/no JS
    * content warnings (`spoiler_text`) now collapse the post body behind an expandable `<details>`/`<summary>` element instead of showing both unconditionally
2. [](#breaking)
    * removed the single-account post history type entirely (was `type="timeline"` in v0.1.0-v0.3.0) - a Mastodon account can be locked/followers-only, and a server-side fetch embedded on a public page would bypass that protection regardless of what the API happens to return for a given request.
    * renamed `type="public_timeline"` (introduced in v0.3.0) to `type="timeline"` - now unambiguous since the single-account type above is gone. **Existing `[social-embed type="public_timeline" ...]` calls must be updated to `type="timeline"`.**

# v0.3.0
## 07/31/2026

1. [](#new)
    * profile type: follower/following/status counts, bio, joined date, custom profile fields (with verified badge) added
    * new type "public_timeline": instance-wide public live feed (local/remote/federated), matching the "Dieser Server"/"Externe Server"/"Alle Server" tabs in Mastodon's web UI
2. [](#bugfix)
    * acct now correctly includes the instance domain for local accounts (Mastodon API omits it for same-instance accounts)
    * README: reinstated blueprints.yaml/`<slug>.yaml` requirement note and PHP >= 8.3 requirement that were lost during manual consolidation

# v0.2.0
## 07/31/2026

1. [](#new)
    * config default values added
    * blueprints added
    * changelog added
2. [](#bugfix)
    * image path not found fixed

# v0.1.0
## 07/31/2026

1. [](#new)
    * Initial Release