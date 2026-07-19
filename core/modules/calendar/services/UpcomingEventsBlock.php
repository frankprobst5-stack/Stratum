<?php

declare(strict_types=1);

namespace Stratum\Modules\Calendar;

use Stratum\Core\CardBlock;
use Stratum\Core\ConfigurableBlock;

final class UpcomingEventsBlock implements ConfigurableBlock, CardBlock
{
    public function __construct(private readonly CalendarService $calendar)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function configFields(): array
    {
        return [
            ['name' => 'limit', 'label' => 'Number of events to show', 'type' => 'number', 'default' => 5],
        ];
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $limit = isset($config['limit']) ? max(1, (int) $config['limit']) : 5;
        $events = array_slice($this->calendar->listUpcomingEvents(null, $limit), 0, $limit);
        if ($events === []) {
            return '';
        }

        $items = '';
        foreach ($events as $event) {
            $items .= '<li style="padding:0.35rem 0;border-bottom:1px solid var(--strat-card-border);font-size:0.85rem;">'
                . '<a href="' . e(route('/calendar/events/' . $event['id'])) . '" style="text-decoration:none;color:inherit;font-weight:600;">' . e($event['title']) . '</a>'
                . '<div style="color:var(--strat-muted-text);font-size:0.75rem;">' . e((string) $event['starts_at']) . '</div>'
                . '</li>';
        }

        return '<ul class="strat-upcoming-events" style="list-style:none;margin:0;padding:0;">' . $items . '</ul>';
    }

    public function cardTitle(): string
    {
        return 'Upcoming Events';
    }

    public function cardIcon(): string
    {
        return "\u{1F4C5}";
    }

    public function cardAccent(): string
    {
        return 'purple';
    }

    public function viewAllUrl(): ?string
    {
        return '/calendar';
    }
}
