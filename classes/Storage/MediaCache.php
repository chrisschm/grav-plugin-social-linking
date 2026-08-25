<?php

namespace Grav\Plugin\SocialLinking\Storage;

use Grav\Plugin\SocialLinking\Http\SimpleHttpClient;

/**
 * Lädt von Providern gelieferte Remote-Medien (Avatare, Kopfbilder,
 * Beitragsanhänge, Link-Vorschaubilder) einmalig lokal in den Seitenordner
 * herunter und schreibt im normalisierten Datensatz an deren Stelle einen
 * *relativen* Verweis (z. B. "mastodon__status__ab12.../media/e5a8....jpg").
 * Schlägt ein einzelner Download fehl, bleibt die ursprüngliche Remote-URL
 * als Fallback erhalten, damit der Beitrag trotzdem dargestellt werden kann.
 *
 * Bewusst KEIN absoluter/web-erreichbarer Pfad wird persistiert: Der
 * Seitenordner (inkl. seines numerischen Sortier-Präfix, z. B.
 * "03.mein-beitrag") kann sich nach dem ersten Abruf jederzeit ändern -
 * etwa durch eine Neusortierung der Unterseiten im Admin. Ein zu diesem
 * Zeitpunkt fest im JSON gespeicherter Pfad würde dadurch ungültig, obwohl
 * die Mediendatei selbst beim Verschieben/Umbenennen des Seitenordners
 * mitwandert. Der relative Verweis wird stattdessen bei jedem Rendern über
 * resolve() gegen den *aktuellen* Seitenordner-Pfad aufgelöst, siehe
 * EmbedStorage::publicPathFor().
 */
class MediaCache
{
    public static function localize(array $data, EmbedStorage $storage, string $key, SimpleHttpClient $client): array
    {
        return match (true) {
            isset($data['statuses']) && is_array($data['statuses']) => self::localizeTimeline($data, $storage, $key, $client),
            isset($data['content_html']) || array_key_exists('media_attachments', $data) => self::localizeStatus($data, $storage, $key, $client),
            isset($data['username']) || isset($data['acct']) => self::localizeAccount($data, $storage, $key, $client),
            default => $data,
        };
    }

    private static function localizeTimeline(array $data, EmbedStorage $storage, string $key, SimpleHttpClient $client): array
    {
        if (!empty($data['account'])) {
            $data['account'] = self::localizeAccount($data['account'], $storage, $key . '_account', $client);
        }

        foreach ($data['statuses'] as $i => $status) {
            $data['statuses'][$i] = self::localizeStatus($status, $storage, $key . '_' . $i, $client);
        }

        return $data;
    }

    private static function localizeStatus(array $status, EmbedStorage $storage, string $key, SimpleHttpClient $client): array
    {
        if (!empty($status['account'])) {
            $status['account'] = self::localizeAccount($status['account'], $storage, $key, $client);
        }

        if (!empty($status['media_attachments']) && is_array($status['media_attachments'])) {
            foreach ($status['media_attachments'] as $i => $att) {
                if (!empty($att['url'])) {
                    $status['media_attachments'][$i]['url'] = self::download($att['url'], $storage, $key, $client);
                }
                if (!empty($att['preview_url'])) {
                    $status['media_attachments'][$i]['preview_url'] = self::download($att['preview_url'], $storage, $key, $client);
                }
            }
        }

        if (!empty($status['card']['image'])) {
            $status['card']['image'] = self::download($status['card']['image'], $storage, $key, $client);
        }

        if (!empty($status['reblog']) && is_array($status['reblog'])) {
            $status['reblog'] = self::localizeStatus($status['reblog'], $storage, $key . '_reblog', $client);
        }

        return $status;
    }

    private static function localizeAccount(array $account, EmbedStorage $storage, string $key, SimpleHttpClient $client): array
    {
        if (!empty($account['avatar'])) {
            $account['avatar'] = self::download($account['avatar'], $storage, $key, $client);
        }
        if (!empty($account['header'])) {
            $account['header'] = self::download($account['header'], $storage, $key, $client);
        }
        return $account;
    }

