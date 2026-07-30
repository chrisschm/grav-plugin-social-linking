<?php

namespace Grav\Plugin\SocialLinking\Http;

/**
 * Sehr schlanker HTTP-Client für GET-Anfragen und Datei-Downloads.
 * Nutzt curl, falls verfügbar, sonst file_get_contents() mit Stream-Context.
 * Bewusst ohne Abhängigkeit auf Guzzle o.ä., damit das Plugin ohne Composer
 * install lauffähig ist.
 */
class SimpleHttpClient
{
    public function __construct(
        private int $timeout = 10,
        private string $userAgent = 'Grav-SocialLinking/1.0 (+https://github.com/)'
    ) {
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
     * @throws \RuntimeException bei Netzwerkfehlern oder HTTP-Statuscode >= 400
     */
    public function get(string $url, array $headers = []): string
    {
        if (function_exists('curl_init')) {
            return $this->curlGet($url, $headers);
        }

        return $this->streamGet($url, $headers);
    }

    /**
     * Lädt eine Datei (z. B. Avatar, Anhang, Card-Bild) herunter und speichert
     * sie unter dem angegebenen lokalen Pfad.
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

    private function curlGet(string $url, array $headers): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_USERAGENT      => $this->userAgent,
            CURLOPT_HTTPHEADER     => $this->formatHeaders($headers),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            throw new \RuntimeException('HTTP-Anfrage an "' . $url . '" fehlgeschlagen: ' . $error);
        }
        if ($status >= 400) {
            throw new \RuntimeException('HTTP-Fehler ' . $status . ' bei "' . $url . '"');
        }

        return (string) $result;
    }

    private function streamGet(string $url, array $headers): string
    {
        $headerLines = $this->formatHeaders($headers);
        $headerLines[] = 'User-Agent: ' . $this->userAgent;

        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => implode("\r\n", $headerLines),
                'timeout'       => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            throw new \RuntimeException('HTTP-Anfrage an "' . $url . '" fehlgeschlagen.');
        }

        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) {
                $status = (int) $m[1];
            }
        }
        if ($status >= 400) {
            throw new \RuntimeException('HTTP-Fehler ' . $status . ' bei "' . $url . '"');
        }

        return (string) $result;
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
