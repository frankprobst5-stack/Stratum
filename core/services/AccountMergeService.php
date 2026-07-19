<?php

declare(strict_types=1);

namespace Stratum\Core;

use Throwable;

/**
 * Merges a duplicate member account (source) into the canonical one
 * (target): every piece of content and relationship state source ever
 * created moves to target, points are summed (re-checking rank promotion
 * via the existing ReputationService), then source is soft-deleted the
 * same way AuthService::softDeleteAccount() already does everywhere else.
 *
 * Soft-delete means the FK ON DELETE CASCADE/SET NULL clauses throughout
 * this schema never fire on their own — nothing moves without this
 * service explicitly doing it, which is the entire reason it exists
 * rather than just calling softDeleteAccount() on the duplicate.
 *
 * Lives in core/ rather than the users module for the same reason
 * TrashService/AccountExportService do: it reaches into tables owned by
 * forum/wiki/gallery/calendar/etc, not just users.
 */
final class AccountMergeService
{
    /**
     * Tables where the user column carries no UNIQUE constraint — a blind
     * UPDATE is always safe, source's rows simply move to target.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const SIMPLE_REASSIGN = [
        ['articles', 'author_id'],
        ['articles_revisions', 'author_id'],
        ['forum_topics', 'author_id'],
        ['forum_posts', 'author_id'],
        ['wiki_revisions', 'author_id'],
        ['calendar_events', 'author_id'],
        ['classifieds_listings', 'user_id'],
        ['gallery_photos', 'uploader_id'],
        ['videos', 'uploader_id'],
        ['comments', 'user_id'],
        ['downloads_versions', 'uploader_id'],
        ['links', 'submitted_by'],
        ['org_spaces_forum_topics', 'author_id'],
        ['org_spaces_forum_posts', 'author_id'],
        ['org_spaces_calendar_events', 'author_id'],
        ['org_spaces_files', 'uploader_id'],
        ['org_spaces_gallery_photos', 'uploader_id'],
        ['member_notes', 'user_id'],
        ['member_notes', 'author_id'],
        ['notifications', 'user_id'],
        ['notifications', 'actor_id'],
        ['moderation_reports', 'reporter_id'],
        ['moderation_reports', 'resolved_by'],
        ['donation_contributions', 'user_id'],
        ['donation_contributions', 'recorded_by'],
        ['dues_payments', 'user_id'],
        ['dues_payments', 'recorded_by'],
        ['membership_applications', 'reviewed_by'],
        ['ticker_messages', 'author_id'],
        ['org_spaces_announcements', 'author_id'],
        ['member_badges', 'awarded_by'],
        ['presence', 'user_id'],
    ];

    /**
     * Tables where the user column participates in a UNIQUE constraint
     * alongside the listed key columns — a blind UPDATE would throw a
     * duplicate-key error whenever target already has a row for the same
     * key (e.g. both accounts hold the same badge, rated the same item,
     * or already hold the same role). Resolved per-row in PHP: move the
     * row if target has no matching one, otherwise drop source's and let
     * target's existing row win.
     *
     * @var array<int, array{0: string, 1: string, 2: array<int, string>}>
     */
    private const DEDUPE_REASSIGN = [
        ['users_roles', 'user_id', ['role_id']],
        ['member_badges', 'user_id', ['badge_id']],
        ['ratings', 'user_id', ['ratable_type', 'ratable_id']],
        ['bookmarks', 'user_id', ['bookmarkable_type', 'bookmarkable_id']],
        ['org_spaces_members', 'user_id', ['org_id']],
        ['gallery_likes', 'user_id', ['photo_id']],
        ['forum_post_likes', 'user_id', ['post_id']],
        ['calendar_rsvps', 'user_id', ['event_id']],
        ['forum_poll_votes', 'user_id', ['poll_id']],
    ];

    public function __construct(
        private readonly Database $db,
        private readonly ReputationService $reputation
    ) {
    }

