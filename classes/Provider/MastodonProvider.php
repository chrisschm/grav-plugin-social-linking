<?php

namespace Grav\Plugin\SocialLinking\Provider;

use Grav\Plugin\SocialLinking\Http\SimpleHttpClient;

/**
 * Provider für Mastodon und alle Dienste mit Mastodon-kompatibler
 * Client-API (z. B. Pleroma, Akkoma, GoToSocial).
 *
 * API-Referenz: https://docs.joinmastodon.org/client/intro/
 *
 * Unterstützte Referenzen:
 *  - Status-Permalink:  https://instanz.tld/@user/1234567890123456
 *  - Profil-Permalink:  https://instanz.tld/@user
 *  - Handle:            user@instanz.tld  oder  @user@instanz.tld
 */
class MastodonProvider implements ProviderInterface
{
    /**
     * @param array<string,string> $tokens Optionale Access-Token je Instanz,
     *                                      z. B. ['norden.social' => 'xxxxx']
     */
    public function __construct(
        private SimpleHttpClient $http,
        private array $tokens = []
    ) {
    }

    public function getKey(): string
    {
        return 'mastodon';
    }

    public function supports(string $reference): bool
    {
        if (preg_match('#^https?://[^/]+/@[^/]+#', $reference)) {
            return true;
        }
        if (preg_match('#^https?://[^/]+/users/[^/]+#', $reference)) {
            return true;
        }
        if (preg_match('#^@?[a-z0-9_.-]+@[a-z0-9.-]+$#i', $reference)) {
            return true;
        }
        return false;
    }

    public function fetchStatus(string $url, array $options = []): array
    {
        [$instance, $id] = $this->parseStatusUrl($url);
        $endpoint = sprintf('https://%s/api/v1/statuses/%s', $instance, $id);
        $raw = $this->http->getJson($endpoint, $this->headersFor($instance));

        return $this->normalizeStatus($raw, $instance, $url);
    }

    public function fetchAccount(string $handleOrUrl, array $options = []): array
    {
        [$instance, $acct] = $this->parseAccountReference($handleOrUrl);
        $raw = $this->lookupAccount($instance, $acct);

        return array_merge($this->normalizeAccount($raw, $instance), [
            'type'       => 'profile',
            'service'    => $this->getKey(),
            'instance'   => $instance,
            'source_url' => $handleOrUrl,
        ]);
    }

    public function fetchTimeline(string $handleOrUrl, array $options = []): array
    {
        [$instance, $acct] = $this->parseAccountReference($handleOrUrl);
        $accountRaw = $this->lookupAccount($instance, $acct);

        $limit = max(1, min(40, (int) ($options['limit'] ?? 5)));
        $endpoint = sprintf(
            'https://%s/api/v1/accounts/%s/statuses?%s',
            $instance,
            $accountRaw['id'],
            http_build_query(['limit' => $limit, 'exclude_replies' => 'true'])
        );
        $statusesRaw = $this->http->getJson($endpoint, $this->headersFor($instance));

        $statuses = [];
        foreach ($statusesRaw as $status) {
            $statuses[] = $this->normalizeStatus($status, $instance, $status['url'] ?? '');
        }

        return [
            'type'       => 'timeline',
            'service'    => $this->getKey(),
            'instance'   => $instance,
            'source_url' => $handleOrUrl,
            'account'    => $this->normalizeAccount($accountRaw, $instance),
            'statuses'   => $statuses,
        ];
    }

    private function lookupAccount(string $instance, string $acct): array
    {
        $endpoint = sprintf(
            'https://%s/api/v1/accounts/lookup?%s',
            $instance,
            http_build_query(['acct' => $acct])
        );
        return $this->http->getJson($endpoint, $this->headersFor($instance));
    }

    /**
     * Zerlegt eine Status-Permalink-URL wie
     * https://norden.social/@christiansagt/113456789012345678
     * in [instance, id].
     */
    private function parseStatusUrl(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if (!$host || !preg_match('#(\d{5,})/?$#', $path, $m)) {
            throw new \InvalidArgumentException('Konnte keine Status-ID aus der URL lesen: ' . $url);
        }

        return [$host, $m[1]];
    }

    /**
     * Erkennt entweder eine Profil-URL (https://instanz/@user) oder ein
     * direktes Handle (user@instanz / @user@instanz).
     */
    private function parseAccountReference(string $handleOrUrl): array
    {
        if (preg_match('#^https?://([^/]+)/@([^/@]+)#', $handleOrUrl, $m)) {
            return [$m[1], $m[2]];
        }

        $handle = ltrim($handleOrUrl, '@');
        if (preg_match('#^([a-z0-9_.-]+)@([a-z0-9.-]+)$#i', $handle, $m)) {
            return [$m[2], $m[1]];
        }

        throw new \InvalidArgumentException('Konnte kein Konto aus "' . $handleOrUrl . '" ermitteln.');
    }

