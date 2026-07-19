<?php

declare(strict_types=1);

namespace Stratum\Modules\Video;

/**
 * Extracts just the video ID from a pasted YouTube/Vimeo URL — never the
 * raw URL itself. Callers build embed markup from the extracted ID only
 * (same posture as BBCodeParser's [url] handling: validate first, only
 * ever emit markup built from the validated piece, not raw user input).
 */
final class VideoUrlParser
{
    /** @return array{sourceType: string, externalId: string}|null */
    public function parse(string $url): ?array
    {
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([a-zA-Z0-9_-]{11})#', $url, $m) === 1) {
            return ['sourceType' => 'youtube', 'externalId' => $m[1]];
        }

        if (preg_match('#vimeo\.com/(\d+)#', $url, $m) === 1) {
            return ['sourceType' => 'vimeo', 'externalId' => $m[1]];
        }

        return null;
    }
}
