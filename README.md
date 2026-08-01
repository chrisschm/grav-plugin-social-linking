# Social Linking (Grav-Plugin)

Bindet Beiträge von Mastodon (und jedem Dienst mit Mastodon-kompatibler
Client-API, z. B. Pleroma, Akkoma, GoToSocial) direkt in Grav-Seiten ein.
Die nötigen Daten werden **einmalig über die API gelesen und danach als
Datei im Ordner der jeweiligen Seite zwischengespeichert** - die Darstellung
erfolgt anschließend ausschließlich aus diesem lokalen Cache, nicht bei
jedem Seitenaufruf live von der API.

## Installation

1. Ordner `social-linking/` nach `user/plugins/social-linking/` kopieren.
2. Das Plugin **Shortcode Core** installieren (Abhängigkeit):
   `bin/gpm install shortcode-core`
3. Plugin aktivieren: `bin/gpm install social-linking` (falls per GPM) oder
   direkt im Admin-Panel unter *Plugins*.

> **Wichtig:** Neben `blueprints.yaml` (Formular-/Metadaten-Struktur) muss im
> Plugin-Root zusätzlich eine `social-linking.yaml` mit den Default-Werten
> liegen (siehe Vorlage weiter unten). Ohne diese Datei taucht das Plugin in
> der Admin-Oberfläche **nicht** in der Plugin-Liste auf - reproduzierbar
> bestätigt sowohl unter Grav 1.7 (klassisches Admin) als auch unter Grav 2.0
> (Admin-Next). Das widerspricht der offiziellen Doku, die nur `blueprints.yaml`
> als notwendig beschreibt - vermutlich ein bislang nicht dokumentiertes
> Verhalten oder ein eigener Bug in den getesteten Versionen. Falls sich das
> in einer späteren Grav-Version ändert, bitte hier vermerken.

## Aufrufkonvention

Der zentrale Baustein ist der Shortcode `[social-embed ...]`, nutzbar in
jedem Markdown-Seiteninhalt:

```
[social-embed url="https://norden.social/@christiansagt/113456789012345678"]
```

Das genügt für den Regelfall (einzelner Beitrag). Vollständige Parameterliste:

| Parameter | Pflicht | Default     | Bedeutung |
|-----------|---------|-------------|-----------|
| `url`     | ja      | –           | Permalink des Beitrags/Profils/Feeds **oder** ein Handle (`user@instanz.tld`) |
| `service` | nein    | `mastodon`  | Welcher Dienst-Provider genutzt wird |
| `type`    | nein    | `status`    | `status` (Einzelbeitrag) · `profile` (Profilkarte) · `timeline` (instanzweiter Live-Feed, siehe unten) |
| `limit`   | nein    | `10`        | Nur bei `type="timeline"`: Anzahl Beiträge |
| `refresh` | nein    | `false`     | `true` lädt den Beitrag **neu** von der API und überschreibt den Cache – so wird ein „veränderter“ Beitrag übernommen |
| `delete`  | nein    | `false`     | `true` **löscht** den lokalen Cache (JSON + Medien) für genau diesen Aufruf wieder; es wird nichts ausgegeben |

**`type="timeline"`** zeigt den instanzweiten öffentlichen Live-Feed
(`url` ist die Feed-URL selbst - genau das, was in der Adresszeile steht,
wenn man in der Mastodon-Weboberfläche auf „Dieser Server“/„Externe
Server“/„Alle Server“ klickt):

```
[social-embed type="timeline" url="https://norden.social/public/local"]   {# Dieser Server #}
[social-embed type="timeline" url="https://norden.social/public/remote"]  {# Externe Server #}
[social-embed type="timeline" url="https://norden.social/public"]         {# Alle Server #}
```

Der `remote`-Parameter für ausschließlich föderierte Beiträge wird nicht von
jeder Instanz-Version unterstützt; ältere Server liefern dann ggf. den
vollständigen (kombinierten) Feed statt einer echten Remote-only-Filterung.

