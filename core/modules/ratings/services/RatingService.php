<?php

declare(strict_types=1);

namespace Stratum\Modules\Ratings;

use Stratum\Core\Database;

final class RatingService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * One rating per user per content item — re-rating overwrites the
     * previous score (an UPDATE, not a second row), same "changing your
     * mind is just doing it again" posture ForumService::vote() already
     * takes for poll ballots.
     */
    public function rate(string $type, int $id, int $userId, int $score): void
    {
        $score = max(1, min(5, $score));
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('ratings') . '
             WHERE ratable_type = :type AND ratable_id = :id AND user_id = :user_id',
            ['type' => $type, 'id' => $id, 'user_id' => $userId]
        );

        if ($existing !== null) {
            $this->db->execute(
                'UPDATE ' . $this->db->table('ratings') . ' SET score = :score, updated_at = :now WHERE id = :id',
                ['score' => $score, 'now' => $now, 'id' => $existing['id']]
            );

            return;
        }

        $this->db->insert('ratings', [
            'ratable_type' => $type,
            'ratable_id' => $id,
            'user_id' => $userId,
            'score' => $score,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array{average: float, count: int} */
    public function summaryFor(string $type, int $id): array
    {
        $row = $this->db->fetchOne(
            'SELECT AVG(score) AS avg_score, COUNT(*) AS c FROM ' . $this->db->table('ratings') . '
             WHERE ratable_type = :type AND ratable_id = :id',
            ['type' => $type, 'id' => $id]
        );

        return [
            'average' => $row !== null && $row['avg_score'] !== null ? round((float) $row['avg_score'], 1) : 0.0,
            'count' => $row !== null ? (int) $row['c'] : 0,
        ];
    }

    public function myRating(string $type, int $id, int $userId): ?int
    {
        $row = $this->db->fetchOne(
            'SELECT score FROM ' . $this->db->table('ratings') . '
             WHERE ratable_type = :type AND ratable_id = :id AND user_id = :user_id',
            ['type' => $type, 'id' => $id, 'user_id' => $userId]
        );

        return $row !== null ? (int) $row['score'] : null;
    }
}
