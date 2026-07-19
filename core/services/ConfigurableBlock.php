<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Optional companion to `Block` — a block implements this when it takes
 * real settings, so the admin placement UI can generate a proper form
 * instead of asking an admin to hand-type JSON into a textarea (the
 * actual gap identified 2026-07-18: "nobody should need to know JSON
 * syntax to pick a category"). A block with no config (most of them —
 * `ActivityFeedBlock`, `WhosOnlineBlock`, etc.) just implements `Block`
 * alone and gets no settings form at all, which is correct for them.
 *
 * `configFields()` is called on an already-constructed block instance,
 * so a block needing dynamic options (e.g. `LatestContentBlock` listing
 * real article categories) can just query its own already-injected
 * service — no separate "dynamic vs static options" mechanism needed.
 */
interface ConfigurableBlock extends Block
{
    /**
     * @return array<int, array{
     *     name: string,
     *     label: string,
     *     type: 'text'|'number'|'textarea'|'select',
     *     options?: array<string, string>,
     *     default?: string|int
     * }>
     */
    public function configFields(): array;
}
