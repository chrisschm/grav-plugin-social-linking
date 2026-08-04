<?php

namespace Grav\Plugin\SocialLinking\Http;

/**
 * Zentrale SSRF-Absicherung für alle vom Plugin ausgehenden HTTP-Anfragen.
 *
 * Wird an zwei Stellen gebraucht, die beide letztlich in SimpleHttpClient
 * münden:
 *  - der von einer Seite/einem Redakteur angegebene "url"-Parameter
 *    ([social-embed url="..."]), der über MastodonProvider zur API-Endpunkt-
 *    URL wird,
 *  - Medien-URLs (Avatar, Anhänge, Card-Bild), die NICHT vom Redakteur,
 *    sondern von der abgefragten (ggf. böswilligen oder kompromittierten)
 *    Mastodon-Instanz selbst in der API-Antwort geliefert werden
 *    (siehe MediaCache::download()).
 *
 * Beide Fälle sind gleichermaßen sicherheitsrelevant: In beiden Fällen
 * entscheidet letztlich jemand außerhalb des Servers (Redakteur bzw.
 * Betreiber der fremden Instanz), welche URL der Grav-Server serverseitig
 * abruft. Ohne Prüfung ließe sich der Server dazu bringen, interne Adressen
 * abzufragen (Loopback, private Netze, Link-Local/Cloud-Metadaten wie
 * 169.254.169.254, ...).
 *
 * Die Prüfung erfolgt bewusst NICHT nur einmal auf die Ursprungs-URL,
 * sondern muss von den Aufrufern (SimpleHttpClient) für JEDE tatsächlich
 * kontaktierte Adresse erneut durchlaufen werden - insbesondere auch für
 * Redirect-Ziele, sonst wäre die Prüfung der Erst-URL wirkungslos.
 */
