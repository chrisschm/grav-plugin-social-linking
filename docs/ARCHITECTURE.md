# Architecture

This document explains how the plugin is built and *why* certain decisions were made. It's aimed
at contributors who want to change code, not at end users configuring the plugin (see `README.md`
for that). *(Eine deutsche Kurzfassung findest du am Ende dieser Datei.)*

## Purpose

Embeds posts, profiles, and instance-wide live feeds from Mastodon (and any service with a
Mastodon-compatible Client API, e.g. Pleroma/Akkoma/GoToSocial) into Grav pages — visually close
to the original service, but connected to the site's theme via CSS custom properties.

## Design goals

These apply to any future change (see also `CONTRIBUTING.md`):

- Data is fetched from the API **once** and cached **file-based in the page's own folder**;
  rendering afterwards happens exclusively from that local cache, never live from the API on
  every page view.
- The call convention was designed from the start to extend beyond single posts (profiles, feeds)
  — new `type` values can be added without touching storage/caching.
- **Privacy over feature scope:** nothing is offered that could work around the followers-only
  protection of a protected Mastodon account (see "Deliberate non-implementation" below).
- Sensitive content (content warnings, `sensitive: true` media) is rendered safely by default
  (collapsed/blurred), never passed through unfiltered.
- Internationalizable via Grav's standard language files — no hardcoded text in templates or
  error messages.

## File layout

```
user/plugins/social-linking/
├── social-linking.php              # main class SocialLinkingPlugin, own spl_autoload_register
├── blueprints.yaml                 # Admin panel form + metadata (required filename!)
├── social-linking.yaml             # default configuration values (also required, see "Notable past bugs")
├── classes/
│   ├── Http/SimpleHttpClient.php          # lightweight HTTP client (curl preferred, stream fallback)
│   ├── Provider/
│   │   ├── ProviderInterface.php          # extension point for further services
│   │   ├── ProviderRegistry.php
│   │   └── MastodonProvider.php           # API access + normalization to the internal schema
│   ├── Shortcode/EmbedRenderer.php        # core logic: read/write cache, call provider, render template
│   └── Storage/
│       ├── EmbedStorage.php               # JSON cache per page folder
│       └── MediaCache.php                 # local caching of avatar/media URLs
├── shortcodes/SocialLinkShortcode.php     # tag registration with Shortcode Core
├── cli/
│   ├── RefreshCommand.php                 # bin/plugin social-linking refresh [page-folder]
│   └── PurgeCommand.php                   # bin/plugin social-linking purge [page-folder] [--yes]
├── templates/partials/social-linking/
│   ├── mastodon-status.html.twig
│   ├── mastodon-profile.html.twig
│   ├── mastodon-timeline.html.twig        # instance-wide live feed, NOT a single-account history
│   └── error.html.twig
├── css/social-linking.css                 # CSS-custom-property-based theme integration
├── languages/{de,en}.yaml
├── composer.json                          # metadata + PHP version only, no PSR-4 autoload block —
│                                           #   this plugin uses its own spl_autoload_register(), see below
├── LICENSE (MIT)
└── .github/workflows/release-from-tag.yml # release notes generated automatically from the tag
```

## Two integration paths

1. `[social-embed ...]` — a shortcode directly in Markdown page content (dependency: the
   **Shortcode Core** plugin), the main path for users without Twig knowledge.
2. `{{ social_embed({...}) }}` — a Twig function (`onTwigInitialized`) for theme developers, same
   parameter convention. Both paths internally call the same `EmbedRenderer::render()` method — if
   you change one, check whether the other needs the same fix.

Unlike `feedteasers`, this plugin depends on the community **Shortcode Core** plugin instead of
implementing its own bracket-syntax parsing — deliberate choice, since `social-embed` needed a
richer parameter set (`type`, `service`, `limit`, `refresh`, `delete`) than a single boolean-style
flag, and Shortcode Core already solves attribute parsing robustly.

## Call convention