    private static function download(string $remoteUrl, EmbedStorage $storage, string $key, SimpleHttpClient $client): string
    {
        $mediaDir = $storage->mediaDir($key);
        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0755, true);
        }

        $ext = pathinfo(parse_url($remoteUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION);
        $ext = preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'jpg';
        $filename = substr(sha1($remoteUrl), 0, 16) . '.' . $ext;
        $target = $mediaDir . '/' . $filename;

        if (!is_file($target)) {
            try {
                $client->download($remoteUrl, $target);
            } catch (\Throwable $e) {
                return $remoteUrl; // Original-URL als Fallback, falls Download scheitert
            }
        }

        // Bewusst relativ (kein $storage->mediaPublicPath()) - siehe
        // Klassenkommentar. Wird erst in resolve() beim Rendern in einen
        // aktuellen, web-erreichbaren Pfad umgewandelt.
        return $key . '/media/' . $filename;
    }

    /**
     * Gegenstück zu localize(): läuft denselben normalisierten Datensatz ab
     * und wandelt jeden von download() hinterlegten relativen Medienverweis
     * in einen aktuellen, web-erreichbaren Pfad um. Wird bei JEDEM Rendern
     * aufgerufen (nicht nur beim ersten Abruf), damit eine spätere
     * Umbenennung des Seitenordners (z. B. Neusortierung im Admin) nicht zu
     * toten Bildpfaden führt.
     */
    public static function resolve(array $data, EmbedStorage $storage): array
    {
        return match (true) {
            isset($data['statuses']) && is_array($data['statuses']) => self::resolveTimeline($data, $storage),
            isset($data['content_html']) || array_key_exists('media_attachments', $data) => self::resolveStatus($data, $storage),
            isset($data['username']) || isset($data['acct']) => self::resolveAccount($data, $storage),
            default => $data,
        };
    }

    private static function resolveTimeline(array $data, EmbedStorage $storage): array
    {
        if (!empty($data['account'])) {
            $data['account'] = self::resolveAccount($data['account'], $storage);
        }

        foreach ($data['statuses'] as $i => $status) {
            $data['statuses'][$i] = self::resolveStatus($status, $storage);
        }

        return $data;
    }

    private static function resolveStatus(array $status, EmbedStorage $storage): array
    {
        if (!empty($status['account'])) {
            $status['account'] = self::resolveAccount($status['account'], $storage);
        }

        if (!empty($status['media_attachments']) && is_array($status['media_attachments'])) {
            foreach ($status['media_attachments'] as $i => $att) {
                if (!empty($att['url'])) {
                    $status['media_attachments'][$i]['url'] = self::resolveUrl($att['url'], $storage);
                }
                if (!empty($att['preview_url'])) {
                    $status['media_attachments'][$i]['preview_url'] = self::resolveUrl($att['preview_url'], $storage);
                }
            }
        }

        if (!empty($status['card']['image'])) {
            $status['card']['image'] = self::resolveUrl($status['card']['image'], $storage);
        }

        if (!empty($status['reblog']) && is_array($status['reblog'])) {
            $status['reblog'] = self::resolveStatus($status['reblog'], $storage);
        }

        return $status;
    }

    private static function resolveAccount(array $account, EmbedStorage $storage): array
    {
        if (!empty($account['avatar'])) {
            $account['avatar'] = self::resolveUrl($account['avatar'], $storage);
        }
        if (!empty($account['header'])) {
            $account['header'] = self::resolveUrl($account['header'], $storage);
        }
        return $account;
    }

    private static function resolveUrl(string $value, EmbedStorage $storage): string
    {
        if (preg_match('#^https?://#i', $value)) {
            return $value; // Remote-Fallback (Download war fehlgeschlagen) - unverändert lassen.
        }

        // Sowohl das aktuelle relative Format ("<key>/media/<datei>") als
        // auch der vor diesem Fix fest gespeicherte absolute Pfad
        // ("/user/pages/.../<key>/media/<datei>") enden auf dieselben drei
        // Segmente. Wir extrahieren bewusst nur dieses Suffix und hängen es
        // an den *aktuellen* Web-Pfad an - das repariert nebenbei auch
        // Bestandsdaten, die durch eine frühere Umsortierung bereits
        // ungültig geworden sind, ohne dass ein Migrationsschritt nötig ist.
        $parts = explode('/', trim($value, '/'));
        $filename = array_pop($parts);
        $mediaSegment = array_pop($parts);
        $key = array_pop($parts);

        if ($filename === null || $key === null || $mediaSegment !== 'media') {
            return $value; // Unerwartetes Format - unverändert lassen (sicherer Fallback)
        }

        return $storage->publicPathFor($key . '/media/' . $filename);
    }
}
