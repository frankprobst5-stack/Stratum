<?php

declare(strict_types=1);

namespace Stratum\Modules\Forum;

use Stratum\Core\Database;
use Stratum\Core\Slug;

final class ForumService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array{id: int, name: string, slug: string}> */
    public function listCategories(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, name, slug FROM ' . $this->db->table('forum_categories') . ' ORDER BY weight, name'
        );

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'slug' => $r['slug'],
        ], $rows);
    }

    public function createCategory(string $name): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('forum_categories', [
            'name' => $name,
            'slug' => $this->uniqueSlug('forum_categories', $name, 'category'),
            'weight' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>> boards with computed
     *   topic_count/post_count plus a real "last post" preview
     *   (last_post_at/last_post_username/last_post_topic_id/
     *   last_post_topic_title, all NULL if the board has no posts) —
     *   added 2026-07-19 for the content-page presentation pass; a plain
     *   `last_post_at` timestamp with no author/topic attached read as
     *   thin next to real forum software's board lists, which always
     *   show who posted what last. `last_post_id` is resolved via its
     *   own correlated subquery (ORDER BY created_at DESC, id DESC LIMIT
     *   1) in an inner derived table, then joined once — guarantees
     *   exactly one row per board even if two posts share a timestamp,
     *   unlike a naive join on MAX(created_at) which could return two.
     */
    public function listBoards(): array
    {
        $boardsTable = $this->db->table('forum_boards');
        $topicsTable = $this->db->table('forum_topics');
        $postsTable = $this->db->table('forum_posts');
        $usersTable = $this->db->table('users');

        return $this->db->fetchAll("
            SELECT
                x.*,
                p.created_at AS last_post_at,
                u.username AS last_post_username,
                t.id AS last_post_topic_id,
                t.title AS last_post_topic_title
            FROM (
                SELECT
                    b.*,
                    (SELECT COUNT(*) FROM {$topicsTable} t WHERE t.board_id = b.id AND t.deleted_at IS NULL) AS topic_count,
                    (SELECT COUNT(*) FROM {$postsTable} p
                        JOIN {$topicsTable} t ON t.id = p.topic_id
                        WHERE t.board_id = b.id AND p.deleted_at IS NULL) AS post_count,
                    (SELECT p.id FROM {$postsTable} p
                        JOIN {$topicsTable} t ON t.id = p.topic_id
                        WHERE t.board_id = b.id AND p.deleted_at IS NULL
                        ORDER BY p.created_at DESC, p.id DESC LIMIT 1) AS last_post_id
                FROM {$boardsTable} b
            ) x
            LEFT JOIN {$postsTable} p ON p.id = x.last_post_id
            LEFT JOIN {$topicsTable} t ON t.id = p.topic_id
            LEFT JOIN {$usersTable} u ON u.id = p.author_id
            ORDER BY x.weight, x.name
        ");
    }

    /** @return array<string, mixed>|null */
    public function findBoardBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('forum_boards') . ' WHERE slug = :slug',
            ['slug' => $slug]
        );
    }

    /** @return array<string, mixed>|null */
    public function findBoard(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('forum_boards') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    public function createBoard(int $categoryId, string $name, string $description, ?int $parentId = null): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('forum_boards', [
            'category_id' => $categoryId,
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $this->uniqueSlug('forum_boards', $name, 'board'),
            'description' => $description !== '' ? $description : null,
            'weight' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Nests the flat listBoards() result under each board's parent —
     * pure array grouping, no query needed, since parent_id already comes
     * back on every row via listBoards()'s `b.*`. Top-level boards
     * (parent_id NULL) get a 'children' key holding their direct
     * sub-boards; only one level deep is rendered by the current
     * templates, but the grouping itself doesn't assume that — a
     * sub-board that itself has children just ends up with a populated
     * 'children' array nobody currently walks into, not a broken one.
     *
     * @param array<int, array<string, mixed>> $boards
     * @return array<int, array<string, mixed>> only top-level boards, each with a 'children' key
     */
    public function nestBoards(array $boards): array
    {
        $byParent = [];
        foreach ($boards as $board) {
            $byParent[$board['parent_id'] !== null ? (int) $board['parent_id'] : 0][] = $board;
        }

        $attachChildren = static function (array $board) use (&$attachChildren, $byParent): array {
            $board['children'] = array_map($attachChildren, $byParent[(int) $board['id']] ?? []);

            return $board;
        };

        return array_map($attachChildren, $byParent[0] ?? []);
    }

    /** @return array<int, array<string, mixed>> topics with computed reply_count/last_post_at/last_post_username (2026-07-19 — same "who posted last" preview listBoards() gained) */
    public function listTopicsForBoard(int $boardId): array
    {
        $topicsTable = $this->db->table('forum_topics');
        $postsTable = $this->db->table('forum_posts');
        $usersTable = $this->db->table('users');

        return $this->db->fetchAll("
            SELECT
                x.*,
                p.created_at AS last_post_at,
                u.username AS last_post_username
            FROM (
                SELECT
                    t.*,
                    (SELECT COUNT(*) FROM {$postsTable} p WHERE p.topic_id = t.id AND p.deleted_at IS NULL) - 1 AS reply_count,
                    (SELECT p.id FROM {$postsTable} p WHERE p.topic_id = t.id AND p.deleted_at IS NULL
                        ORDER BY p.created_at DESC, p.id DESC LIMIT 1) AS last_post_id
                FROM {$topicsTable} t
                WHERE t.board_id = :board_id AND t.deleted_at IS NULL
            ) x
            LEFT JOIN {$postsTable} p ON p.id = x.last_post_id
            LEFT JOIN {$usersTable} u ON u.id = p.author_id
            ORDER BY x.is_pinned DESC, x.last_post_id DESC
        ", ['board_id' => $boardId]);
    }

    /**
     * Cross-board recent topics — nothing else in this service queries
     * across all boards at once (listTopicsForBoard() is scoped to one
     * board), so this is a new query rather than a reuse. Backs the
     * "Recent Forum Posts" front-page block.
     *
     * @return array<int, array<string, mixed>> each row includes board_name/board_slug
     */
    public function listRecentTopics(int $limit): array
    {
        $topicsTable = $this->db->table('forum_topics');
        $boardsTable = $this->db->table('forum_boards');
        $postsTable = $this->db->table('forum_posts');

        return $this->db->fetchAll(
            "SELECT t.*, b.name AS board_name, b.slug AS board_slug,
                    (SELECT MAX(p.created_at) FROM {$postsTable} p WHERE p.topic_id = t.id AND p.deleted_at IS NULL) AS last_post_at
             FROM {$topicsTable} t
             JOIN {$boardsTable} b ON b.id = t.board_id
             WHERE t.deleted_at IS NULL
             ORDER BY t.created_at DESC
             LIMIT " . max(1, $limit)
        );
    }

    /** @return array<string, mixed>|null */
    public function findTopic(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('forum_topics') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /** @return array{topicId: int, postId: int} */
    public function createTopicWithFirstPost(int $boardId, int $authorId, string $title, string $body): array
    {
        $now = date('Y-m-d H:i:s');

        $topicId = (int) $this->db->insert('forum_topics', [
            'board_id' => $boardId,
            'author_id' => $authorId,
            'title' => $title,
            'is_pinned' => 0,
            'is_locked' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $postId = (int) $this->db->insert('forum_posts', [
            'topic_id' => $topicId,
            'author_id' => $authorId,
            'body' => $body,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['topicId' => $topicId, 'postId' => $postId];
    }

    /** @return array<int, array<string, mixed>> */
    public function listPostsForTopic(int $topicId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('forum_posts') . '
             WHERE topic_id = :topic_id AND deleted_at IS NULL
             ORDER BY created_at ASC',
            ['topic_id' => $topicId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findPost(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('forum_posts') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    public function reply(int $topicId, int $authorId, string $body): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('forum_posts', [
            'topic_id' => $topicId,
            'author_id' => $authorId,
            'body' => $body,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function setPinned(int $topicId, bool $pinned): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('forum_topics') . ' SET is_pinned = :v, updated_at = :now WHERE id = :id',
            ['v' => $pinned ? 1 : 0, 'now' => date('Y-m-d H:i:s'), 'id' => $topicId]
        );
    }

    public function setLocked(int $topicId, bool $locked): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('forum_topics') . ' SET is_locked = :v, updated_at = :now WHERE id = :id',
            ['v' => $locked ? 1 : 0, 'now' => date('Y-m-d H:i:s'), 'id' => $topicId]
        );
    }

    public function softDeleteTopic(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('forum_topics') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public function softDeletePost(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('forum_posts') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    /**
     * Only ever called right after createTopicWithFirstPost() — kept as a
     * separate method rather than folded into it, since a poll is optional
     * and the two inserts (topic, poll+options) don't need to share a
     * transaction boundary any more carefully than the topic+first-post
     * insert pair above already doesn't.
     *
     * @param array<int, string> $optionLabels
     */
    public function createPoll(int $topicId, string $question, array $optionLabels, ?string $closesAt): void
    {
        $pollId = (int) $this->db->insert('forum_polls', [
            'topic_id' => $topicId,
            'question' => $question,
            'closes_at' => $closesAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        foreach (array_values($optionLabels) as $position => $label) {
            $this->db->insert('forum_poll_options', [
                'poll_id' => $pollId,
                'label' => $label,
                'position' => $position,
            ]);
        }
    }

    /**
     * @return array{id: int, question: string, closes_at: ?string, isClosed: bool,
     *     options: array<int, array{id: int, label: string, votes: int}>, totalVotes: int}|null
     */
    public function findPollForTopic(int $topicId): ?array
    {
        $poll = $this->db->fetchOne(
            // NOW() on the DB's own clock, not a PHP timestamp — same
            // "compute time comparisons in MySQL" house rule scheduled
            // publishing and presence already established, so a
            // misconfigured APP_TIMEZONE can never make a poll close early
            // or late relative to what MySQL itself considers "now."
            'SELECT id, question, closes_at, (closes_at IS NOT NULL AND closes_at <= NOW()) AS is_closed
             FROM ' . $this->db->table('forum_polls') . ' WHERE topic_id = :topic_id',
            ['topic_id' => $topicId]
        );

        if ($poll === null) {
            return null;
        }

        $optionsTable = $this->db->table('forum_poll_options');
        $votesTable = $this->db->table('forum_poll_votes');

        $options = $this->db->fetchAll(
            "SELECT o.id, o.label, (SELECT COUNT(*) FROM {$votesTable} v WHERE v.option_id = o.id) AS votes
             FROM {$optionsTable} o WHERE o.poll_id = :poll_id ORDER BY o.position, o.id",
            ['poll_id' => $poll['id']]
        );

        $totalVotes = 0;
        $mappedOptions = [];
        foreach ($options as $option) {
            $votes = (int) $option['votes'];
            $totalVotes += $votes;
            $mappedOptions[] = ['id' => (int) $option['id'], 'label' => $option['label'], 'votes' => $votes];
        }

        return [
            'id' => (int) $poll['id'],
            'question' => $poll['question'],
            'closes_at' => $poll['closes_at'],
            'isClosed' => (bool) $poll['is_closed'],
            'options' => $mappedOptions,
            'totalVotes' => $totalVotes,
        ];
    }

    /** The option id this user already voted for in this poll, or null if they haven't. */
    public function myPollVote(int $pollId, int $userId): ?int
    {
        $row = $this->db->fetchOne(
            'SELECT option_id FROM ' . $this->db->table('forum_poll_votes') . '
             WHERE poll_id = :poll_id AND user_id = :user_id',
            ['poll_id' => $pollId, 'user_id' => $userId]
        );

        return $row !== null ? (int) $row['option_id'] : null;
    }

    /**
     * Single-choice voting (the UNIQUE key is poll_id+user_id, not
     * +option_id) — casting a new vote overwrites the user's previous
     * choice rather than adding a second row, so "change your vote" is
     * just voting again, same low-ceremony reasoning
     * ForumService::toggleLike() already applies to its own join table.
     */
    public function vote(int $pollId, int $optionId, int $userId): void
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('forum_poll_votes') . '
             WHERE poll_id = :poll_id AND user_id = :user_id',
            ['poll_id' => $pollId, 'user_id' => $userId]
        );

        if ($existing !== null) {
            $this->db->execute(
                'UPDATE ' . $this->db->table('forum_poll_votes') . ' SET option_id = :option_id WHERE id = :id',
                ['option_id' => $optionId, 'id' => $existing['id']]
            );

            return;
        }

        $this->db->insert('forum_poll_votes', [
            'poll_id' => $pollId,
            'option_id' => $optionId,
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Same toggle shape as GalleryService::toggleLike — a like can be taken back, so it's a row, not a counter. */
    /**
     * Returns true if the like was just added, false if it was just
     * removed — the caller (reputation awarding) needs to know which,
     * since only an add should ever award a point, never a removal.
     */
    public function toggleLike(int $postId, int $userId): bool
    {
        $table = $this->db->table('forum_post_likes');
        $existing = $this->db->fetchOne(
            "SELECT id FROM {$table} WHERE post_id = :post_id AND user_id = :user_id",
            ['post_id' => $postId, 'user_id' => $userId]
        );

        if ($existing !== null) {
            $this->db->execute("DELETE FROM {$table} WHERE id = :id", ['id' => $existing['id']]);

            return false;
        }

        $this->db->insert('forum_post_likes', [
            'post_id' => $postId,
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * @param array<int, int> $postIds
     * @return array<int, int> post_id => like count (posts with zero likes absent)
     */
    public function likeCountsForPosts(array $postIds): array
    {
        [$placeholders, $params] = $this->bindIdList($postIds);
        if ($placeholders === '') {
            return [];
        }

        $rows = $this->db->fetchAll(
            'SELECT post_id, COUNT(*) AS c FROM ' . $this->db->table('forum_post_likes') . "
             WHERE post_id IN ({$placeholders}) GROUP BY post_id",
            $params
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['post_id']] = (int) $row['c'];
        }

        return $counts;
    }

    /**
     * @param array<int, int> $postIds
     * @return array<int, int> post ids among $postIds this user has liked
     */
    public function likedPostIds(int $userId, array $postIds): array
    {
        [$placeholders, $params] = $this->bindIdList($postIds);
        if ($placeholders === '') {
            return [];
        }

        $params['user_id'] = $userId;
        $rows = $this->db->fetchAll(
            'SELECT post_id FROM ' . $this->db->table('forum_post_likes') . "
             WHERE user_id = :user_id AND post_id IN ({$placeholders})",
            $params
        );

        return array_map(static fn (array $r): int => (int) $r['post_id'], $rows);
    }

    /**
     * Builds an IN(...) clause with uniquely-named placeholders — this
     * codebase's PDO layer rejects a named placeholder reused twice.
     *
     * @param array<int, int> $ids
     * @return array{0: string, 1: array<string, int>}
     */
    private function bindIdList(array $ids): array
    {
        $placeholders = [];
        $params = [];
        foreach (array_values(array_unique($ids)) as $i => $id) {
            $placeholders[] = ':id' . $i;
            $params['id' . $i] = $id;
        }

        return [implode(', ', $placeholders), $params];
    }

    private function uniqueSlug(string $table, string $value, string $fallback): string
    {
        $base = Slug::make($value, $fallback);
        $slug = $base;
        $suffix = 2;

        while ($this->db->fetchOne(
            "SELECT id FROM " . $this->db->table($table) . " WHERE slug = :slug",
            ['slug' => $slug]
        ) !== null) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