```
[social-embed url="https://norden.social/@user/113..." ]                        {# type=status, default #}
[social-embed type="profile" url="user@norden.social"]
[social-embed type="timeline" url="https://norden.social/public/local"]         {# live feed "this server" #}
[social-embed type="timeline" url="https://norden.social/public/remote"]        {# "federated servers" #}
[social-embed type="timeline" url="https://norden.social/public"]               {# "all servers" #}
[social-embed url="..." refresh="true"]   {# force cache refresh #}
[social-embed url="..." delete="true"]    {# delete cache entry incl. media #}
```

Additional parameters: `service` (default `mastodon`), `limit` (only for `type="timeline"`,
default 10).

## Storage

A subfolder `_social-linking/` (name configurable via `storage_subfolder`) inside each page
folder: one JSON file per embed plus locally downloaded media (avatar, attachments, card image).
Media URLs deliberately reference the **physical path relative to `GRAV_ROOT`**
(e.g. `/user/pages/01.home/_social-linking/.../file.jpg`), not the "clean" page route — see
"Notable past bugs" #2 for why.

## Configurable options (Admin panel)

| Option | Purpose |
|---|---|
| `enabled` | Plugin on/off |
| `storage_subfolder` | Name of the per-page cache subfolder, default `_social-linking` |
| `timeout` | API timeout in seconds, default 10 |
| `tokens` | Optional per-instance access tokens (YAML-only, deliberately not exposed as an Admin form field) |

If you add a new configurable option, it needs an entry in `blueprints.yaml` and a default in
`social-linking.yaml` — and, if it's user-facing text, translation keys in `languages/*.yaml` (see
"Internationalization" below).

## Provider architecture

`classes/Provider/ProviderInterface.php` is the extension point for services beyond Mastodon.
`ProviderRegistry` looks up the configured/detected provider by `service`; `MastodonProvider`
normalizes the Mastodon API response shape into the plugin's internal schema (the shape that
`EmbedStorage`, `MediaCache`, and the templates all rely on). Adding a new service means
implementing the interface and adding matching templates — storage and caching don't need to
change. See `CONTRIBUTING.md` for the contributor-facing walkthrough of this.

## Deliberate non-implementation

A `type="timeline"` for the post history **of a single account** was initially built, then fully
removed again (provider method, interface, template, README): a Mastodon account can be
"protected" (posts visible only to followers), and a server-fetched, publicly embedded list would
effectively bypass that protection — regardless of whether the API would actually return data in
a given case. The name `type="timeline"` has stood, since then, for the instance-wide, by-definition
public live feed instead (formerly `type="public_timeline"`, renamed once the naming collision
became moot after the removal above).

## Content warnings & sensitive media

- `spoiler_text` → a native `<details>`/`<summary>` element, post text expandable on click (no
  JavaScript).
- `sensitive: true` → all media of a post blurred by default, "show media" overlay to reveal
  (pure CSS checkbox hack, no JavaScript).
- Known, permanent limitation: an "auto-expand" preference a Mastodon user configures for their
  own view is not readable via the public API — all site visitors see the same safe default.

## Internationalization

`languages/de.yaml` + `languages/en.yaml`, prefix `PLUGIN_SOCIAL_LINKING.*` (33 keys, identical
in both files). Templates use `|t` (plain strings) resp. `|tl` with array syntax
`{{ ['KEY', param1, param2]|tl }}` for `%s` placeholders — deliberately **not** `|t(param1,
param2)`, since that has a documented bug on older Grav versions.
`EmbedRenderer::renderError()` translates server-side via `$grav['language']->translate([key,
...params])`. The Admin form labels/help texts in `blueprints.yaml` are translated too.

Deliberately **not** translated: internal exception messages in `MastodonProvider` (e.g. for an
unparseable URL). The class is deliberately kept independent of a running Grav instance so it can
be unit-tested without a Grav bootstrap — coupling it to `$grav['language']` would have prevented
that. Affects only edge-case errors, not normal operation.

## Notable past bugs (useful context before touching related code)

1. **`blueprints.yaml` alone isn't enough.** Despite official docs suggesting otherwise, the
   plugin disappears entirely from the Admin plugin list without an additional
   `social-linking.yaml` (default configuration values, following the `<slug>.yaml` pattern) —
   reproducibly confirmed on **Grav 1.7** (classic Admin) **and Grav 2.0** (Admin Next). Both
   files have been a fixed part of the plugin since, `social-linking.yaml` structured after the
   pattern of `feedteasers.yaml` (a plain values file with comments, no form structure).
