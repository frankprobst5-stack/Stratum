<?php

declare(strict_types=1);

namespace Stratum\Modules\CustomBlocks;

use Stratum\Core\CardBlock;
use Stratum\Core\ConfigurableBlock;

/**
 * Small enough that it was genuinely a toss-up whether this needed its
 * own block class or could just be a `custom.text` instance — decided at
 * build time (per the Stage 8 design note's own "decide later" flag) in
 * favor of a dedicated block: a CTA button with its own href is a
 * different shape than a text blob, not just styling.
 *
 * cardTitle() returns the admin-configured headline itself (2026-07-19
 * design-system pass) — this block used to render its own `<h3>` inline;
 * now BlockRegistry's shared card-header does that job (matching
 * look.png's "Join our community" card, which has the same icon-badge
 * treatment every other card gets), so render() only needs the
 * description + button.
 */
final class WelcomeCtaBlock implements ConfigurableBlock, CardBlock
{
    private string $headline = 'Join our community';

    /** @return array<int, array<string, mixed>> */
    public function configFields(): array
    {
        return [
            ['name' => 'headline', 'label' => 'Headline', 'type' => 'text', 'default' => 'Join our community'],
            ['name' => 'text', 'label' => 'Body text', 'type' => 'textarea', 'default' => ''],
            ['name' => 'button_label', 'label' => 'Button label', 'type' => 'text', 'default' => 'Join Now'],
            ['name' => 'button_url', 'label' => 'Button link (e.g. /register)', 'type' => 'text', 'default' => '/register'],
        ];
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $this->headline = (string) ($config['headline'] ?? 'Join our community');
        $text = (string) ($config['text'] ?? '');
        $buttonLabel = (string) ($config['button_label'] ?? 'Join Now');
        $buttonUrl = (string) ($config['button_url'] ?? '/register');

        return '<div class="strat-welcome-cta" style="text-align:center;padding:0.5rem 0;">'
            . ($text !== '' ? '<p style="color:var(--strat-muted-text);font-size:0.9rem;">' . e($text) . '</p>' : '')
            . '<a href="' . e(route($buttonUrl)) . '" style="display:inline-block;background:var(--strat-accent);color:#fff;padding:0.5rem 1.25rem;border-radius:6px;text-decoration:none;font-weight:600;">' . e($buttonLabel) . '</a>'
            . '</div>';
    }

    public function cardTitle(): string
    {
        return $this->headline;
    }

    public function cardIcon(): string
    {
        return "\u{1F465}";
    }

    public function cardAccent(): string
    {
        return 'blue';
    }

    public function viewAllUrl(): ?string
    {
        return null;
    }
}
