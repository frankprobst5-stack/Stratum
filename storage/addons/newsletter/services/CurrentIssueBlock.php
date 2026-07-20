<?php

declare(strict_types=1);

namespace Stratum\Modules\Newsletter;

use Stratum\Core\CardBlock;

final class CurrentIssueBlock implements CardBlock
{
    public function __construct(private readonly NewsletterService $newsletter)
    {
    }

    /** @param array<string, mixed> $config */
    public function render(array $config): string
    {
        $issue = $this->newsletter->latestPublishedIssue();
        if ($issue === null) {
            return '';
        }

        return '<div style="font-weight:600;">' . e($issue['title']) . '</div>'
            . '<div style="color:var(--strat-muted-text);font-size:0.85rem;">' . e($issue['published_at']) . '</div>';
    }

    public function cardTitle(): string
    {
        return 'Current Issue';
    }

    public function cardIcon(): string
    {
        return "\u{1F4F0}";
    }

    public function cardAccent(): string
    {
        return 'purple';
    }

    public function viewAllUrl(): ?string
    {
        $issue = $this->newsletter->latestPublishedIssue();

        return $issue !== null ? '/newsletter/' . $issue['slug'] : null;
    }
}
