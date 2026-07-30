<?php

namespace Grav\Plugin\SocialLinking\Shortcode;

use Grav\Common\Grav;
use Grav\Plugin\SocialLinking\Provider\ProviderRegistry;
use Grav\Plugin\SocialLinking\Storage\EmbedStorage;
use Grav\Plugin\SocialLinking\Storage\MediaCache;

/**
 * Kernlogik für die Einbindung eines Social-Media-Embeds. Wird sowohl vom
 * [social-embed ...]-Shortcode als auch von der Twig-Funktion social_embed()
 * mit einem einfachen assoziativen Array aus Parametern aufgerufen - das ist
 * die gemeinsame "Aufrufkonvention" des Plugins.
 *
 * Unterstützte Parameter:
 *
 *   service   Kurzname des Dienstes, z. B. "mastodon" (Default: mastodon)
 *   type      "status" (Einzelbeitrag, Default), "profile" (Profilkarte)
 *             oder "timeline" (Beitragsliste) - profile/timeline sind für
 *             eine spätere Ausbaustufe vorbereitet.
 *   url       Permalink des Beitrags/Profils oder ein Handle
 *             (user@instanz.tld). Pflichtfeld.
 *   limit     Nur bei type="timeline": Anzahl Beiträge (Default: 5)
 *   refresh   "true" ignoriert einen vorhandenen Cache-Eintrag und lädt
 *             den Beitrag neu von der API (= "verändern").
 *   delete    "true" entfernt einen vorhandenen Cache-Eintrag inkl. lokaler
 *             Medien wieder (= "löschen"). Danach wird nichts ausgegeben.
 */
class EmbedRenderer
{
    public function __construct(
        private ProviderRegistry $providers,
        private array $config
    ) {
    }

    public function render(array $params): string
    {
        $grav = Grav::instance();
        $page = $grav['page'] ?? null;

        $type    = strtolower((string) ($params['type'] ?? 'status'));
        $service = strtolower((string) ($params['service'] ?? 'mastodon'));
        $url     = trim((string) ($params['url'] ?? ''));
        $refresh = $this->toBool($params['refresh'] ?? false);
        $delete  = $this->toBool($params['delete'] ?? false);
        $limit   = (int) ($params['limit'] ?? 5);

        if ($url === '') {
            return $this->renderError('Es wurde keine URL bzw. kein Handle angegeben (Parameter "url").');
        }
        if (!in_array($type, ['status', 'profile', 'timeline'], true)) {
            return $this->renderError(sprintf('Unbekannter type "%s".', $type));
        }

        $provider = $this->providers->get($service);
        if (!$provider) {
            return $this->renderError(sprintf('Unbekannter Dienst "%s".', $service));
        }
        if (!$provider->supports($url)) {
            return $this->renderError(sprintf('"%s" passt nicht zum Dienst "%s".', $url, $service));
        }
        if (!$page) {
            return $this->renderError('Kein aktiver Seitenkontext gefunden - der Aufruf muss innerhalb einer Grav-Seite erfolgen.');
        }

        $storage = new EmbedStorage(
            $page->path(),
            $this->resolveStaticWebBase($page->path()),
            (string) ($this->config['storage_subfolder'] ?? '_social-linking')
        );
        $key = EmbedStorage::keyFor($service, $type, $url . ($type === 'timeline' ? '|limit=' . $limit : ''));

        if ($delete) {
            $storage->delete($key);
            return '';
        }

        $data = $refresh ? null : $storage->load($key);

        if ($data === null) {
            try {
                $fresh = match ($type) {
                    'status'   => $provider->fetchStatus($url),
                    'profile'  => $provider->fetchAccount($url),
                    'timeline' => $provider->fetchTimeline($url, ['limit' => $limit]),
                };
            } catch (\Throwable $e) {
                $cached = $storage->load($key);
                if ($cached === null) {
                    return $this->renderError('Beitrag konnte nicht geladen werden: ' . $e->getMessage());
                }
                return $this->renderTemplate($type, $cached);
            }

            $data = MediaCache::localize($fresh, $storage, $key);
            $storage->save($key, $data);
        }

        return $this->renderTemplate($type, $data);
    }

    /**
     * Bildet aus dem physischen Seitenordner-Pfad einen web-erreichbaren Pfad,
     * der 1:1 auf dieselbe Datei zeigt - unabhängig von der "sauberen"
     * Seiten-Route. Dadurch werden lokal gecachte Medien direkt statisch vom
     * Webserver ausgeliefert (Standard-Regel "Datei existiert? -> ausliefern,
     * sonst an index.php weiterreichen"), statt über Grav's Seiten-Routing zu
     * laufen - das löst beliebig tief verschachtelte Unterordner nämlich
     * nicht zuverlässig auf und führt sonst zu einem 404 über den
     * PagesProcessor.
     */
    private function resolveStaticWebBase(string $pageFolder): string
    {
        $grav = Grav::instance();

        $root = defined('GRAV_ROOT') ? rtrim(GRAV_ROOT, '/') : '';
        $abs = rtrim($pageFolder, '/');

        $relative = ($root !== '' && str_starts_with($abs, $root))
            ? ltrim(substr($abs, strlen($root)), '/')
            : ltrim($abs, '/');

        $rootUrl = rtrim((string) $grav['uri']->rootUrl(false), '/');

        return $rootUrl . '/' . $relative;
    }

    private function renderTemplate(string $type, array $data): string
    {
        $twig = Grav::instance()['twig']->twig();
        $service = $data['service'] ?? 'mastodon';
        $template = "partials/social-linking/{$service}-{$type}.html.twig";

        return $twig->render($template, ['embed' => $data]);
    }

    private function renderError(string $message): string
    {
        $twig = Grav::instance()['twig']->twig();
        return $twig->render('partials/social-linking/error.html.twig', ['message' => $message]);
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