**Bewusst NICHT unterstützt: die Beitragshistorie eines einzelnen Kontos.**
Grund: Mastodon-Konten können als "geschützt" markiert sein, sodass Beiträge
nur für bestätigte Follower sichtbar sind. Eine serverseitig abgerufene und
öffentlich eingebettete Liste würde diesen Schutz faktisch aushebeln -
unabhängig davon, ob die API im Einzelfall überhaupt Daten liefern würde.
Das oben beschriebene `type="timeline"` ist davon nicht betroffen, da dort
per Definition nur ohnehin öffentlich sichtbare Beiträge auftauchen (der
instanzweite Feed, keine Konto-spezifische Liste).

Beispiele:

```
{# Beitrag neu laden, z. B. weil er auf Mastodon bearbeitet wurde #}
[social-embed url="https://norden.social/@christiansagt/113456789012345678" refresh="true"]

{# zwischengespeicherten Beitrag wieder entfernen #}
[social-embed url="https://norden.social/@christiansagt/113456789012345678" delete="true"]
```

Dieselbe Aufrufkonvention steht auch als Twig-Funktion zur Verfügung, für
Theme-Entwickler, die Embeds direkt in `.html.twig`-Dateien statt im
Markdown einsetzen möchten:

```twig
{{ social_embed({url: 'https://norden.social/@christiansagt/113456789012345678'}) }}
```

### Bearbeiten und Löschen

- **Bearbeiten:** Beitrag auf Mastodon ändern, danach den Shortcode einmalig
  mit `refresh="true"` aufrufen (oder den passenden CLI-Befehl nutzen, siehe
  unten) - der Cache wird dann beim nächsten Aufruf neu befüllt.
- **Löschen:** `delete="true"` im Shortcode-Aufruf entfernt den zugehörigen
  Cache-Ordner inkl. heruntergeladener Medien vollständig.

### CLI (Wartung mehrerer/aller Seiten)

Für Bulk-Wartung (z. B. per Scheduler/Cronjob) gibt es zusätzlich:

```
bin/plugin social-linking refresh [seitenordner]   # Cache löschen, Medien bleiben
bin/plugin social-linking purge   [seitenordner]   # Cache + Medien vollständig löschen
```

`seitenordner` ist optional und relativ zu `user/pages` anzugeben, z. B.
`03.blog/05.mein-beitrag`. Ohne Angabe wirkt der Befehl auf alle Seiten.

## Speicherformat

Pro Aufruf wird innerhalb des Ordners der Seite, in der der Shortcode
verwendet wird, ein Unterordner angelegt (Name konfigurierbar, Standard
`_social-linking`):

```
user/pages/03.blog/05.mein-beitrag/
├── mein-beitrag.md
└── _social-linking/
    ├── mastodon__status__29961ee38a4860c66b51.json
    └── mastodon__status__29961ee38a4860c66b51/
        └── media/
            ├── 4f9a1c2b8e7d0a11.jpg   (Avatar)
            └── 8b2e9f1a0c3d5e77.jpg   (Card-Bild etc.)
```

Der Dateiname wird deterministisch aus Dienst, Typ und Beitrags-URL
gebildet - ein erneuter, identischer Aufruf trifft also immer denselben
Cache-Eintrag. Die JSON-Datei enthält ein dienstunabhängiges, normalisiertes
Schema (angelehnt an die Mastodon-API, siehe `MastodonProvider::normalizeStatus()`),
u. a.:

```json
{
  "type": "status",
  "service": "mastodon",
  "instance": "norden.social",
  "url": "https://norden.social/@christiansagt/113...",
  "created_at": "2026-07-20T20:16:00.000Z",
  "content_html": "<p>...</p>",
  "account": { "acct": "christiansagt@norden.social", "avatar": "/blog/mein-beitrag/_social-linking/.../avatar.jpg", "..." : "..." },
  "media_attachments": [ ],
  "card": { "url": "...", "title": "...", "image": "..." },
  "stats": { "replies_count": 0, "reblogs_count": 0, "favourites_count": 1 },
  "reblog": null
}
```

