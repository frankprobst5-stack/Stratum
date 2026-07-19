<?php

declare(strict_types=1);

namespace Stratum\Modules\Downloads;

use Stratum\Core\CardBlock;
use Stratum\Core\ConfigurableBlock;

final class RecentDownloadsBlock implements ConfigurableBlock, CardBlock
{
    public function __construct(private readonly DownloadService $downloads)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function configFields(): array
    {
        return [
            ['name' => 'limit', 'label' => 'Number of files to show', 'type' => 'number', 'default' => 5],
        ];
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $limit = isset($config['limit']) ? max(1, (int) $config['limit']) : 5;
        $files = $this->downloads->listRecent($limit);
        if ($files === []) {
            return '';
        }

        $items = '';
        foreach ($files as $file) {
            $items .= '<li style="padding:0.35rem 0;border-bottom:1px solid var(--strat-card-border);font-size:0.85rem;">'
                . '<a href="' . e(route('/downloads/files/' . $file['id'])) . '" style="text-decoration:none;color:inherit;font-weight:600;">' . e($file['title']) . '</a>'
                . '<div style="color:var(--strat-muted-text);font-size:0.75rem;">' . (int) $file['download_count'] . ' downloads</div>'
                . '</li>';
        }

        return '<ul class="strat-recent-downloads" style="list-style:none;margin:0;padding:0;">' . $items . '</ul>';
    }

    public function cardTitle(): string
    {
        return 'Downloads';
    }

    public function cardIcon(): string
    {
        return "\u{2B07}\u{FE0F}";
    }

    public function cardAccent(): string
    {
        return 'orange';
    }

    public function viewAllUrl(): ?string
    {
        return '/downloads';
    }
}
