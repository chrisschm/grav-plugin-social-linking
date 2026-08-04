<?php

namespace Grav\Plugin\SocialLinking\Http;

/**
 * Sehr schlanker HTTP-Client für GET-Anfragen und Datei-Downloads.
 * Nutzt curl, falls verfügbar, sonst file_get_contents() mit Stream-Context.
 * Bewusst ohne Abhängigkeit auf Guzzle o.ä., damit das Plugin ohne Composer
 * install lauffähig ist.
 *
 * SSRF-Schutz (siehe SsrfGuard): JEDE tatsächlich kontaktierte URL - also
 * auch jedes einzelne Redirect-Ziel, nicht nur die Ursprungs-URL - wird vor
 * dem Verbindungsaufbau geprüft. Automatisches Redirect-Following ist daher
 * bewusst deaktiviert; Redirects werden manuell und mit erneuter Prüfung
 * verfolgt. Bei curl wird die geprüfte Ziel-IP zusätzlich per
 * CURLOPT_RESOLVE gepinnt, damit sich die Verbindung nicht durch eine
 * zwischenzeitlich abweichende DNS-Antwort ("DNS-Rebinding") umleiten lässt.
 */
class SimpleHttpClient
{
    private const MAX_REDIRECTS = 5;

    private SsrfGuard $ssrfGuard;

    /**
     * @param string[] $allowedPrivateHosts Bewusst erlaubte Hostnamen/IPs
     *        (z. B. eine interne Mastodon-Instanz), die trotz privater/
     *        lokaler Adresse nicht durch den SSRF-Schutz blockiert werden
     *        sollen. Siehe social-linking.yaml, Option
     *        "allowed_private_hosts". Sicherheitshinweis: Jeder freigegebene
     *        Host kann vom Grav-Server aus erreicht werden, inklusive
     *        interner Netzbereiche - nur eintragen, wem man vertraut bzw.
     *        was bewusst intern erreichbar sein soll.
     */
    public function __construct(
        private int $timeout = 10,
        private string $userAgent = 'Grav-SocialLinking/1.0 (+https://github.com/)',
        array $allowedPrivateHosts = []
    ) {
        $this->ssrfGuard = new SsrfGuard($allowedPrivateHosts);
    }