Bilder (Avatar, Anhänge, Link-Vorschaubild) werden beim ersten Laden einmalig
heruntergeladen und im selben Ordner abgelegt; im JSON stehen dann bereits
die lokalen Pfade. Schlägt ein einzelner Bild-Download fehl, wird als
Fallback die ursprüngliche Remote-URL verwendet, damit die Anzeige nicht
komplett scheitert.

**Technischer Hinweis:** Die lokalen Bild-URLs zeigen bewusst auf den
*physischen* Pfad relativ zum Grav-Wurzelverzeichnis (z. B.
`/user/pages/03.blog/05.mein-beitrag/_social-linking/.../datei.jpg`) statt
auf die "saubere" Seiten-Route. Grund: Grav löst URLs für beliebig tief
verschachtelte Unterordner nicht generisch über sein Seiten-Routing auf -
nur der reale physische Pfad wird vom Webserver per Standardregel direkt
statisch ausgeliefert, ohne Umweg über `index.php`.

**Hinweis zur Sichtbarkeit:** Die abgelegten Dateien liegen im normal
erreichbaren Seitenordner und sind über die URL grundsätzlich abrufbar -
wie auch reguläre Bilder, die man einer Grav-Seite hinzufügt. Für öffentlich
sichtbare Beiträge ist das unproblematisch; sollen ausschließlich
nicht-öffentliche Beiträge eingebunden werden, empfiehlt sich zusätzlicher
Zugriffsschutz auf Server-Ebene.

## Content Warnings & sensible Medien

Mastodon-Beiträge können zwei unabhängige Warnhinweise tragen:

- **Content Warning** (`spoiler_text`): eine Kurzbeschreibung, hinter der der
  eigentliche Beitragstext standardmäßig verborgen ist. Wir bilden das über
  ein natives `<details>`/`<summary>`-Element ab - der Beitragstext ist per
  Klick aufklappbar, ganz ohne JavaScript.
- **Sensible Medien** (`sensitive: true`): gilt für alle Anhänge eines
  Beitrags gemeinsam (Mastodon kennt keine pro-Bild-Markierung). Diese werden
  standardmäßig weichgezeichnet dargestellt, mit einem Overlay zum Aufdecken
  per Klick - ebenfalls rein CSS-basiert (Checkbox-Hack), kein JavaScript.

Beide Zustände sind rein clientseitig und starten für jeden Besucher
zurückgesetzt (kein Merken über Seitenaufrufe hinweg). Eine eventuelle
"immer automatisch aufklappen"-Einstellung, die ein Mastodon-Nutzer für die
eigene Ansicht in seinem Account konfiguriert hat, ist über die öffentliche
API nicht auslesbar und wird hier folglich nicht berücksichtigt - alle
Besucher der Website sehen den gleichen, sicheren Default (eingeklappt/
weichgezeichnet).

## Theme-Integration

Die Darstellung orientiert sich optisch an Mastodon (Avatar, Name/Handle,
Inhalt, Medien, Link-Vorschau, Fußzeile mit Zähler und Permalink; boostete
Beiträge zeigen zusätzlich eine „X teilte“-Kopfzeile). Farben, Schrift und
Rundungen werden dabei nicht hart codiert, sondern über CSS-Variablen
bezogen, mit sinnvollen Fallback-Werten:

```css
.se-mastodon {
  --se-bg:     var(--color-surface, var(--background-color, #ffffff));
  --se-text:   var(--color-text, var(--text-color, #1e2029));
  --se-muted:  var(--color-text-muted, #6b7280);
  --se-border: var(--color-border, #e2e2e6);
  --se-accent: var(--color-primary, var(--color-link, #6364ff));
  --se-radius: var(--border-radius, 12px);
  --se-font:   var(--font-family, inherit);
}
```

