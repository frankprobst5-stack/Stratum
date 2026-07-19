<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Checks a small admin-configured JSON manifest URL for a newer version
 * than what's in the local VERSION file — deliberately doesn't phone
 * home to any Stratum-run service, because no such public update server
 * exists (or should get built speculatively here): today, updates are
 * curated and signed by hand per club (see UpdatePackageVerifier/
 * UpdateApplier), so "where is the manifest hosted" is left entirely up
 * to whoever's distributing updates to plug in — a gist, a page on
 * their own site, anything serving a small JSON file. Same safe-fetch
 * discipline (http/https only, timeout, no following into a redirect
 * loop) RssFetcher::fetchUrl() already established for "this app
 * fetches an admin-supplied external URL," duplicated rather than
 * shared since the two features have no other overlap.
 *
 * Expected manifest shape: {"version": "1.1.0", "notes": "...",
 * "download_url": "https://..."} — notes/download_url are optional.
 */
final class UpdateChecker
{
    private const TIMEOUT_SECONDS = 10;
    private const CONNECT_TIMEOUT_SECONDS = 5;
    private const USER_AGENT = 'Stratum CMS Update Checker/1.0';

    /** @return array{success: bool, updateAvailable: bool, latestVersion: ?string, notes: ?string, downloadUrl: ?string, error: ?string} */
    public function check(string $manifestUrl, string $currentVersion): array
    {
        try {
            $body = $this->fetchUrl($manifestUrl);
        } catch (\Throwable $e) {
            return $this->result(false, null, null, null, $e->getMessage());
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['version']) || !is_string($data['version'])) {
            return $this->result(false, null, null, null, 'Manifest response was not valid JSON with a "version" field.');
        }

        $latest = $data['version'];
        $notes = isset($data['notes']) && is_string($data['notes']) ? $data['notes'] : null;
        $downloadUrl = isset($data['download_url']) && is_string($data['download_url']) ? $data['download_url'] : null;

        return [
            'success' => true,
            'updateAvailable' => version_compare($latest, $currentVersion, '>'),
            'latestVersion' => $latest,
            'notes' => $notes,
            'downloadUrl' => $downloadUrl,
            'error' => null,
        ];
    }

    /** @return array{success: bool, updateAvailable: bool, latestVersion: ?string, notes: ?string, downloadUrl: ?string, error: ?string} */
    private function result(bool $success, ?string $latest, ?string $notes, ?string $downloadUrl, ?string $error): array
    {
        return [
            'success' => $success,
            'updateAvailable' => false,
            'latestVersion' => $latest,
            'notes' => $notes,
            'downloadUrl' => $downloadUrl,
            'error' => $error,
        ];
    }

    private function fetchUrl(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException('Update check URL must be http or https.');
        }

        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body = curl_exec($handle);
        $errno = curl_errno($handle);
        $error = curl_error($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($errno !== 0) {
            throw new \RuntimeException("Fetch failed: {$error}");
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("Manifest URL returned HTTP {$status}.");
        }

        if ($body === false || $body === '') {
            throw new \RuntimeException('Manifest response was empty.');
        }

        return $body;
    }
}
