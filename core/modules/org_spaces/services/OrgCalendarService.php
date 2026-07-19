<?php

declare(strict_types=1);

namespace Stratum\Modules\OrgSpaces;

use DateTimeImmutable;
use Stratum\Core\Database;

/**
 * A per-org private calendar — deliberately minimal compared to the
 * site-wide `calendar` module: no recurring-event materialization, no
 * RSVP. Neither was part of the confirmed requirement ("private calendar
 * per org"); a natural v1.1 addition if a chapter actually asks for it,
 * not built ahead of demand. Events are member-only, same privacy model
 * as the org forum.
 */
final class OrgCalendarService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> upcoming events, chronological */
    public function listUpcoming(int $orgId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('org_spaces_calendar_events') . '
             WHERE org_id = :org_id AND deleted_at IS NULL AND starts_at >= :now
             ORDER BY starts_at ASC',
            ['org_id' => $orgId, 'now' => date('Y-m-d H:i:s')]
        );
    }

    /** @return array<string, mixed>|null */
    public function findEvent(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('org_spaces_calendar_events') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    /** $startsAt/$endsAt are the browser's `datetime-local` format (T-separated); parsed and reformatted for MySQL, same as CalendarService. */
    public function createEvent(
        int $orgId,
        int $authorId,
        string $title,
        string $description,
        string $location,
        string $startsAt,
        string $endsAt
    ): int {
        $now = date('Y-m-d H:i:s');
        $starts = new DateTimeImmutable($startsAt);
        $ends = $endsAt !== '' ? new DateTimeImmutable($endsAt) : null;

        return (int) $this->db->insert('org_spaces_calendar_events', [
            'org_id' => $orgId,
            'author_id' => $authorId,
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'location' => $location !== '' ? $location : null,
            'starts_at' => $starts->format('Y-m-d H:i:s'),
            'ends_at' => $ends?->format('Y-m-d H:i:s'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function softDeleteEvent(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('org_spaces_calendar_events') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }
}