Definiert das aktive Theme bereits Variablen mit diesen (oder ähnlichen,
gängigen) Namen, übernimmt die Karte automatisch das Theme-Aussehen. Sonst
lassen sich die Werte gezielt im eigenen Theme-CSS überschreiben, z. B.:

```css
.se-mastodon { --se-accent: #ff6a00; --se-radius: 4px; }
```

## Bekannte Einschränkungen / ToDo

- **Keine Internationalisierung:** Alle im Plugin sichtbaren Texte (Twig-
  Templates wie „Follower“, „Dabei seit“, „Dieser Server“, „Auf {instanz}
  ansehen“, „Medien anzeigen“, sowie PHP-seitige Fehlermeldungen) sind aktuell
  hart als Deutsch verdrahtet - es gibt noch keine `languages/de.yaml` /
  `languages/en.yaml` mit Grav-typischen Übersetzungs-Keys. Für den
  produktiven Einsatz auf mehrsprachigen oder englischsprachigen Grav-Sites
  wäre das nachzuziehen.
- **Keine Pagination:** Bei `type="timeline"` (instanzweiter Live-Feed) wird
  immer nur der neueste Ausschnitt (`limit`, Default 10) geladen, ein
  "weitere laden"-Mechanismus fehlt noch.
- **Content-Warning-Präferenz nicht auslesbar:** Eine "automatisch
  aufklappen"-Einstellung, die ein Mastodon-Nutzer für die eigene Ansicht
  konfiguriert hat, ist über die öffentliche API nicht ermittelbar (siehe
  Abschnitt "Content Warnings & sensible Medien" oben) - alle Website-
  Besucher sehen denselben sicheren Default.

## Erweiterbarkeit

Die Aufrufkonvention ist bewusst so angelegt, dass sie über den aktuellen
Funktionsumfang hinaus trägt:

- **`type="profile"`** ist für den produktiven Einsatz ausgebaut
  (Follower/Folge-ich/Beiträge, Bio, Dabei-seit, benutzerdefinierte Profilfelder
  mit Verifizierungs-Häkchen).
- **Weitere Dienste**: Ein neuer Dienst wird unterstützt, indem
  `Grav\Plugin\SocialLinking\Provider\ProviderInterface` implementiert und in
  `social-linking.php`/`shortcodes/SocialLinkShortcode.php` registriert
  wird. Speicherung, Medien-Zwischenspeicherung und der Shortcode-Parameter
  `service="..."` funktionieren dann automatisch mit.

## Konfiguration

Über *Admin → Plugins → Social Linking* bzw. `user/config/plugins/social-linking.yaml`:

```yaml
enabled: true
storage_subfolder: _social-linking   # Name des Cache-Unterordners je Seite
timeout: 10                         # API-Timeout in Sekunden
tokens:                             # optional, für nicht-öffentliche Inhalte
  norden.social: 'dein-access-token'
```

## Voraussetzungen

- Grav 1.7 oder 2.0
- **PHP ≥ 8.3** – der Code nutzt durchgehend PHP-8-Syntax (Constructor Property
  Promotion, `match`-Ausdrücke, `str_starts_with()`). Grav 1.7 selbst käme
  offiziell mit PHP ≥ 7.3.6 (empfohlen 7.4) aus, das Plugin benötigt jedoch
  in jedem Fall PHP 8.3 oder neuer, unabhängig von der Grav-Version.
  Getestet mit PHP 8.3 (Grav 1.7) und PHP 8.5 (Grav 2.0).
- Plugin **Shortcode Core** (`shortcode-core`)
- PHP mit `curl`-Extension (empfohlen) oder aktivierten `allow_url_fopen`-Streams als Fallback
