# Contributing to social-linking

Thank you for considering a contribution! *(Eine deutsche Kurzfassung findest du am Ende dieser Datei.)*

## This GitHub repository is a read-only mirror

Development happens on **Codeberg**. This GitHub repository is automatically mirrored from there and is
**read-only** — issues and pull requests opened here will not be reviewed and will be closed/redirected.

- Main repository: https://codeberg.org/chschmidt/grav-plugin-social-linking
- Report a bug or request a feature: https://codeberg.org/chschmidt/grav-plugin-social-linking/issues/new/choose
- Submit code changes: open a pull request against Codeberg

## Design goals (please keep these in mind for any change)

- Must stay installable via GPM without any manual steps by the end user. The dependency on
  **Shortcode Core** is declared in `blueprints.yaml` and resolved by GPM automatically.
- No external PHP dependencies (no third-party Composer packages, see `composer.json`). All HTTP access
  goes through `classes/Http/SimpleHttpClient.php`, which relies exclusively on `cURL` with a
  stream-context fallback.
- There is intentionally **no** feature to fetch the post history/timeline of a single account. Only the
  instance-wide *public* timeline (`type="timeline"`) is supported — see the docblock in
  `classes/Provider/ProviderInterface.php`. A single-account history could be used to work around
  protected/followers-only accounts, so this is a deliberate limitation, not an oversight. Please don't
  submit PRs that add this without discussing it in an issue first.
- Embeds are fetched once and cached locally in the page's own folder (JSON + media); pages must not hit
  the provider's API on every request. Any change to fetching/rendering must keep working through this
  cache, including the `refresh`/`delete` parameters and the `bin/plugin social-linking refresh|purge`
  CLI commands.
- Must remain usable via the `[social-embed]` shortcode without requiring Twig knowledge from site owners;
  the `social_embed()` Twig function is an additional option for theme developers, not a replacement.

If a change would require adding a Composer dependency, touch the caching behaviour, or add an
account-history feature, please open an issue first to discuss it before investing time in a PR.

## Adding support for another service (provider)

The provider architecture is designed for this: implement `classes/Provider/ProviderInterface.php`,
register the new provider in `social-linking.php`, and add matching templates under
`templates/partials/social-linking/<service>-<type>.html.twig`. Storage, media caching, and rendering
don't need to be touched. See the docblock in `ProviderInterface.php` for details.

## Before opening a pull request

1. **Target branch:** please branch from and target `main`.
2. **PHP version:** the plugin supports PHP >= 8.0 (see `composer.json`; README documents PHP 8.3/8.5 as
   the tested/supported floor). Please avoid syntax or functions that require a newer PHP version unless
   you also raise the requirement in `composer.json` and README *and* discuss it in an issue first — this
   affects every user on shared/older hosting.
3. **Syntax check:** there is currently no automated lint/test step in CI for pull requests. Please run a
   PHP syntax check yourself on any changed PHP file before submitting:
   ```bash
   php -l path/to/changed-file.php
   ```
4. **Manual testing:** there is no automated test suite yet. Please briefly describe in the PR description
   how you tested your change — Grav version, which `type` (`status` / `profile` / `timeline`) and which
   provider/service, and whether you tested both the Twig function and the `[social-embed]` shortcode if
   relevant.
5. Keep changes focused — smaller, single-purpose PRs are much easier to review than large ones.

## Configuration & code overview

- `social-linking.php` — plugin events, manual `spl_autoload_register()` (no Composer autoloading), Twig
  function/shortcode wiring, asset registration
- `classes/Http/SimpleHttpClient.php` — dependency-free HTTP client (`cURL`/stream fallback)
- `classes/Provider/` — `ProviderInterface`, `ProviderRegistry`, `MastodonProvider`; implement the
  interface to add a new service
- `classes/Shortcode/EmbedRenderer.php` — shared rendering/caching logic used by both the shortcode and
  the Twig function
- `classes/Storage/` — `EmbedStorage` (JSON cache per page) and `MediaCache` (locally cached media files)
- `shortcodes/SocialLinkShortcode.php` — registers the `[social-embed]` shortcode
- `cli/RefreshCommand.php`, `cli/PurgeCommand.php` — `bin/plugin social-linking refresh|purge`
- `blueprints.yaml` — Admin panel form (labels/help/titles are translatable via `PLUGIN_SOCIAL_LINKING.*`
  keys in `languages/*.yaml`)
- `templates/partials/social-linking/<service>-<type>.html.twig` — output templates per provider and type

See the plugin's own README for the full list of configuration options and the data schema returned by
providers.

## Release process (for context, maintainer-only)

Releases are tagged on Codeberg; the GitHub mirror publishes a matching GitHub Release automatically via
`.github/workflows/release-from-tag.yml`. You don't need to do anything here as a contributor — just
mention in your PR if you think a change warrants a version bump.

## License

This project is licensed under the MIT License. By submitting a pull request, you agree that your
contribution is provided under the same license.

---

## Auf Deutsch (Kurzfassung)

**Dieses GitHub-Repository ist nur ein Lese-Mirror.** Die eigentliche Entwicklung findet auf
[Codeberg](https://codeberg.org/chschmidt/grav-plugin-social-linking) statt. Bitte Bugs/Feature-Wünsche und
Pull Requests dort einreichen.

**Design-Ziele:** GPM-fähig ohne manuellen Eingriff (Abhängigkeit „Shortcode Core" wird über
`blueprints.yaml` aufgelöst), keine externen Composer-Abhängigkeiten (nur `cURL`/Stream-Fallback in
`SimpleHttpClient`), bewusst **kein** Feature für die Beitragshistorie eines einzelnen Kontos (nur der
öffentliche, instanzweite Live-Feed wird unterstützt – geschützte Konten sollen sich nicht darüber
aushebeln lassen), Embeds werden lokal je Seite zwischengespeichert statt live bei jedem Aufruf
nachgeladen, nutzbar per `[social-embed]`-Shortcode auch ohne Twig-Kenntnisse. Bei größeren Änderungen,
die daran rütteln würden, bitte vorher ein Issue eröffnen.

**Neuen Dienst/Provider hinzufügen:** `ProviderInterface` implementieren, in `social-linking.php`
registrieren, passende Templates unter `templates/partials/social-linking/<service>-<type>.html.twig`
ergänzen — Speicherung, Medien-Cache und Rendering müssen dafür nicht angefasst werden.

**Vor einem Pull Request:**
- Ziel-Branch ist immer `main`.
- Unterstützt wird PHP >= 8.0 (siehe `composer.json`; README nennt PHP 8.3/8.5 als getestete/
  unterstützte Untergrenze). Neuere PHP-Syntax bitte nur nach Rücksprache in einem Issue verwenden.
- Es gibt aktuell **keinen automatisierten Lint/Test-Schritt** in der CI. Bitte selbst `php -l` auf
  geänderten PHP-Dateien laufen lassen.
- Kurz in der PR-Beschreibung angeben, wie manuell getestet wurde (Grav-Version, `type`, Provider,
  Twig-Funktion und/oder Shortcode).
- Lieber kleinere, fokussierte PRs als große Sammel-Änderungen.

**Lizenz:** MIT. Mit einem Pull Request stimmst du zu, dass dein Beitrag unter derselben Lizenz steht.
