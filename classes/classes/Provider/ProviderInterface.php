<?php

namespace Grav\Plugin\SocialLinking\Provider;

/**
 * Ein Provider kapselt den Zugriff auf die API eines Dienstes und liefert
 * normalisierte Daten zurück (siehe README.md, Abschnitt "Datenschema").
 *
 * Um einen weiteren Dienst zu unterstützen, reicht es, diese Schnittstelle
 * zu implementieren und den Provider in social-linking.php zu registrieren -
 * die Speicherung, Medien-Zwischenspeicherung und das Rendering müssen dafür
 * nicht angefasst werden. Für die Twig-Darstellung wird dann zusätzlich ein
 * Template unter templates/partials/social-linking/<service>-<type>.html.twig
 * erwartet.
 */
interface ProviderInterface
{
    /**
     * Kurzer, eindeutiger Schlüssel des Dienstes, z. B. "mastodon".
     * Wird als "service"-Parameter im Aufruf sowie im Datei- und
     * Template-Namen verwendet.
     */
    public function getKey(): string;

    /**
     * Prüft, ob eine gegebene URL bzw. ein Handle von diesem Provider
     * verarbeitet werden kann.
     */
    public function supports(string $reference): bool;

    /**
     * Lädt einen einzelnen Beitrag und liefert ein normalisiertes Array
     * zurück (type: "status").
     */
    public function fetchStatus(string $url, array $options = []): array;

    /**
     * Lädt ein Nutzerprofil (type: "profile").
     */
    public function fetchAccount(string $handleOrUrl, array $options = []): array;

    /**
     * Lädt den instanzweiten, öffentlichen Live-Feed (alle/lokale/föderierte
     * Beiträge, unabhängig von einem einzelnen Konto; type: "timeline").
     * Entspricht den "Dieser Server"/"Externe Server"/"Alle Server"-Reitern
     * der Mastodon-Weboberfläche.
     *
     * Es gibt bewusst KEINE Methode für die Beitragshistorie eines EINZELNEN
     * Kontos: Ein Konto kann geschützt sein (Beiträge nur für Follower
     * sichtbar), und eine serverseitig abgerufene, öffentlich eingebettete
     * Liste würde diesen Schutz faktisch aushebeln.
     */
    public function fetchPublicTimeline(string $url, array $options = []): array;
}
