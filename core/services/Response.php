<?php

declare(strict_types=1);

namespace Stratum\Core;

final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    public function __construct(
        private string $body = '',
        private int $status = 200
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        $response = new self($body, $status);
        $response->headers['Content-Type'] = 'text/html; charset=utf-8';

        return $response;
    }

    /** @param array<string, mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        $response = new self((string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $status);
        $response->headers['Content-Type'] = 'application/json';

        return $response;
    }

    public static function xml(string $body, int $status = 200): self
    {
        $response = new self($body, $status);
        $response->headers['Content-Type'] = 'application/rss+xml; charset=utf-8';

        return $response;
    }

    public static function redirect(string $to, int $status = 302): self
    {
        $response = new self('', $status);
        $response->headers['Location'] = $to;

        return $response;
    }

    public static function notFound(): self
    {
        return self::html(self::renderErrorPage('404'), 404);
    }

    public static function forbidden(): self
    {
        return self::html(self::renderErrorPage('403'), 403);
    }

    public static function serverError(): self
    {
        return self::html(self::renderErrorPage('500'), 500);
    }

    /**
     * The 404/403/500 pages are plain static HTML under core/errors/ — never
     * web-served directly (core/ isn't inside public/), only ever read
     * server-side here. No templating needed since there's nothing dynamic
     * to interpolate; a missing file falls back to a bare status line rather
     * than fataling, since an error page failing is the last place you want
     * a second error.
     */
    private static function renderErrorPage(string $code): string
    {
        $path = dirname(__DIR__) . "/errors/{$code}.html";

        return is_file($path) ? (string) file_get_contents($path) : "{$code} error";
    }

    /**
     * A 503 shown to everyone except staff (`users.manage`) while
     * maintenance mode is on — pure string params, no DB access, same
     * "Response stays stateless" posture renderErrorPage() already has.
     * Deliberately doesn't reuse Stratum's own icon-circle/logo art the
     * way the 403/404/500 pages do: those are Stratum-the-product's own
     * branding, but this page is shown under the *club's* site name, so
     * it stays text-only rather than mixing in branding that isn't theirs.
     */
    public static function maintenance(string $siteName, string $message): self
    {
        $safeName = htmlspecialchars($siteName, ENT_QUOTES);
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES));

        $body = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . "<title>{$safeName} — Down for maintenance</title>"
            . '<style>
                body { margin:0; background:#0b0d12; color:#fff; font-family:system-ui,sans-serif;
                    min-height:100vh; display:flex; flex-direction:column; align-items:center;
                    justify-content:center; padding:2rem 1rem; box-sizing:border-box; text-align:center; }
                h1 { font-size:1.6rem; margin-bottom:0.5rem; }
                p { color:#aaa; max-width:32rem; line-height:1.5; }
            </style></head><body>'
            . "<h1>{$safeName}</h1>"
            . "<p>{$safeMessage}</p>"
            . '</body></html>';

        return self::html($body, 503);
    }

    /**
     * Streams a file body with an explicit Content-Type and a
     * Content-Disposition attachment filename — per docs/coding-standards.md,
     * never serve uploads directly from storage/, always through a
     * controller that sets these headers itself.
     */
    public static function file(string $body, string $contentType, string $downloadName): self
    {
        $response = new self($body, 200);
        $response->headers['Content-Type'] = $contentType;
        // Strip anything that could break out of the quoted header value (CR/LF/quotes).
        $safeName = str_replace(['"', "\r", "\n"], '', $downloadName);
        $response->headers['Content-Disposition'] = "attachment; filename=\"{$safeName}\"";

        return $response;
    }

    /**
     * Same as file() but without Content-Disposition, so the browser plays
     * the content inline (e.g. a <video> tag) instead of forcing a download
     * prompt — needed for native video playback (Stage 5b). Still never
     * serves directly from storage/, same controller-mediated posture.
     */
    public static function streamFile(string $body, string $contentType): self
    {
        $response = new self($body, 200);
        $response->headers['Content-Type'] = $contentType;

        return $response;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** Fluent — sets an arbitrary response header, e.g. `Retry-After` on a 429. Every other header on this class is set internally by a specific factory; this is the generic escape hatch for the rest. */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body;
    }
}
