<?php

declare(strict_types=1);

namespace Stratum\Modules\Ticker;

use Stratum\Core\Block;

final class TickerBlock implements Block
{
    private const LEVEL_COLORS = [
        'info' => '#2f6fed',
        'warning' => '#c98a12',
        'urgent' => '#c8362b',
    ];

    public function __construct(private readonly TickerService $service)
    {
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $messages = $this->service->listActive();
        if ($messages === []) {
            return '';
        }

        $items = '';
        foreach ($messages as $i => $message) {
            $color = self::LEVEL_COLORS[$message['level']] ?? self::LEVEL_COLORS['info'];
            $text = e($message['message']);
            $inner = $message['url'] !== null && $message['url'] !== ''
                ? '<a href="' . e($message['url']) . '" style="color:inherit;text-decoration:underline;">' . $text . '</a>'
                : $text;

            $items .= '<div class="strat-ticker-item" style="border-left:4px solid ' . $color . ';padding-left:0.75rem;color:#f0f1f5;display:' . ($i === 0 ? 'block' : 'none') . ';">' . $inner . '</div>';
        }

        $rotator = count($messages) > 1 ? <<<'JS'
            <script>
            (function () {
                var items = document.querySelectorAll('.strat-ticker-item');
                var current = 0;
                if (items.length < 2) return;
                setInterval(function () {
                    items[current].style.display = 'none';
                    current = (current + 1) % items.length;
                    items[current].style.display = 'block';
                }, 6000);
            })();
            </script>
            JS
            : '';

        return '<div class="strat-ticker" style="padding:0.5rem 1rem;background:#12141c;">' . $items . '</div>' . $rotator;
    }
}
