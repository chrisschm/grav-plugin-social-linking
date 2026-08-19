# Social Linking Plugin for Grav CMS

[![Latest Release](https://img.shields.io/gitea/v/release/chschmidt/grav-plugin-social-linking?gitea_url=https%3A%2F%2Fcodeberg.org%2F&display_name=release)](https://codeberg.org/chschmidt/grav-plugin-social-linking/releases) 
[![MIT-Lizenz](https://img.shields.io/badge/License-MIT-blue.svg)](https://de.wikipedia.org/wiki/MIT-Lizenz) 
[![Translation status](https://translate.codeberg.org/widget/grav-plugin-social-linking/svg-badge.svg)](https://translate.codeberg.org/engage/grav-plugin-social-linking/)  

**Social Linking** embeds posts, profiles, and instance-wide live feeds from Mastodon — and any
service with a Mastodon-compatible Client API, e.g. Pleroma, Akkoma, GoToSocial — directly into
[Grav CMS](https://getgrav.org) pages. Data is read from the API **once** and then cached locally
as a file in the respective page's own folder; rendering afterwards happens exclusively from that
local cache, never live from the API on every page view.

The plugin depends on the **Shortcode Core** plugin (resolved automatically via GPM) and has no
other external dependencies (no third-party Composer packages).

## Installation

```
bin/gpm install social-linking
```

GPM resolves the dependency on **Shortcode Core** automatically. For manual/zip installation, see
the [Wiki](https://codeberg.org/chschmidt/grav-plugin-social-linking/wiki).

## Quick usage

On a page, in the Markdown editor (no Twig knowledge needed):

```
[social-embed url="https://norden.social/@christiansagt/113456789012345678"]
```

In a theme template, as a Twig function:

```twig
{{ social_embed({url: 'https://norden.social/@christiansagt/113456789012345678'}) }}
```

Both accept optional parameters — `service`, `type` (`status` / `profile` / `timeline`), `limit`,
`refresh`, `delete` — to override the configured defaults for that one call, e.g.
`[social-embed type="timeline" url="https://norden.social/public/local" limit=5]`.

## Documentation

- **For site administrators:** the
  [Wiki](https://codeberg.org/chschmidt/grav-plugin-social-linking/wiki) is the full manual —
  installation, all configuration options, the `status`/`profile`/`timeline` call convention,
  content warnings & sensitive media, theme integration, storage & maintenance (the `refresh`/
  `purge` CLI commands), internationalization, security, and troubleshooting/FAQ.
- **For developers and contributors:** start at [`docs/README.md`](docs/README.md) — architecture,
  design decisions, and how to contribute.

## Links

- Report a bug or request a feature:
  [issue tracker](https://codeberg.org/chschmidt/grav-plugin-social-linking/issues)
- [Security policy](SECURITY.md)
- [Contributing guide](CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](CHANGELOG.md)
- Live demo: [jcs-net.de](https://www.jcs-net.de)

## License

MIT

---

## Auf Deutsch (Kurzfassung)

**Social Linking** bindet Beiträge, Profile und instanzweite Live-Feeds von Mastodon — und jedem
Dienst mit Mastodon-kompatibler Client-API, z. B. Pleroma, Akkoma, GoToSocial — direkt in
Grav-Seiten ein. Die Daten werden **einmalig** über die API gelesen und danach als Datei im Ordner
der jeweiligen Seite zwischengespeichert; die Darstellung erfolgt anschließend ausschließlich aus
diesem lokalen Cache, nicht bei jedem Seitenaufruf live von der API. Das Plugin benötigt das
Plugin **Shortcode Core** als Abhängigkeit (wird per GPM automatisch mitinstalliert) und kommt
ansonsten ohne externe PHP-Abhängigkeiten aus.

**Installation:** `bin/gpm install social-linking` (empfohlen, löst die Shortcode-Core-Abhängigkeit
automatisch auf), alternativ manuell/per Zip — siehe
[Wiki](https://codeberg.org/chschmidt/grav-plugin-social-linking/wiki).

**Verwendung:** `[social-embed url="..."]` im Seiteninhalt (kein Twig-Wissen nötig) oder
`{{ social_embed({url: '...'}) }}` im Template, jeweils mit optionalen Parametern
(`service`, `type`, `limit`, `refresh`, `delete`).

**Dokumentation:** Ein vollständiges Anwender-Handbuch (Installation, alle Konfigurationsoptionen,
Aufrufkonvention, Content Warnings, Theme-Integration, Speicherung & Wartung, Mehrsprachigkeit,
Sicherheit, Fehlerbehebung/FAQ) gibt es im
[Wiki](https://codeberg.org/chschmidt/grav-plugin-social-linking/wiki). Entwickler-/
Contributor-Doku beginnt bei [`docs/README.md`](docs/README.md).

**Weitere Links:**
[Fehler melden](https://codeberg.org/chschmidt/grav-plugin-social-linking/issues),
[Sicherheitsrichtlinie](SECURITY.md), [Mitwirken](CONTRIBUTING.md),
[Verhaltenskodex](CODE_OF_CONDUCT.md), [Changelog](CHANGELOG.md). Demo:
[jcs-net.de](https://www.jcs-net.de).

**Lizenz:** MIT.
