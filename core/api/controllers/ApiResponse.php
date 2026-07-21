<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Response;

/**
 * Consistent JSON envelope for every /api/v1/ endpoint — not a new
 * response mechanism, just fixed shaping on top of the existing
 * Response::json() every other JSON endpoint (chat polling, notification
 * badge) already uses.
 */
final class ApiResponse
{
    /** @param array<string, mixed>|array<int, mixed> $data */
    public static function data(array $data, int $status = 200): Response
    {
        return Response::json(['data' => $data], $status);
    }

    /**
     * @param array<int, array<string, mixed>> $items already sliced to the current page
     */
    public static function paginated(array $items, int $page, int $perPage, int $total): Response
    {
        return Response::json([
            'data' => $items,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ]);
    }

    public static function error(string $message, int $status, string $code): Response
    {
        return Response::json(['error' => ['message' => $message, 'code' => $code]], $status);
    }

    public static function unauthenticated(): Response
    {
        return self::error('A valid API token is required.', 401, 'unauthenticated');
    }

    public static function forbidden(): Response
    {
        return self::error("You don't have permission to do that.", 403, 'forbidden');
    }

    public static function notFound(): Response
    {
        return self::error('Not found.', 404, 'not_found');
    }

    /** Stage 10 rate limiting — see ApiRateLimiter. Caller is expected to chain ->withHeader('Retry-After', ...). */
    public static function rateLimited(): Response
    {
        return self::error('Too many requests. Please slow down.', 429, 'rate_limited');
    }
}
