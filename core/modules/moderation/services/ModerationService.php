<?php

declare(strict_types=1);

namespace Stratum\Modules\Moderation;

use Stratum\Core\ContentResolver;
use Stratum\Core\Database;

/**
 * One shared reporting queue for user-flagged content across modules —
 * consumers add a report link pointing at /reports/new with their
 * reportable type; they never write to this table themselves.
 *
 * REPORTABLE_TYPES is the allow-list (deliberately narrower than everything
 * ContentResolver knows how to resolve — reportability is this module's own
 * business rule, not the resolver's). Growing the queue to gallery photos,
 * classifieds listings, etc. means adding a ContentResolver case (if not
 * already there) plus one type here and a report link in that module's
 * template — no schema or queue changes.
 */
final class ModerationService
{
    private const REASON_MAX_LENGTH = 500;
    private const CLOSED_LIST_LIMIT = 30;

    private readonly ContentResolver $resolver;

    public function __construct(private readonly Database $db)
    {
        $this->resolver = new ContentResolver($db);
    }

    /** @return array<int, string> */
    public function reportableTypes(): array
    {
        return ['forum_post'];
    }

    /**
     * Resolves a reportable to its display title and local URL, or null if
     * the type isn't allow-listed or the content doesn't exist / is deleted.
     *
     * @return ?array{title: string, url: string}
     */
    public function resolveTarget(string $type, int $id): ?array
    {
        if (!in_array($type, $this->reportableTypes(), true)) {
            return null;
        }

        return $this->resolver->resolve($type, $id);
    }

    /** Dedup matches membership's rule: a duplicate *open* report by the same reporter is rejected, but re-reporting after a resolve/dismiss is allowed. */
    public function hasOpenReport(string $type, int $id, int $reporterId): bool
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('moderation_reports') . "
             WHERE reportable_type = :type AND reportable_id = :id
             AND reporter_id = :reporter_id AND status = 'open'",
            ['type' => $type, 'id' => $id, 'reporter_id' => $reporterId]
        );

        return $row !== null;
    }

    public function create(string $type, int $id, int $reporterId, string $reason, string $contentTitle, string $contentUrl): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->insert('moderation_reports', [
            'reportable_type' => $type,
            'reportable_id' => $id,
            'reporter_id' => $reporterId,
            'reason' => mb_substr($reason, 0, self::REASON_MAX_LENGTH),
            'content_title' => mb_substr($contentTitle, 0, 191),
            'content_url' => $contentUrl,
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listOpen(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('moderation_reports') . "
             WHERE status = 'open' ORDER BY created_at ASC, id ASC"
        );
    }

    /** @return array<int, array<string, mixed>> most recently closed first */
    public function listClosed(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('moderation_reports') . "
             WHERE status <> 'open' ORDER BY resolved_at DESC, id DESC
             LIMIT " . self::CLOSED_LIST_LIMIT
        );
    }

    /** @return ?array<string, mixed> */
    public function find(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('moderation_reports') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /**
     * Closes an open report as 'resolved' or 'dismissed'. Returns false if
     * the report doesn't exist or was already closed — the status check is
     * in the WHERE clause, so two moderators racing on the same report can't
     * both "win."
     */
    public function close(int $id, string $status, int $resolvedBy, string $note): bool
    {
        if (!in_array($status, ['resolved', 'dismissed'], true)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');

        $affected = $this->db->execute(
            'UPDATE ' . $this->db->table('moderation_reports') . "
             SET status = :status, resolution_note = :note, resolved_by = :resolved_by,
                 resolved_at = :resolved_at, updated_at = :updated_at
             WHERE id = :id AND status = 'open'",
            [
                'status' => $status,
                'note' => $note !== '' ? mb_substr($note, 0, 255) : null,
                'resolved_by' => $resolvedBy,
                'resolved_at' => $now,
                'updated_at' => $now,
                'id' => $id,
            ]
        );

        return $affected > 0;
    }
}