class SsrfGuard
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * @param string[] $allowedPrivateHosts Bewusst erlaubte Hostnamen/IPs
     *        (z. B. eine interne Mastodon-Instanz im Firmennetz), die trotz
     *        privater/lokaler Adresse NICHT blockiert werden sollen.
     *        Opt-in, leer per Default. Siehe social-linking.yaml.
     */
    public function __construct(
        private array $allowedPrivateHosts = []
    ) {
    }

    /**
     * Prüft eine URL vollständig (Schema + aufgelöste Ziel-IP) und liefert
     * die validierte IP zurück, gegen die die eigentliche Verbindung
     * aufgebaut werden sollte (siehe SimpleHttpClient::curlGet() -
     * CURLOPT_RESOLVE-Pinning gegen DNS-Rebinding).
     *
     * @throws \RuntimeException wenn die URL abgelehnt wird
     */
    public function assertAllowedAndResolve(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new \RuntimeException(
                'Nicht erlaubtes URL-Schema "' . $scheme . '" (nur http/https zulässig): ' . $url
            );
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            throw new \RuntimeException('Konnte keinen Host aus der URL lesen: ' . $url);
        }

        // PHP liefert IPv6-Host-Literale inkl. eckiger Klammern
        // (z. B. "[::1]" bei "https://[::1]/..."); für Filter/Vergleiche
        // wird die reine Adresse ohne Klammern benötigt.
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        if ($this->isExplicitlyAllowed($host)) {
            // Bewusster Opt-in (z. B. interne Instanz) - trotzdem muss der
            // Host auf mindestens eine IP auflösbar sein, sonst schlägt der
            // eigentliche Request ohnehin fehl.
            $ip = $this->resolveFirstIp($host);
            if ($ip === null) {
                throw new \RuntimeException('Host "' . $host . '" konnte nicht aufgelöst werden.');
            }
            return $ip;
        }

        // Host selbst schon eine IP-Adresse (Literal)?
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->isDisallowedIp($host)) {
                throw new \RuntimeException(
                    'Zugriff auf private/lokale Adresse "' . $host . '" ist nicht erlaubt (SSRF-Schutz).'
                );
            }
            return $host;
        }

        if (strcasecmp($host, 'localhost') === 0 || str_ends_with(strtolower($host), '.localhost')) {
            throw new \RuntimeException('Zugriff auf "' . $host . '" ist nicht erlaubt (SSRF-Schutz).');
        }

        // Hostname: ALLE aufgelösten Adressen (A + AAAA) prüfen, nicht nur
        // die erste - ein Hostname kann auf mehrere IPs zeigen, und DNS
        // liefert nicht garantiert eine stabile Reihenfolge.
        $ips = $this->resolveAllIps($host);
        if (empty($ips)) {
            throw new \RuntimeException('Host "' . $host . '" konnte nicht aufgelöst werden.');
        }

        foreach ($ips as $ip) {
            if ($this->isDisallowedIp($ip)) {
                throw new \RuntimeException(
                    'Host "' . $host . '" löst auf eine private/lokale Adresse auf (' . $ip . ') - '
                    . 'Zugriff aus SSRF-Schutzgründen abgelehnt.'
                );
            }
        }

        // Für das spätere IP-Pinning (CURLOPT_RESOLVE) wird eine konkrete,
        // bereits geprüfte Adresse zurückgegeben. Dadurch verbindet sich
        // curl garantiert zu genau der IP, die hier geprüft wurde, statt
        // den Hostnamen zum Verbindungszeitpunkt erneut (und ggf. anders,
        // Stichwort DNS-Rebinding) aufzulösen.
        return $ips[0];
    }

    private function isExplicitlyAllowed(string $host): bool
    {
        foreach ($this->allowedPrivateHosts as $allowed) {
            if (strcasecmp(trim((string) $allowed), $host) === 0) {
                return true;
            }
        }
        return false;
    }

    private function resolveFirstIp(string $host): ?string
    {
        $ips = $this->resolveAllIps($host);
        return $ips[0] ?? null;
    }

    /** @return string[] */
    private function resolveAllIps(string $host): array
    {
        $ips = [];

        $ipv4 = gethostbynamel($host);
        if (is_array($ipv4)) {
            $ips = array_merge($ips, $ipv4);
        }

        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (!empty($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Prüft eine einzelne IP-Adresse (v4 oder v6) gegen bekannte
     * private/reservierte/lokale Bereiche, die für serverseitige Requests
     * an fremde, öffentliche Dienste (Mastodon-Instanzen) niemals ein
     * legitimes Ziel sind.
     */
    private function isDisallowedIp(string $ip): bool
    {
        // FILTER_FLAG_NO_PRIV_RANGE + FILTER_FLAG_NO_RES_RANGE decken die
        // gängigen privaten/reservierten Bereiche für v4 UND v6 ab
        // (10/8, 172.16/12, 192.168/16, 127/8, 169.254/16, fc00::/7,
        // fe80::/10, ::1, 0.0.0.0/8, etc.).
        $publicRangeCheck = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        if ($publicRangeCheck === false) {
            return true;
        }

        // Zusätzliche, von den obigen Flags nicht immer abgedeckte
        // Sonderbereiche (Carrier-Grade-NAT, Benchmarking, IPv4-mapped
        // IPv6, Multicast).
        $extraDenylist = [
            '100.64.0.0/10',   // Carrier-Grade NAT (RFC 6598)
            '192.0.0.0/24',    // IETF Protocol Assignments
            '192.0.2.0/24',    // TEST-NET-1
            '198.18.0.0/15',   // Benchmarking
            '198.51.100.0/24', // TEST-NET-2
            '203.0.113.0/24',  // TEST-NET-3
            '224.0.0.0/4',     // Multicast
            '::ffff:0:0/96',   // IPv4-mapped IPv6
        ];

        foreach ($extraDenylist as $cidr) {
            if ($this->ipInCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $bits = (int) $bits;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $remainderBits = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($remainderBits === 0) {
            return true;
        }

        $mask = ~(0xFF >> $remainderBits) & 0xFF;
        return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
    }
}
