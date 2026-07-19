<?php

declare(strict_types=1);

namespace Stratum\Modules\Users;

use Stratum\Core\CardBlock;
use Stratum\Core\ConfigurableBlock;

final class NewestMembersBlock implements ConfigurableBlock, CardBlock
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function configFields(): array
    {
        return [
            ['name' => 'limit', 'label' => 'Number of members to show', 'type' => 'number', 'default' => 8],
        ];
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $limit = isset($config['limit']) ? max(1, (int) $config['limit']) : 8;
        $members = $this->auth->listNewest($limit);
        if ($members === []) {
            return '';
        }

        $items = '';
        foreach ($members as $member) {
            $items .= '<li style="display:inline-block;margin:0.25rem;">'
                . '<a href="' . e(route('/members/' . $member['username'])) . '" style="text-decoration:none;color:inherit;font-size:0.85rem;">' . e($member['username']) . '</a>'
                . '</li>';
        }

        return '<ul class="strat-newest-members" style="list-style:none;margin:0;padding:0;">' . $items . '</ul>';
    }

    public function cardTitle(): string
    {
        return 'Newest Members';
    }

    public function cardIcon(): string
    {
        return "\u{1F195}";
    }

    public function cardAccent(): string
    {
        return 'cyan';
    }

    public function viewAllUrl(): ?string
    {
        return '/online';
    }
}