    /**
     * Führt einen GET-Request aus und dekodiert die Antwort als JSON.
     *
     * @throws \RuntimeException wenn die Anfrage fehlschlägt oder kein gültiges JSON liefert
     */
    public function getJson(string $url, array $headers = []): array
    {
        $raw = $this->get($url, $headers);
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new \RuntimeException('Antwort von "' . $url . '" ist kein gültiges JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Führt einen GET-Request aus und liefert den rohen Response-Body zurück.
     *
     * @throws \RuntimeException bei Netzwerkfehlern, SSRF-Verstößen oder HTTP-Statuscode >= 400
     */
    public function get(string $url, array $headers = []): string
    {
        return $this->getFollowingRedirects($url, $headers, 0);
    }

    /**
     * Lädt eine Datei (z. B. Avatar, Anhang, Card-Bild) herunter und speichert
     * sie unter dem angegebenen lokalen Pfad.
     *
     * Wichtig: Diese URLs stammen aus der API-Antwort der abgefragten
     * Instanz, NICHT vom Redakteur - eine böswillige/kompromittierte
     * Instanz könnte hier beliebige interne Ziele eintragen. Der
     * SSRF-Schutz greift daher gleichermaßen für download() wie für get().
     */
    public function download(string $url, string $targetPath, array $headers = []): bool
    {
        $data = $this->get($url, $headers);

        if ($data === '') {
            return false;
        }

        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return (bool) file_put_contents($targetPath, $data);
    }

    /**
     * Führt den eigentlichen Request aus und folgt Redirects manuell -
     * jede neue Ziel-URL (auch aus einem Location-Header) durchläuft
     * erneut den SSRF-Guard, bevor sie kontaktiert wird.
     */
    private function getFollowingRedirects(string $url, array $headers, int $redirectCount): string
    {
        if ($redirectCount > self::MAX_REDIRECTS) {
            throw new \RuntimeException('Zu viele Redirects (> ' . self::MAX_REDIRECTS . ') bei "' . $url . '"');
        }

        $resolvedIp = $this->ssrfGuard->assertAllowedAndResolve($url);

        $result = function_exists('curl_init')
            ? $this->curlGet($url, $headers, $resolvedIp)
            : $this->streamGet($url, $headers);

        if ($result['redirectTo'] !== null) {
            $nextUrl = $this->resolveRedirectTarget($url, $result['redirectTo']);
            return $this->getFollowingRedirects($nextUrl, $headers, $redirectCount + 1);
        }

        return $result['body'];
    }

    /**
     * @return array{body: string, redirectTo: ?string}
     */
    private function curlGet(string $url, array $headers, string $resolvedIp): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== null && str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }
        $port = parse_url($url, PHP_URL_PORT) ?? (parse_url($url, PHP_URL_SCHEME) === 'https' ? 443 : 80);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            // Redirects werden NICHT automatisch verfolgt (siehe
            // getFollowingRedirects) - jedes Ziel muss erst erneut den
            // SSRF-Guard durchlaufen.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_USERAGENT      => $this->userAgent,
            CURLOPT_HTTPHEADER     => $this->formatHeaders($headers),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            // Pinnt den Hostnamen fest auf die zuvor geprüfte IP-Adresse -
            // verhindert, dass sich zwischen Prüfung und Verbindungsaufbau
            // per DNS-Rebinding ein anderes (internes) Ziel unterschiebt.
            // TLS-SNI/Zertifikatsprüfung laufen dabei weiterhin gegen den
            // Hostnamen, nicht gegen die IP.
            CURLOPT_RESOLVE        => ["{$host}:{$port}:{$resolvedIp}"],
        ]);

        $resultBody = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $redirectTarget = curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: null;
        curl_close($ch);

        if ($resultBody === false) {
            throw new \RuntimeException('HTTP-Anfrage an "' . $url . '" fehlgeschlagen: ' . $error);
        }

        if ($status >= 300 && $status < 400 && $redirectTarget) {
            return ['body' => '', 'redirectTo' => $redirectTarget];
        }

        if ($status >= 400) {
            throw new \RuntimeException('HTTP-Fehler ' . $status . ' bei "' . $url . '"');
        }

        return ['body' => (string) $resultBody, 'redirectTo' => null];
    }

    /**
     * @return array{body: string, redirectTo: ?string}
     */
    private function streamGet(string $url, array $headers): array
    {
        $headerLines = $this->formatHeaders($headers);
        $headerLines[] = 'User-Agent: ' . $this->userAgent;

        $context = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => implode("\r\n", $headerLines),
                'timeout'         => $this->timeout,
                'ignore_errors'   => true,
                // Redirects auch im Stream-Fallback nicht automatisch
                'follow_location' => 0,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            throw new \RuntimeException('HTTP-Anfrage an "' . $url . '" fehlgeschlagen.');
        }

        $status = 0;
        $location = null;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                $status = (int) $m[1];
            }
            if (preg_match('#^Location:\s*(.+)$#i', trim($line), $m)) {
                $location = trim($m[1]);
            }
        }

        if ($status >= 300 && $status < 400 && $location) {
            return ['body' => '', 'redirectTo' => $location];
        }

        if ($status >= 400) {
            throw new \RuntimeException('HTTP-Fehler ' . $status . ' bei "' . $url . '"');
        }

        return ['body' => (string) $result, 'redirectTo' => null];
    }

    /**
     * Löst ein Location-Ziel (kann relativ oder absolut sein) gegenüber der
     * ursprünglich angefragten URL auf.
     */
    private function resolveRedirectTarget(string $originalUrl, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $base = parse_url($originalUrl);
        $scheme = $base['scheme'] ?? 'https';
        $host = $base['host'] ?? '';
        $port = isset($base['port']) ? ':' . $base['port'] : '';

        if (str_starts_with($location, '/')) {
            return $scheme . '://' . $host . $port . $location;
        }

        $basePath = isset($base['path']) ? preg_replace('#/[^/]*$#', '/', $base['path']) : '/';
        return $scheme . '://' . $host . $port . $basePath . $location;
    }

    private function formatHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $key => $value) {
            $out[] = $key . ': ' . $value;
        }
        return $out;
    }
}
