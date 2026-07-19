<?php

declare(strict_types=1);

namespace Stratum\Modules\CustomBlocks;

use Stratum\Core\ConfigurableBlock;

/**
 * The deliberate "escape hatch" for anything the curated block library
 * doesn't cover — raw markup straight from the placement's `config_json`,
 * no parsing, no sanitization. Safe in the same sense addon/theme uploads
 * already are (see docs/roadmap.md's "Addons & Themes" entry): whoever can
 * set a placement's config is already at admin/DB trust level (the
 * `/admin/blocks` form, gated by `blocks.manage`), same as an addon
 * upload — this isn't a new trust boundary. Deliberately NOT a PHP/SQL
 * execution block (see the Stage 8 block-library cut list) — markup only.
 */
final class HtmlBlock implements ConfigurableBlock
{
    /** @return array<int, array<string, mixed>> */
    public function configFields(): array
    {
        return [
            ['name' => 'html', 'label' => 'Raw HTML', 'type' => 'textarea', 'default' => ''],
        ];
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        return raw((string) ($config['html'] ?? ''));
    }
}
