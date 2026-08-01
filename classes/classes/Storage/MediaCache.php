<?php

namespace Grav\Plugin\SocialLinking\Storage;

use Grav\Plugin\SocialLinking\Http\SimpleHttpClient;

/**
 * Lädt von Providern gelieferte Remote-Medien (Avatare, Kopfbilder,
 * Beitragsanhänge, Link-Vorschaubilder) einmalig lokal in den Seitenordner
 * herunter und schreibt die entsprechenden URLs im normalisierten Datensatz
 * auf die lokalen, dauerhaft funktionierenden Pfade um. Schlägt ein
 * einzelner Download fehl, bleibt die ursprüngliche Remote-URL als Fallback
 * erhalten, damit der Beitrag trotzdem dargestellt werden kann.
 */
class MediaCache
{
    public static function localize(array $data, EmbedStorage $storage, string $key): array
    {
        $client = new SimpleHttpClient();

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

        return $storage->mediaPublicPath($key) . '/' . $filename;
    }
}
