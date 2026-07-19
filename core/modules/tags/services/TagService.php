<?php

declare(strict_types=1);

namespace Stratum\Modules\Tags;

use Stratum\Core\ContentResolver;
use Stratum\Core\Database;
use Stratum\Core\Slug;

/**
 * Cross-module tagging over whichever content types ContentResolver
 * already knows how to resolve to a title/URL (article, wiki_page,
 * forum_topic, forum_post as of this writing) — the same "one shared
 * resolver, extend it when a new consumer needs a new type" precedent
 * Bookmarks and Moderation already established, not a parallel type
 * registry invented here.
 *
 * Tag names are stored lowercase (`normalize()`) so "PHP" and "php"
 * collapse to one tag rather than silently fragmenting discovery across
 * near-duplicates — a real tagging-system footgun this avoids by
 * construction rather than by admin diligence.
 */
final class TagService
{
    private const MAX_TAGS_PER_ITEM = 15;
    private const MAX_TAG_LENGTH = 60;

    private readonly ContentResolver $resolver;

    public function __construct(private readonly Database $db)
    {
        $this->resolver = new ContentResolver($db);
    }

    /**
     * Replaces the full tag set for one piece of content — parses a
     * comma-separated string (the plain-text-input UX every wired-in
     * form uses), normalizes each name, creates any tag that doesn't
     * exist yet, and removes associations for anything no longer in
     * the list. Idempotent: calling this with the same input twice
     * leaves the same end state, no duplicate rows.
     */
    public function setTags(string $type, int $id, string $rawCsv): void
    {
        $names = $this->parseNames($rawCsv);
        $tagIds = array_map(fn (string $name): int => $this->findOrCreateTag($name), $names);

        $table = $this->db->table('taggables');
        $existing = $this->db->fetchAll(
            "SELECT id, tag_id FROM {$table} WHERE taggable_type = :type AND taggable_id = :id",
            ['type' => $type, 'id' => $id]
        );

        foreach ($existing as $row) {
            if (!in_array((int) $row['tag_id'], $tagIds, true)) {
                $this->db->execute("DELETE FROM {$table} WHERE id = :id", ['id' => $row['id']]);
            }
        }

        $existingTagIds = array_map(static fn (array $r): int => (int) $r['tag_id'], $existing);
        foreach ($tagIds as $tagId) {
            if (!in_array($tagId, $existingTagIds, true)) {
                $this->db->insert('taggables', [
                    'tag_id' => $tagId,
                    'taggable_type' => $type,
                    'taggable_id' => $id,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /** @return array<int, array{name: string, slug: string}> */
    public function tagsFor(string $type, int $id): array
    {
        $taggables = $this->db->table('taggables');
        $tags = $this->db->table('tags');

        $rows = $this->db->fetchAll(
            "SELECT t.name, t.slug FROM {$taggables} tg
             INNER JOIN {$tags} t ON t.id = tg.tag_id
             WHERE tg.taggable_type = :type AND tg.taggable_id = :id
             ORDER BY t.name",
            ['type' => $type, 'id' => $id]
        );

        return array_map(static fn (array $r): array => ['name' => $r['name'], 'slug' => $r['slug']], $rows);
    }

    /** Plain comma-joined string for pre-filling an edit form's text input. */
    public function tagsForAsCsv(string $type, int $id): string
    {
        return implode(', ', array_column($this->tagsFor($type, $id), 'name'));
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('tags') . ' WHERE slug = :slug',
            ['slug' => $slug]
        );
    }

    /**
     * Resolves every live piece of content tagged with $tagId, same
     * "silently drop anything that no longer resolves" posture
     * BookmarkService::listForUser() already established — a tag on
     * deleted/unpublished content just doesn't show up, not a broken link.
     *
     * @return array<int, array{type: string, title: string, url: string}>
     */
    public function contentForTag(int $tagId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT taggable_type, taggable_id FROM ' . $this->db->table('taggables') . '
             WHERE tag_id = :tag_id ORDER BY created_at DESC',
            ['tag_id' => $tagId]
        );

        $results = [];
        foreach ($rows as $row) {
            $target = $this->resolver->resolve($row['taggable_type'], (int) $row['taggable_id']);
            if ($target === null) {
                continue;
            }

            $results[] = ['type' => $row['taggable_type'], 'title' => $target['title'], 'url' => $target['url']];
        }

        return $results;
    }

    /** @return array<int, array{id: int, name: string, slug: string, count: int}> tags actually in use, most-used first */
    public function popularTags(int $limit = 100): array
    {
        $tags = $this->db->table('tags');
        $taggables = $this->db->table('taggables');

        $rows = $this->db->fetchAll(
            "SELECT t.id, t.name, t.slug, COUNT(*) AS c
             FROM {$tags} t
             INNER JOIN {$taggables} tg ON tg.tag_id = t.id
             GROUP BY t.id, t.name, t.slug
             ORDER BY c DESC, t.name ASC
             LIMIT " . max(1, $limit)
        );

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => $r['name'],
            'slug' => $r['slug'],
            'count' => (int) $r['c'],
        ], $rows);
    }

    /** @return array<int, string> normalized, deduped, length- and count-capped tag names */
    private function parseNames(string $rawCsv): array
    {
        $names = [];
        foreach (explode(',', $rawCsv) as $part) {
            $name = $this->normalize($part);
            if ($name === '' || in_array($name, $names, true)) {
                continue;
            }

            $names[] = $name;
            if (count($names) >= self::MAX_TAGS_PER_ITEM) {
                break;
            }
        }

        return $names;
    }

    private function normalize(string $raw): string
    {
        $name = mb_strtolower(trim(preg_replace('/\s+/', ' ', $raw) ?? $raw));

        return mb_substr($name, 0, self::MAX_TAG_LENGTH);
    }

    private function findOrCreateTag(string $name): int
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('tags') . ' WHERE name = :name',
            ['name' => $name]
        );

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return (int) $this->db->insert('tags', [
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Slug::make($name, 'tag');
        $slug = $base;
        $suffix = 2;

        while ($this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('tags') . ' WHERE slug = :slug',
            ['slug' => $slug]
        ) !== null) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
