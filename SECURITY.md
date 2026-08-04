# Security Policy

*(Eine deutsche Kurzfassung findest du am Ende dieser Datei.)*

## Reporting a vulnerability

Please **do not** report security vulnerabilities through public GitHub or Codeberg issues,
discussions, or pull requests. Codeberg/Forgejo does not currently offer a private vulnerability
reporting feature comparable to GitHub's, so please report privately by email instead:

**security@jcs-net.de**

Please include, as far as you can:

- A description of the vulnerability and its potential impact
- Steps to reproduce (affected plugin version, Grav version, PHP version, `type` used —
  `status`/`profile`/`timeline` — and the provider/service involved)
- Any proof-of-concept code, or an example remote instance/account/post that triggers the issue

You should receive an acknowledgement within a few days. This is a small, solo-maintained
open-source project without a dedicated security team, so please allow reasonable time for a fix
before any public disclosure. I'll coordinate a disclosure timeline with you once the report is
confirmed.

## Supported versions

Only the latest released version of the plugin (as published via GPM) is supported with security
fixes. Please make sure you're on the current version before reporting, and update to the fixed
version as soon as a patch is released.

## Scope

This plugin fetches data and media from Mastodon-compatible instances (Mastodon, Pleroma, Akkoma,
GoToSocial) via their public Client API, caches it locally per page, and renders it through Twig
templates. Reports particularly welcome around:

- **Server-side request forgery (SSRF):** all outgoing HTTP requests — both the `url`/instance
  parameter passed to `[social-embed]` / `social_embed()` *and* media URLs (avatar, attachments,
  card image) returned by the queried instance's API response — are validated against
  `classes/Http/SsrfGuard.php` before every connection, including redirect targets, which are
  followed manually rather than automatically so each hop is re-checked. By default, requests to
  private/loopback/link-local/reserved IP ranges (IPv4 and IPv6) and to `localhost` are rejected;
  site owners can opt a specific internal host in via `allowed_private_hosts` in
  `social-linking.yaml` (off by default, YAML-only, documented as a trust decision). Reports of
  ways to bypass this guard (DNS-rebinding, redirect chains, host-parsing edge cases, IPv6
  literal handling, etc.) are very welcome.
- **Unsafe handling of remote content when rendered in templates** — e.g. XSS via a crafted
  display name, `spoiler_text`, or post content coming back from a (potentially malicious or
  compromised) remote instance and rendered in `templates/partials/social-linking/*.html.twig`.
- **Local file handling of downloaded media** (`classes/Storage/MediaCache.php`,
  `classes/Http/SimpleHttpClient.php::download()`) — e.g. path traversal or overwriting unintended
  files via a crafted remote URL, filename, or `Content-Type`.
- **Handling of the optional per-instance access `tokens`** configured in `social-linking.yaml`
  (Admin panel does not expose this — YAML-only by design) — e.g. accidental exposure in logs,
  error messages, or cached JSON files.
- **Bypassing the sensitive-content/content-warning safeguards** (`spoiler_text` collapsing,
  blurred `sensitive: true` media) described in the plugin's design goals — this is a privacy
  control, not just a display preference, so treat ways to circumvent it as security-relevant.
- **Cache/storage handling** in `classes/Storage/EmbedStorage.php` — e.g. whether the
  `storage_subfolder` name or embed keys can be manipulated to write outside the intended page
  folder.

General Grav core, Shortcode Core, or hosting/infrastructure vulnerabilities are out of scope
here — please report those to the respective project or your hosting provider directly.

---

## Auf Deutsch (Kurzfassung)

**Sicherheitslücken bitte nicht** als öffentliches Issue auf GitHub oder Codeberg melden, sondern
per E-Mail an **security@jcs-net.de**. Bitte möglichst mit Beschreibung, Auswirkung,
Reproduktionsschritten (Plugin-/Grav-/PHP-Version, verwendeter `type`, betroffener Dienst) und
ggf. einem Proof-of-Concept.

Unterstützt wird nur die jeweils aktuelle, über GPM veröffentlichte Version. Da es sich um ein
Solo-Projekt ohne dediziertes Security-Team handelt, bitte etwas Zeit für einen Fix einplanen,
bevor öffentlich darüber gesprochen wird — ich melde mich zeitnah zurück und stimme einen
Offenlegungszeitpunkt mit dir ab.

**Besonders relevant:** Umgehungsversuche der SSRF-Absicherung (`SsrfGuard`, gilt für API-Aufrufe
UND Medien-Downloads), unsichere Ausgabe von Fremdinhalten (XSS über Anzeigename/Spoiler-Text/
Beitragstext), Datei-Handling beim Medien-Download, Umgang mit optionalen Access-Tokens sowie
alles, was den Content-Warning-/Sensible-Medien-Schutz aushebeln könnte.
