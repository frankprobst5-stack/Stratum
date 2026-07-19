<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Resolves a (type, id) pair — the same commentable_type/commentable_id
 * shape `comments` established — to a display title and local URL, or null
 * if the type is unknown or the content doesn't exist / is soft-deleted /
 * unpublished. Promoted here (not left inside the moderation module) once
 * bookmarks became its second real consumer — same "promote on 2nd/3rd
 * consumer" rule that already promoted Slug, BBCodeParser,
 * FileUploadValidator, and ImageThumbnailer.
 *
 * Deliberately does NOT check ModuleManager::isEnabled() for the owning
 * module — if a module is disabled after content was reported/bookmarked,
 * the row survives and its resolved link simply 404s when followed, same
 * posture notifications already takes with deleted content.
 */
final class ContentResolver
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return ?array{title: string, url: string} */
    public function resolve(string $type, int $id): ?array
    {
        return match ($type) {
            'article' => $this->article($id),
            'wiki_page' => $this->wikiPage($id),
            'forum_topic' => $this->forumTopic($id),
            'forum_post' => $this->forumPost($id),
            default => null,
        };
    }

    private function article(int $id): ?array
    {
        // Matches ArticleService::PUBLISHED_CONDITION exactly — without the
        // OR clause, bookmarking/reporting a just-published scheduled
        // article would fail until cron.daily next flips is_published,
        // even though the article is already live on its own page.
        $row = $this->db->fetchOne(
            'SELECT title, slug FROM ' . $this->db->table('articles') . '
             WHERE id = :id AND deleted_at IS NULL AND (is_published = 1 OR (published_at IS NOT NULL AND published_at <= NOW()))',
            ['id' => $id]
        );

        return $row === null ? null : ['title' => $row['title'], 'url' => '/articles/' . $row['slug']];
    }

    private function wikiPage(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT title, slug FROM ' . $this->db->table('wiki_pages') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );

        return $row === null ? null : ['title' => $row['title'], 'url' => '/wiki/' . $row['slug']];
    }

    private function forumTopic(int $id): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT title FROM ' . $this->db->table('forum_topics') . ' WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );

        return $row === null ? null : ['title' => $row['title'], 'url' => '/forum/topics/' . $id];
    }

    private function forumPost(int $id): ?array
    {
        $posts = $this->db->table('forum_posts');
        $topics = $this->db->table('forum_topics');

        $row = $this->db->fetchOne(
            "SELECT ft.id AS topic_id, ft.title AS title
             FROM {$posts} fp
             INNER JOIN {$topics} ft ON ft.id = fp.topic_id
             WHERE fp.id = :id AND fp.deleted_at IS NULL AND ft.deleted_at IS NULL",
            ['id' => $id]
        );

        if ($row === null) {
            return null;
        }

        return ['title' => 'Post in "' . $row['title'] . '"', 'url' => '/forum/topics/' . $row['topic_id']];
    }
}
