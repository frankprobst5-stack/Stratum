<?php

declare(strict_types=1);

namespace Stratum\Modules\Chat;

use Stratum\Core\ConfigurableBlock;

/**
 * Confirmed 2026-07-19: public rooms only, admin- and member-created
 * alike — matches ChatService::listPublicRooms()'s own visibility rule
 * exactly, no separate filtering needed here.
 */
final class AvailableRoomsBlock implements ConfigurableBlock
{
    public function __construct(private readonly ChatService $chat)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function configFields(): array
    {
        return [
            ['name' => 'limit', 'label' => 'Number of rooms to show', 'type' => 'number', 'default' => 5],
        ];
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $limit = isset($config['limit']) ? max(1, (int) $config['limit']) : 5;
        $rooms = $this->chat->listPublicRooms($limit);
        if ($rooms === []) {
            return '';
        }

        $items = '';
        foreach ($rooms as $room) {
            $items .= '<li style="margin-bottom:0.4rem;">'
                . '<a href="' . e(route('/chat/rooms/' . $room['id'])) . '">' . e($room['name']) . '</a>'
                . ' <small style="color:#888;">(' . (int) $room['member_count'] . ')</small>'
                . '</li>';
        }

        return '<div class="strat-chat-rooms-block">'
            . '<strong>Chat Rooms</strong>'
            . '<ul style="list-style:none; padding:0; margin:0.5rem 0 0;">' . $items . '</ul>'
            . '<a href="' . e(route('/chat')) . '" style="font-size:0.85rem;">View all &rarr;</a>'
            . '</div>';
    }
}
