<?php

namespace Grav\Plugin\SocialLinking\Storage;

/**
 * Persistiert einen einzelnen Embed als JSON-Datei innerhalb eines
 * Unterordners des jeweiligen Grav-Seitenordners, z. B.:
 *
 *   user/pages/03.blog/05.mein-beitrag/_social-linking/mastodon__status__ab12cd34.json
 *   user/pages/03.blog/05.mein-beitrag/_social-linking/mastodon__status__ab12cd34/media/...
 *
 * Der Unterordner beginnt bewusst NICHT mit einem Punkt (Grav/Webserver
 * blockieren versteckte Dateien häufig standardmäßig) und NICHT mit einer
 * Zahl+Punkt-Konvention (würde von Grav als eigene Unterseite interpretiert).
 * Er wird von Page::media() nicht mit eingelesen, da es sich um eine
 * Unterebene handelt.
 *
 * WICHTIG zur URL-Bildung: Medien werden bewusst NICHT über die "saubere"
 * Seiten-Route (Page::url()) referenziert, sondern über den tatsächlichen
 * physischen Pfad relativ zum Grav-Wurzelverzeichnis (z. B.
 * "/user/pages/03.blog/05.mein-beitrag/..."). Grav löst Bild-URLs für
 * beliebig tief verschachtelte Unterordner nicht über sein Routing auf -
 * nur der reale Pfad wird von Apache/Nginx per Standard-Regel ("Datei
 * existiert? -> direkt ausliefern") ohne Umweg über index.php bedient.
 */
class EmbedStorage
{
    private string $baseDir;
    private string $webBase;

    /**
     * @param string $pageFolder    Absoluter Dateisystempfad zum Seitenordner (Page::path())
     * @param string $webBasePath   Web-erreichbarer Pfad, der physisch exakt auf $pageFolder zeigt
     *                              (siehe EmbedRenderer::resolveStaticWebBase()), z. B.
     *                              "/user/pages/03.blog/05.mein-beitrag"
     * @param string $subfolder     Name des Unterordners für die Zwischenspeicherung
     */
    public function __construct(string $pageFolder, string $webBasePath, string $subfolder = '_social-linking')
    {
        $this->baseDir = rtrim($pageFolder, '/') . '/' . trim($subfolder, '/');
        $this->webBase = rtrim($webBasePath, '/') . '/' . trim($subfolder, '/');
    }

    public function exists(string $key): bool
    {
        return is_file($this->jsonPath($key));
    }

    public function load(string $key): ?array
    {
        $file = $this->jsonPath($key);
        if (!is_file($file)) {
            return null;
        }

        $json = file_get_contents($file);
        $data = $json !== false ? json_decode($json, true) : null;

        return is_array($data) ? $data : null;
    }

    public function save(string $key, array $data): void
    {
        $file = $this->jsonPath($key);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data['fetched_at'] = $data['fetched_at'] ?? gmdate('c');
        $data['updated_at'] = gmdate('c');

        file_put_contents(
            $file,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function delete(string $key): bool
    {
        $ok = true;

        $mediaDir = $this->mediaDir($key);
        if (is_dir($mediaDir)) {
            $ok = $this->rrmdir($mediaDir) && $ok;
        }

        $json = $this->jsonPath($key);
        if (is_file($json)) {
            $ok = @unlink($json) && $ok;
        }

        return $ok;
    }

    /** Absoluter Dateisystempfad zum Medienordner eines Embeds. */
    public function mediaDir(string $key): string
    {
        return $this->baseDir . '/' . $key . '/media';
    }

    /**
     * Relativer, web-erreichbarer Pfad zum Medienordner eines Embeds -
     * ausgehend vom Seitenordner. Wird direkt als Bild-URL im Twig-Template
     * verwendet, da Grav Dateien innerhalb von Seitenordnern statisch
     * ausliefert.
     */
    public function mediaPublicPath(string $key): string
    {
        return $this->webBase . '/' . $key . '/media';
    }

    /**
     * Bildet einen relativen, im gespeicherten Datensatz abgelegten
     * Medienverweis (z. B. "mastodon__profile__50c3.../media/e5a8...jpg",
     * siehe MediaCache::download()) auf den *aktuellen* web-erreichbaren
     * Pfad ab. Bewusst getrennt von mediaPublicPath(): Diese Methode wird
     * bei JEDEM Rendern neu aufgerufen (siehe MediaCache::resolve()),
     * mediaPublicPath() nur beim Download. Dadurch bleiben Bildpfade auch
     * dann korrekt, wenn der Seitenordner nach dem ersten Abruf des Embeds
     * umbenannt wurde (z. B. durch eine Neusortierung im Admin, die den
     * numerischen Präfix des Seitenordners ändert) - ein fest im JSON
     * gespeicherter absoluter Pfad würde das nicht überleben.
     */
    public function publicPathFor(string $relative): string
    {
        return $this->webBase . '/' . ltrim($relative, '/');
    }

    /** @return string[] Liste aller vorhandenen Cache-Keys */
    public function list(): array
    {
        if (!is_dir($this->baseDir)) {
            return [];
        }

        $keys = [];
        foreach (glob($this->baseDir . '/*.json') ?: [] as $file) {
            $keys[] = basename($file, '.json');
        }

        return $keys;
    }

    /**
     * Erzeugt einen dateisystemsicheren, eindeutigen Schlüssel für einen
     * Aufruf. Gleiche Parameter ergeben immer denselben Schlüssel, wodurch
     * ein erneuter Aufruf denselben Cache-Eintrag wiederverwendet.
     */
    public static function keyFor(string $service, string $type, string $identifier): string
    {
        return $service . '__' . $type . '__' . substr(sha1($identifier), 0, 20);
    }

    private function jsonPath(string $key): string
    {
        return $this->baseDir . '/' . $key . '.json';
    }

    private function rrmdir(string $dir): bool
    {
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        return rmdir($dir);
    }
}
