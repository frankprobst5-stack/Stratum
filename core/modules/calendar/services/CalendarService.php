<?php

declare(strict_types=1);

namespace Stratum\Modules\Calendar;

use DateInterval;
use DateTimeImmutable;
use Stratum\Core\Database;
use Stratum\Core\Slug;

final class CalendarService
{
    private const MAX_OCCURRENCES = 26;

    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array{id: int, name: string, slug: string, description: ?string}> */
    public function listCalendars(): array
    {
        $rows = $this->db->fetchAll('SELECT id, name, slug, description FROM ' . $this->db->table('calendars') . ' ORDER BY name');

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'slug' => $r['slug'],
            'description' => $r['description'],
        ], $rows);
    }

    public function createCalendar(string $name, string $description): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('calendars', [
            'name' => $name,
            'slug' => $this->uniqueSlug('calendars', $name, 'calendar'),
            'description' => $description !== '' ? $description : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findCalendarBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('calendars') . ' WHERE slug = :slug',
            ['slug' => $slug]
        );
    }

    /** @return array<string, mixed>|null */
    public function findCalendar(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('calendars') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @return array<int, array<string, mixed>> upcoming events, soonest first, with the calendar's name/slug joined in */
    public function listUpcomingEvents(?int $calendarId = null, int $limit = 200): array
    {
        $eventsTable = $this->db->table('calendar_events');
        $calendarsTable = $this->db->table('calendars');

        $sql = "SELECT e.*, c.name AS calendar_name, c.slug AS calendar_slug
                FROM {$eventsTable} e
                JOIN {$calendarsTable} c ON c.id = e.calendar_id
                WHERE e.deleted_at IS NULL AND e.starts_at >= :cutoff";
        $params = ['cutoff' => date('Y-m-d H:i:s', strtotime('-1 day'))];

        if ($calendarId !== null) {
            $sql .= ' AND e.calendar_id = :calendar_id';
            $params['calendar_id'] = $calendarId;
        }

        $sql .= ' ORDER BY e.starts_at ASC LIMIT ' . max(1, $limit);

        return $this->db->fetchAll($sql, $params);
    }

    /** @return array<string, mixed>|null */
    public function findEvent(int $id): ?array
    {
        $eventsTable = $this->db->table('calendar_events');
        $calendarsTable = $this->db->table('calendars');

        return $this->db->fetchOne(
            "SELECT e.*, c.name AS calendar_name, c.slug AS calendar_slug
             FROM {$eventsTable} e
             JOIN {$calendarsTable} c ON c.id = e.calendar_id
             WHERE e.id = :id AND e.deleted_at IS NULL",
            ['id' => $id]
        );
    }

    /**
     * Materializes a recurring event as $occurrenceCount independent rows
     * sharing a series_id (the first occurrence's own id) — see the
     * Stage 4a plan for why this isn't computed on the fly.
     *
     * @return int[] the created event ids, in order
     */
    public function createEvent(
        int $calendarId,
        int $authorId,
        string $title,
        string $description,
        string $location,
        string $startsAt,
        ?string $endsAt,
        bool $isAllDay,
        string $recurrenceType,
        int $occurrenceCount
    ): array {
        $count = $recurrenceType === 'none' ? 1 : max(1, min(self::MAX_OCCURRENCES, $occurrenceCount));

        $start = new DateTimeImmutable($startsAt);
        $end = $endsAt !== null && $endsAt !== '' ? new DateTimeImmutable($endsAt) : null;
        $duration = $end !== null ? $start->diff($end) : null;

        $now = date('Y-m-d H:i:s');
        $ids = [];
        $seriesId = null;

        for ($i = 0; $i < $count; $i++) {
            $occurrenceStart = $this->shift($start, $recurrenceType, $i);
            $occurrenceEnd = $duration !== null ? $occurrenceStart->add($duration) : null;

            $id = (int) $this->db->insert('calendar_events', [
                'calendar_id' => $calendarId,
                'author_id' => $authorId,
                'title' => $title,
                'description' => $description !== '' ? $description : null,
                'location' => $location !== '' ? $location : null,
                'starts_at' => $occurrenceStart->format('Y-m-d H:i:s'),
                'ends_at' => $occurrenceEnd?->format('Y-m-d H:i:s'),
                'is_all_day' => $isAllDay ? 1 : 0,
                'series_id' => $seriesId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($seriesId === null && $count > 1) {
                $seriesId = $id;
                $this->db->execute(
                    'UPDATE ' . $this->db->table('calendar_events') . ' SET series_id = :series_id WHERE id = :id',
                    ['series_id' => $seriesId, 'id' => $id]
                );
            }

            $ids[] = $id;
        }

        return $ids;
    }

    public function softDeleteEvent(int $id): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('calendar_events') . ' SET deleted_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public function setRsvp(int $eventId, int $userId, string $status): void
    {
        $table = $this->db->table('calendar_rsvps');
        $now = date('Y-m-d H:i:s');

        $existing = $this->db->fetchOne(
            "SELECT id FROM {$table} WHERE event_id = :event_id AND user_id = :user_id",
            ['event_id' => $eventId, 'user_id' => $userId]
        );

        if ($existing === null) {
            $this->db->insert('calendar_rsvps', [
                'event_id' => $eventId,
                'user_id' => $userId,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        $this->db->execute(
            "UPDATE {$table} SET status = :status, updated_at = :now WHERE id = :id",
            ['status' => $status, 'now' => $now, 'id' => $existing['id']]
        );
    }

    public function myRsvp(int $eventId, int $userId): ?string
    {
        $row = $this->db->fetchOne(
            'SELECT status FROM ' . $this->db->table('calendar_rsvps') . ' WHERE event_id = :event_id AND user_id = :user_id',
            ['event_id' => $eventId, 'user_id' => $userId]
        );

        return $row['status'] ?? null;
    }

    /** @return array<int, array{user_id: int, status: string}> */
    public function listRsvps(int $eventId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT user_id, status FROM ' . $this->db->table('calendar_rsvps') . '
             WHERE event_id = :event_id ORDER BY updated_at ASC',
            ['event_id' => $eventId]
        );

        return array_map(static fn (array $r): array => [
            'user_id' => (int) $r['user_id'],
            'status' => $r['status'],
        ], $rows);
    }

    /** Marks $userId as having actually attended $eventId — idempotent, a re-check-in just no-ops rather than erroring. */
    public function checkIn(int $eventId, int $userId, ?int $checkedInBy): void
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('calendar_attendance') . '
             WHERE event_id = :event_id AND user_id = :user_id',
            ['event_id' => $eventId, 'user_id' => $userId]
        );
        if ($existing !== null) {
            return;
        }

        $this->db->insert('calendar_attendance', [
            'event_id' => $eventId,
            'user_id' => $userId,
            'checked_in_at' => date('Y-m-d H:i:s'),
            'checked_in_by' => $checkedInBy,
        ]);
    }

    public function removeCheckIn(int $eventId, int $userId): void
    {
        $this->db->execute(
            'DELETE FROM ' . $this->db->table('calendar_attendance') . '
             WHERE event_id = :event_id AND user_id = :user_id',
            ['event_id' => $eventId, 'user_id' => $userId]
        );
    }

    /** @return array<int, array{user_id: int, checked_in_at: string}> */
    public function listAttendance(int $eventId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT user_id, checked_in_at FROM ' . $this->db->table('calendar_attendance') . '
             WHERE event_id = :event_id ORDER BY checked_in_at ASC',
            ['event_id' => $eventId]
        );

        return array_map(static fn (array $r): array => [
            'user_id' => (int) $r['user_id'],
            'checked_in_at' => $r['checked_in_at'],
        ], $rows);
    }

    /** Shifts $start forward by $occurrenceIndex units of $recurrenceType (0 = unchanged). */
    private function shift(DateTimeImmutable $start, string $recurrenceType, int $occurrenceIndex): DateTimeImmutable
    {
        if ($occurrenceIndex === 0) {
            return $start;
        }

        $spec = match ($recurrenceType) {
            'daily' => 'P' . $occurrenceIndex . 'D',
            'weekly' => 'P' . ($occurrenceIndex * 7) . 'D',
            'monthly' => 'P' . $occurrenceIndex . 'M',
            default => null,
        };

        return $spec !== null ? $start->add(new DateInterval($spec)) : $start;
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
