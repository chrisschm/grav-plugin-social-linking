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
     * Vorbereitet für eine spätere Ausbaustufe des Plugins.
     */
    public function fetchAccount(string $handleOrUrl, array $options = []): array;

    /**
     * Lädt eine Liste der letzten Beiträge eines Kontos (type: "timeline").
     * Vorbereitet für eine spätere Ausbaustufe des Plugins.
     */
    public function fetchTimeline(string $handleOrUrl, array $options = []): array;
}
