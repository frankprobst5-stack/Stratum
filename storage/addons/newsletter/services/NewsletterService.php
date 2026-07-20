<?php

declare(strict_types=1);

namespace Stratum\Modules\Newsletter;

use Stratum\Core\Database;
use Stratum\Core\Slug;

final class NewsletterService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> newest first */
    public function listIssues(bool $publishedOnly = true): array
    {
        $sql = 'SELECT * FROM ' . $this->db->table('newsletter_issues');
        if ($publishedOnly) {
            $sql .= ' WHERE is_published = 1';
        }
        $sql .= ' ORDER BY COALESCE(published_at, created_at) DESC';

        return $this->db->fetchAll($sql);
    }

    /** @return array<string, mixed>|null */
    public function findIssue(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('newsletter_issues') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @return array<string, mixed>|null */
    public function findIssueBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('newsletter_issues') . ' WHERE slug = :slug',
            ['slug' => $slug]
        );
    }

    /** @return array<string, mixed>|null the most recently published issue, or null if none are published yet */
    public function latestPublishedIssue(): ?array
    {
        $issues = $this->listIssues(true);

        return $issues[0] ?? null;
    }

    public function createIssue(string $title): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('newsletter_issues', [
            'title' => $title,
            'slug' => $this->uniqueSlug($title),
            'is_published' => 0,
            'published_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function setIssuePublished(int $issueId, bool $isPublished): void
    {
        $issue = $this->findIssue($issueId);
        if ($issue === null) {
            return;
        }

        $this->db->execute(
            'UPDATE ' . $this->db->table('newsletter_issues') . '
             SET is_published = :is_published, published_at = :published_at, updated_at = :now
             WHERE id = :id',
            [
                'is_published' => $isPublished ? 1 : 0,
                // Only stamped the first time an issue is published — re-toggling
                // off and back on doesn't bump its position in the "newest first" list.
                'published_at' => $isPublished ? ($issue['published_at'] ?? date('Y-m-d H:i:s')) : $issue['published_at'],
                'now' => date('Y-m-d H:i:s'),
                'id' => $issueId,
            ]
        );
    }

    /** @return array<int, array<string, mixed>> ordered by position */
    public function listPages(int $issueId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('newsletter_pages') . '
             WHERE issue_id = :issue_id ORDER BY position ASC',
            ['issue_id' => $issueId]
        );
    }

    /** @return array<string, mixed>|null */
    public function findPage(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('newsletter_pages') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @return array<string, mixed>|null the page at 1-indexed $position within $issueId, or null (used to render /newsletter/{slug}/{position}) */
    public function pageAtPosition(int $issueId, int $position): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('newsletter_pages') . '
             WHERE issue_id = :issue_id AND position = :position',
            ['issue_id' => $issueId, 'position' => $position]
        );
    }

    public function addPage(int $issueId, string $title, string $body): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('newsletter_pages', [
            'issue_id' => $issueId,
            'title' => $title,
            'body' => $body,
            'position' => $this->nextPosition($issueId),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updatePage(int $id, string $title, string $body): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('newsletter_pages') . '
             SET title = :title, body = :body, updated_at = :now WHERE id = :id',
            ['title' => $title, 'body' => $body, 'now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    /** Deletes the page and closes the gap in position numbering so 1..N stays contiguous within the issue. */
    public function deletePage(int $id): void
    {
        $page = $this->findPage($id);
        if ($page === null) {
            return;
        }

        $this->db->execute('DELETE FROM ' . $this->db->table('newsletter_pages') . ' WHERE id = :id', ['id' => $id]);

        $this->db->execute(
            'UPDATE ' . $this->db->table('newsletter_pages') . '
             SET position = position - 1
             WHERE issue_id = :issue_id AND position > :position',
            ['issue_id' => $page['issue_id'], 'position' => $page['position']]
        );
    }

    public function movePageUp(int $id): void
    {
        $this->swapWithNeighbor($id, direction: -1);
    }

    public function movePageDown(int $id): void
    {
        $this->swapWithNeighbor($id, direction: 1);
    }

    private function swapWithNeighbor(int $id, int $direction): void
    {
        $page = $this->findPage($id);
        if ($page === null) {
            return;
        }

        $table = $this->db->table('newsletter_pages');
        $comparator = $direction < 0 ? '<' : '>';
        $order = $direction < 0 ? 'DESC' : 'ASC';

        $neighbor = $this->db->fetchOne(
            "SELECT * FROM {$table} WHERE issue_id = :issue_id AND position {$comparator} :position ORDER BY position {$order} LIMIT 1",
            ['issue_id' => $page['issue_id'], 'position' => $page['position']]
        );
        if ($neighbor === null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->execute("UPDATE {$table} SET position = :position, updated_at = :now WHERE id = :id", ['position' => $neighbor['position'], 'now' => $now, 'id' => $page['id']]);
        $this->db->execute("UPDATE {$table} SET position = :position, updated_at = :now WHERE id = :id", ['position' => $page['position'], 'now' => $now, 'id' => $neighbor['id']]);
    }

    private function nextPosition(int $issueId): int
    {
        $row = $this->db->fetchOne(
            'SELECT MAX(position) AS max_position FROM ' . $this->db->table('newsletter_pages') . ' WHERE issue_id = :issue_id',
            ['issue_id' => $issueId]
        );

        return ((int) ($row['max_position'] ?? 0)) + 1;
    }

    private function uniqueSlug(string $value): string
    {
        $base = Slug::make($value, 'issue');
        $slug = $base;
        $suffix = 2;

        while ($this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('newsletter_issues') . ' WHERE slug = :slug',
            ['slug' => $slug]
        ) !== null) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