    private function headersFor(string $instance): array
    {
        $headers = ['Accept' => 'application/json'];
        if (!empty($this->tokens[$instance])) {
            $headers['Authorization'] = 'Bearer ' . $this->tokens[$instance];
        }
        return $headers;
    }

    /**
     * Bildet ein Mastodon-Status-Objekt auf unser internes, dienstunabhängiges
     * Schema ab (siehe README.md). Verschachtelte Reblogs/Boosts werden
     * rekursiv mit normalisiert.
     */
    private function normalizeStatus(array $raw, string $instance, string $sourceUrl): array
    {
        $status = [
            'type'         => 'status',
            'service'      => $this->getKey(),
            'instance'     => $instance,
            'source_url'   => $sourceUrl ?: ($raw['url'] ?? $raw['uri'] ?? ''),
            'id'           => $raw['id'] ?? null,
            'url'          => $raw['url'] ?? $raw['uri'] ?? $sourceUrl,
            'created_at'   => $raw['created_at'] ?? null,
            'content_html' => $raw['content'] ?? '',
            'spoiler_text' => $raw['spoiler_text'] ?? '',
            'sensitive'    => (bool) ($raw['sensitive'] ?? false),
            'visibility'   => $raw['visibility'] ?? 'public',
            'language'     => $raw['language'] ?? null,
            'account'      => $this->normalizeAccount($raw['account'] ?? [], $instance),
            'media_attachments' => array_map(
                static fn (array $m): array => [
                    'type'        => $m['type'] ?? 'unknown',
                    'url'         => $m['url'] ?? null,
                    'preview_url' => $m['preview_url'] ?? null,
                    'description' => $m['description'] ?? '',
                ],
                $raw['media_attachments'] ?? []
            ),
            'card'  => $this->normalizeCard($raw['card'] ?? null),
            'stats' => [
                'replies_count'    => $raw['replies_count'] ?? 0,
                'reblogs_count'    => $raw['reblogs_count'] ?? 0,
                'favourites_count' => $raw['favourites_count'] ?? 0,
            ],
            'reblog' => null,
        ];

        if (!empty($raw['reblog']) && is_array($raw['reblog'])) {
            $status['reblog'] = $this->normalizeStatus($raw['reblog'], $instance, $raw['reblog']['url'] ?? '');
        }

        return $status;
    }

    /**
     * Normalisiert ein Mastodon-Account-Objekt.
     *
     * Wichtig: Die Mastodon-API liefert "acct" für Konten der abgefragten
     * Instanz selbst OHNE Domain-Suffix (z. B. "christiansagt" statt
     * "christiansagt@norden.social") - nur bei föderierten/entfernten Konten
     * ist die Domain bereits enthalten. Für eine konsistente Anzeige wird
     * die Instanz-Domain hier bei Bedarf ergänzt.
     */
    private function normalizeAccount(array $raw, string $instance): array
    {
        if (empty($raw)) {
            return [];
        }

        $acct = $raw['acct'] ?? ($raw['username'] ?? '');
        if ($acct !== '' && !str_contains($acct, '@')) {
            $acct .= '@' . $instance;
        }

        return [
            'id'               => $raw['id'] ?? null,
            'username'         => $raw['username'] ?? '',
            'acct'             => $acct,
            'display_name'     => $raw['display_name'] ?: ($raw['username'] ?? ''),
            'url'              => $raw['url'] ?? '',
            'avatar'           => $raw['avatar'] ?? null,
            'header'           => $raw['header'] ?? null,
            'locked'           => (bool) ($raw['locked'] ?? false),
            'bot'              => (bool) ($raw['bot'] ?? false),
            'created_at'       => $raw['created_at'] ?? null,
            'note_html'        => $raw['note'] ?? '',
            'followers_count'  => $raw['followers_count'] ?? 0,
            'following_count'  => $raw['following_count'] ?? 0,
            'statuses_count'   => $raw['statuses_count'] ?? 0,
            'fields'           => array_map(
                static fn (array $f): array => [
                    'name'       => $f['name'] ?? '',
                    'value_html' => $f['value'] ?? '',
                    'verified'   => !empty($f['verified_at']),
                ],
                $raw['fields'] ?? []
            ),
        ];
    }

    private function normalizeCard(?array $raw): ?array
    {
        if (empty($raw) || empty($raw['url'])) {
            return null;
        }

        return [
            'url'           => $raw['url'],
            'title'         => $raw['title'] ?? '',
            'description'   => $raw['description'] ?? '',
            'image'         => $raw['image'] ?? null,
            'provider_name' => $raw['provider_name'] ?: parse_url($raw['url'], PHP_URL_HOST),
        ];
    }
}
