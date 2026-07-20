<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\CalendarApiController;
use Stratum\Modules\Calendar\CalendarService;
use Tests\TestCase;

final class CalendarApiTest extends TestCase
{
    /** @var int[] event ids created by this test, cleaned up in tearDown() */
    private array $eventIds = [];
    /** @var int[] calendar ids created by this test, cleaned up in tearDown() */
    private array $calendarIds = [];

    protected function tearDown(): void
    {
        foreach ($this->eventIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('calendar_events') . ' WHERE id = :id', ['id' => $id]);
        }
        foreach ($this->calendarIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('calendars') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->eventIds = [];
        $this->calendarIds = [];

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function createCalendar(): array
    {
        $service = new CalendarService($this->db);
        $name = 'API test calendar ' . bin2hex(random_bytes(4));
        $service->createCalendar($name, '');

        $match = array_values(array_filter(
            $service->listCalendars(),
            static fn (array $c): bool => $c['name'] === $name
        ));
        $calendar = $match[0];
        $this->calendarIds[] = (int) $calendar['id'];

        return $calendar;
    }

    /** @return array<string, mixed> the created event */
    private function createEvent(int $calendarId, int $authorId, string $startsAt): array
    {
        $service = new CalendarService($this->db);
        $ids = $service->createEvent(
            $calendarId,
            $authorId,
            'API test event ' . bin2hex(random_bytes(4)),
            '',
            '',
            $startsAt,
            null,
            false,
            'none',
            1
        );
        $this->eventIds[] = $ids[0];

        return $service->findEvent($ids[0]);
    }

    public function testIndexListsUpcomingEvent(): void
    {
        $calendar = $this->createCalendar();
        $author = $this->createUser();
        $event = $this->createEvent((int) $calendar['id'], (int) $author['id'], '+1 day');

        $controller = new CalendarApiController($this->app);
        $response = $controller->index($this->makeRequest('GET', '/api/v1/calendar', ['per_page' => '100']));
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $ids = array_map('intval', array_column($body['data'], 'id'));
        $this->assertContains((int) $event['id'], $ids);
    }

    public function testIndexExcludesPastEvent(): void
    {
        $calendar = $this->createCalendar();
        $author = $this->createUser();
        // listUpcomingEvents()'s cutoff is "now minus 1 day" (a deliberately
        // generous window so an event that started earlier today still
        // shows as upcoming) — needs to be well past that, not just "in the
        // past", to actually land outside it.
        $pastEvent = $this->createEvent((int) $calendar['id'], (int) $author['id'], '-3 days');

        $controller = new CalendarApiController($this->app);
        $response = $controller->index($this->makeRequest('GET', '/api/v1/calendar', ['per_page' => '100']));
        $body = json_decode($response->body(), true);

        $ids = array_map('intval', array_column($body['data'], 'id'));
        $this->assertNotContains((int) $pastEvent['id'], $ids);
    }

    public function testShowReturnsEvent(): void
    {
        $calendar = $this->createCalendar();
        $author = $this->createUser();
        $event = $this->createEvent((int) $calendar['id'], (int) $author['id'], '+2 days');

        $controller = new CalendarApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/calendar/' . $event['id']);
        $request->setRouteParams(['id' => (string) $event['id']]);

        $response = $controller->show($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertSame((int) $event['id'], (int) $body['data']['id']);
        $this->assertSame($event['title'], $body['data']['title']);
    }

    public function testShowReturns404ForUnknownId(): void
    {
        $controller = new CalendarApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/calendar/999999999');
        $request->setRouteParams(['id' => '999999999']);

        $response = $controller->show($request);

        $this->assertSame(404, $response->status());
    }
}
