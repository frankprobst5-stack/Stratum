<?php

declare(strict_types=1);

namespace Stratum\Modules\CustomBlocks;

use Stratum\Core\BBCodeParser;
use Stratum\Core\ConfigurableBlock;

/**
 * The safe-for-any-editor escape hatch — BBCode, not Markdown, to match
 * every other rich-text field in this app (articles, forum posts, wiki)
 * rather than introducing a second markup dialect and a new dependency.
 * Escaped-then-allow-listed via the shared BBCodeParser, so unlike
 * HtmlBlock this one can't emit arbitrary tags.
 */
final class TextBlock implements ConfigurableBlock
{
    public function __construct(private readonly BBCodeParser $bbcode)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function configFields(): array
    {
        return [
            ['name' => 'text', 'label' => 'Text (supports [b] [i] [u] [url] [quote] [code])', 'type' => 'textarea', 'default' => ''],
        ];
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $text = (string) ($config['text'] ?? '');
        if ($text === '') {
            return '';
        }

        return '<div class="strat-text-block">' . nl2br($this->bbcode->render($text)) . '</div>';
    }
}