    public function merge(int $sourceId, int $targetId): void
    {
        $pdo = $this->db->pdo();
        $pdo->beginTransaction();

        try {
            foreach (self::SIMPLE_REASSIGN as [$table, $column]) {
                $this->reassignSimple($table, $column, $sourceId, $targetId);
            }

            foreach (self::DEDUPE_REASSIGN as [$table, $userColumn, $keyColumns]) {
                $this->reassignDeduped($table, $userColumn, $keyColumns, $sourceId, $targetId);
            }

            $this->mergeFriendRequests($sourceId, $targetId);
            $this->mergeFollows($sourceId, $targetId);
            $this->mergePoints($sourceId, $targetId);

            $now = date('Y-m-d H:i:s');
            $this->db->execute(
                'UPDATE ' . $this->db->table('users') . ' SET deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id',
                ['deleted_at' => $now, 'updated_at' => $now, 'id' => $sourceId]
            );

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function reassignSimple(string $table, string $column, int $source, int $target): void
    {
        $tbl = $this->db->table($table);
        $this->db->execute(
            "UPDATE {$tbl} SET {$column} = :target WHERE {$column} = :source",
            ['target' => $target, 'source' => $source]
        );
    }

    /** @param array<int, string> $keyColumns */
    private function reassignDeduped(string $table, string $userColumn, array $keyColumns, int $source, int $target): void
    {
        $tbl = $this->db->table($table);
        $keyList = implode(', ', $keyColumns);

        $sourceRows = $this->db->fetchAll(
            "SELECT id, {$keyList} FROM {$tbl} WHERE {$userColumn} = :source",
            ['source' => $source]
        );
        if ($sourceRows === []) {
            return;
        }

        $targetRows = $this->db->fetchAll(
            "SELECT {$keyList} FROM {$tbl} WHERE {$userColumn} = :target",
            ['target' => $target]
        );
        $targetKeys = array_map(
            static fn (array $r): string => self::rowKey($r, $keyColumns),
            $targetRows
        );

        foreach ($sourceRows as $row) {
            $key = self::rowKey($row, $keyColumns);

            if (in_array($key, $targetKeys, true)) {
                $this->db->execute("DELETE FROM {$tbl} WHERE id = :id", ['id' => $row['id']]);
                continue;
            }

            $this->db->execute(
                "UPDATE {$tbl} SET {$userColumn} = :target WHERE id = :id",
                ['target' => $target, 'id' => $row['id']]
            );
            $targetKeys[] = $key;
        }
    }

    /** @param array<int, string> $keyColumns */
    private static function rowKey(array $row, array $keyColumns): string
    {
        return implode('|', array_map(static fn (string $c): string => (string) $row[$c], $keyColumns));
    }

    /**
     * Directional and self-reference-prone: a blind column reassign could
     * produce a request from target to target (dropped), or collide with
     * an existing target<->counterpart request in either direction
     * (resolved the same keep-the-other-row way reassignDeduped() does).
     */
    private function mergeFriendRequests(int $source, int $target): void
    {
        $tbl = $this->db->table('friend_requests');

        $rows = $this->db->fetchAll(
            "SELECT id, sender_id, recipient_id FROM {$tbl} WHERE sender_id = :source1 OR recipient_id = :source2",
            ['source1' => $source, 'source2' => $source]
        );

        foreach ($rows as $row) {
            $sender = (int) $row['sender_id'] === $source ? $target : (int) $row['sender_id'];
            $recipient = (int) $row['recipient_id'] === $source ? $target : (int) $row['recipient_id'];

            if ($sender === $recipient) {
                $this->db->execute("DELETE FROM {$tbl} WHERE id = :id", ['id' => $row['id']]);
                continue;
            }

            $collision = $this->db->fetchOne(
                "SELECT id FROM {$tbl} WHERE sender_id = :sender AND recipient_id = :recipient AND id != :rowId",
                ['sender' => $sender, 'recipient' => $recipient, 'rowId' => $row['id']]
            );

            if ($collision !== null) {
                $this->db->execute("DELETE FROM {$tbl} WHERE id = :id", ['id' => $row['id']]);
                continue;
            }

            $this->db->execute(
                "UPDATE {$tbl} SET sender_id = :sender, recipient_id = :recipient WHERE id = :id",
                ['sender' => $sender, 'recipient' => $recipient, 'id' => $row['id']]
            );
        }
    }

    private function mergeFollows(int $source, int $target): void
    {
        $tbl = $this->db->table('member_follows');

        $rows = $this->db->fetchAll(
            "SELECT id, follower_id, followed_id FROM {$tbl} WHERE follower_id = :source1 OR followed_id = :source2",
            ['source1' => $source, 'source2' => $source]
        );

        foreach ($rows as $row) {
            $follower = (int) $row['follower_id'] === $source ? $target : (int) $row['follower_id'];
            $followed = (int) $row['followed_id'] === $source ? $target : (int) $row['followed_id'];

            if ($follower === $followed) {
                $this->db->execute("DELETE FROM {$tbl} WHERE id = :id", ['id' => $row['id']]);
                continue;
            }

            $collision = $this->db->fetchOne(
                "SELECT id FROM {$tbl} WHERE follower_id = :follower AND followed_id = :followed AND id != :rowId",
                ['follower' => $follower, 'followed' => $followed, 'rowId' => $row['id']]
            );

            if ($collision !== null) {
                $this->db->execute("DELETE FROM {$tbl} WHERE id = :id", ['id' => $row['id']]);
                continue;
            }

            $this->db->execute(
                "UPDATE {$tbl} SET follower_id = :follower, followed_id = :followed WHERE id = :id",
                ['follower' => $follower, 'followed' => $followed, 'id' => $row['id']]
            );
        }
    }

    /** Sums source's points into target via the existing award() path so rank promotion (and its notification) is re-checked, not reinvented here. */
    private function mergePoints(int $source, int $target): void
    {
        $row = $this->db->fetchOne(
            'SELECT points FROM ' . $this->db->table('users') . ' WHERE id = :id',
            ['id' => $source]
        );

        $points = $row !== null ? (int) $row['points'] : 0;
        if ($points > 0) {
            $this->reputation->award($target, $points);
        }
    }
}
