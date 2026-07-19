<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * A real point/reputation system — the explicit decision this backlog
 * item called for, not a silent build-or-skip. Points accumulate,
 * never decrease (no action in this app currently deducts them), so
 * rank promotion is naturally monotonic: award() always checks whether
 * the new total qualifies for a higher rank than the member currently
 * holds and promotes them in place, the same "compute don't invent a
 * separate state machine" discipline this app applies everywhere.
 *
 * Lives in core/services/ (not the users module) since award() is
 * called from several unrelated content modules (forum, wiki, gallery)
 * — the same reasoning ContentResolver/TrashService live in core.
 * Takes the whole App, not just Database, because it needs
 * App::notify() too, on promotion.
 */
final class ReputationService
{
    public function __construct(private readonly App $app)
    {
    }

    public function award(int $userId, int $points): void
    {
        $db = $this->app->db;

        $db->execute(
            'UPDATE ' . $db->table('users') . ' SET points = points + :points, updated_at = :now WHERE id = :id',
            ['points' => $points, 'now' => date('Y-m-d H:i:s'), 'id' => $userId]
        );

        $this->promoteIfEligible($userId);
    }

    private function promoteIfEligible(int $userId): void
    {
        $db = $this->app->db;

        $user = $db->fetchOne(
            'SELECT points, rank_id FROM ' . $db->table('users') . ' WHERE id = :id',
            ['id' => $userId]
        );
        if ($user === null) {
            return;
        }

        $eligibleRank = $db->fetchOne(
            'SELECT id, name FROM ' . $db->table('ranks') . '
             WHERE min_points <= :points ORDER BY min_points DESC LIMIT 1',
            ['points' => $user['points']]
        );

        if ($eligibleRank === null) {
            return;
        }

        $currentRankId = $user['rank_id'] !== null ? (int) $user['rank_id'] : null;
        if ((int) $eligibleRank['id'] === $currentRankId) {
            return;
        }

        $db->execute(
            'UPDATE ' . $db->table('users') . ' SET rank_id = :rank_id WHERE id = :id',
            ['rank_id' => $eligibleRank['id'], 'id' => $userId]
        );

        $this->app->notify([
            'user_id' => $userId,
            'actor_id' => null,
            'type' => 'rank.promoted',
            'message' => "You've been promoted to {$eligibleRank['name']}!",
            'url' => '/profile',
        ]);
    }
}
