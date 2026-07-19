<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * RSS "auto articles" — optionally auto-publishing incoming feed items as
 * real site articles, rather than only ever showing in this module's own
 * aggregator list. `article_author_id` is captured once, when an admin
 * turns auto-publish on for a source, rather than resolved live at fetch
 * time — RssFetcher::fetchAndStore() already runs from `cron.daily` with
 * no logged-in admin in scope (see rss_aggregator's own Module.php), so
 * there is no "current user" to attribute a cron-created article to.
 * `rss_items.article_id` tracks which article (if any) a given item
 * already produced, both to avoid double-publishing on a re-fetch and to
 * let the admin list link straight to it.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $users = $db->table('users');

        $db->execute('
            ALTER TABLE ' . $db->table('rss_sources') . '
            ADD COLUMN auto_publish TINYINT(1) NOT NULL DEFAULT 0 AFTER is_enabled,
            ADD COLUMN article_author_id BIGINT UNSIGNED NULL AFTER auto_publish,
            ADD CONSTRAINT fk_rss_sources_article_author FOREIGN KEY (article_author_id)
                REFERENCES ' . $users . ' (id) ON DELETE SET NULL
        ');

        $db->execute('
            ALTER TABLE ' . $db->table('rss_items') . '
            ADD COLUMN article_id BIGINT UNSIGNED NULL AFTER published_at,
            ADD CONSTRAINT fk_rss_items_article FOREIGN KEY (article_id)
                REFERENCES ' . $db->table('articles') . ' (id) ON DELETE SET NULL
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('ALTER TABLE ' . $db->table('rss_items') . ' DROP FOREIGN KEY fk_rss_items_article, DROP COLUMN article_id');
        $db->execute('ALTER TABLE ' . $db->table('rss_sources') . ' DROP FOREIGN KEY fk_rss_sources_article_author, DROP COLUMN article_author_id, DROP COLUMN auto_publish');
    }
};
