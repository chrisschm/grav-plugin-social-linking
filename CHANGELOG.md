# v0.5.1
## 08/02/2026

1. [](#bugfix)
    * v0.5.0 shipped language files and a translated admin form, but the actual `|t`/`|tl` calls in the templates and in `EmbedRenderer` were still missing - i18n now actually applies everywhere
    * removed an accidental duplicated copy of large parts of the plugin nested under `classes/`

# v0.5.0
## 08/01/2026

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