2. **Image 404s from route-based instead of file-based paths.** Locally cached media were
   initially referenced via the page's "clean" route (`/page-route/_social-linking/...`) — Grav
   doesn't generically resolve image URLs for arbitrarily nested subfolders through its routing,
   resulting in a 404 via the `PagesProcessor` (especially visible on the homepage, where the
   route is empty). Fix: media URLs are now built from the physical path relative to `GRAV_ROOT`
   (`EmbedRenderer::resolveStaticWebBase()`) — matches the real file exactly, served directly and
   statically by the webserver, no Grav routing involved.
3. **`acct` without a domain for accounts on the queried instance itself.** The Mastodon API only
   returns `acct` with a domain suffix for federated/remote accounts; accounts on the queried
   instance itself return just the bare username. Result before the fix: `@christiansagt` instead
   of `@christiansagt@norden.social`. Fixed in `MastodonProvider::normalizeAccount()`: the domain
   is appended when `acct` doesn't already contain one.
4. **v0.5.0 regression (a): i18n only half-shipped.** `languages/*.yaml` and the translated Admin
   form were in the release, but the actual `|t`/`|tl` wiring in the three templates and in
   `EmbedRenderer` was missing — the old, untranslated v0.4.0 version of the affected files went
   out instead (including a `MediaCache.php` that had regressed to an even older state). Likely
   cause: a merge/copy step that only picked up part of the patch files.
5. **v0.5.0 regression (b): nested directory duplication.** A byte-identical copy of large parts
   of the plugin was sitting under `classes/` (`classes/classes/`, `classes/cli/`, `classes/css/`,
   `classes/templates/`, even `classes/social-linking.php` and `classes/social-linking.yaml`).
   Functionally harmless (never loaded by Grav), but unnecessary bulk in the public release. Both
   regressions (4+5) were fixed in hotfix release **v0.5.1**.

## Live status (at time of writing)

Version 0.5.1 is live on the official Grav GPM. See `CHANGELOG.md` for the current released
version and `README.md` for user-facing configuration docs. This file describes architecture and
rationale, not release status — please keep it in sync when the design changes, but don't
duplicate version numbers here.

---

## Auf Deutsch (Kurzfassung)

Diese Datei richtet sich an Contributor, die am Code arbeiten wollen (Endnutzer-Doku steht in
`README.md`). Kernpunkte: Daten werden einmalig gelesen und **datei-basiert je Seitenordner**
zwischengespeichert, nie live bei jedem Aufruf; die Aufrufkonvention ist von Anfang an über
einzelne Beiträge hinaus gedacht (Profile, Feeds); Datenschutz geht vor Funktionsumfang — es gibt
bewusst keinen `type` für die Beitragshistorie eines einzelnen Kontos, um den
Follower-only-Schutz geschützter Konten nicht auszuhebeln (siehe „Deliberate non-implementation"
oben); sensible Inhalte (Content Warnings, `sensitive: true`) werden standardmäßig sicher
dargestellt.

Zwei Einbindungswege (`[social-embed]`-Shortcode über die Shortcode-Core-Abhängigkeit, sowie
`{{ social_embed({...}) }}` als Twig-Funktion) laufen intern durch dieselbe
`EmbedRenderer::render()`-Methode.

Medien-URLs referenzieren bewusst den physischen Pfad relativ zu `GRAV_ROOT`, nicht die „saubere"
Seiten-Route (siehe Altbug Nr. 2 oben — sonst 404 über Grav-Routing bei tief verschachtelten
Unterordnern). `acct` ohne Domain-Suffix bei Konten der eigenen Instanz wird in
`MastodonProvider::normalizeAccount()` korrigiert (Altbug Nr. 3).

Provider-Architektur (`ProviderInterface`) ist vorbereitet für weitere Dienste über Mastodon
hinaus, aktuell ist nur `MastodonProvider` implementiert. Die beiden v0.5.0-Regressionen
(unvollständig ausgeliefertes i18n, verschachtelte Verzeichnis-Dopplung unter `classes/`) sind mit
Hotfix v0.5.1 behoben — Details siehe Abschnitt „Notable past bugs" oben bzw. `CHANGELOG.md`.
