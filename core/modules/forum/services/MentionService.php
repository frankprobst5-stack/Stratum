<?php

declare(strict_types=1);

namespace Stratum\Modules\Forum;

use Stratum\Modules\Users\AuthService;

/**
 * Extracts @username mentions from a post body and resolves them to real
 * users. Lives in the forum module (not core) per the promote-on-2nd/3rd-
 * consumer rule — if comments or wiki want mentions later, promote it then.
 *
 * Matching is done against the users table (case-insensitive via the
 * column's utf8mb4_unicode_ci collation), so "@nosuchuser" simply resolves
 * to nothing — no error, no notification. A trailing sentence period after
 * a mention ("thanks @admin.") is retried without the punctuation.
 */
final class MentionService
{
    /** Hard cap on distinct users notified per post — an @-everyone spam post shouldn't fan out unbounded. */
    private const MAX_MENTIONS_PER_POST = 10;

    public function __construct(private readonly AuthService $users)
    {
    }

    /** @return array<int, array<string, mixed>> distinct existing users mentioned in $body, capped */
    public function extractMentionedUsers(string $body): array
    {
        if (!str_contains($body, '@')) {
            return [];
        }

        preg_match_all('/@([A-Za-z0-9_.\-]{2,64})/', $body, $matches);

        $found = [];
        foreach ($matches[1] as $candidate) {
            foreach (array_unique([$candidate, rtrim($candidate, '.-')]) as $username) {
                if ($username === '') {
                    continue;
                }

                $user = $this->users->findByUsername($username);
                if ($user !== null) {
                    $found[(int) $user['id']] = $user;
                    break;
                }
            }

            if (count($found) >= self::MAX_MENTIONS_PER_POST) {
                break;
            }
        }

        return array_values($found);
    }
}
