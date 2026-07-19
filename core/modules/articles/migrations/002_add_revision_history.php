<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * True append-only revision history, same "compute, don't cache" shape
 * wiki's revisions already proved (Stage 3c) — articles.body goes away
 * entirely; "current" is simply the latest row in articles_revisions, not
 * a cached column. Every existing article's current body becomes its
 * first revision (comment "Initial version, migrated") so no history is
 * lost — this is a real data migration, not just a schema change.
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $articles = $db->table('articles');

        $db->execute('
            CREATE TABLE ' . $db->table('articles_revisions') . ' (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                article_id BIGINT UNSIGNED NOT NULL,
                author_id BIGINT UNSIGNED NOT NULL,
                body TEXT NOT NULL,
                comment VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                KEY idx_article_revision_article (article_id, created_at),
                CONSTRAINT fk_article_revisions_article FOREIGN KEY (article_id)
                    REFERENCES ' . $articles . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $existing = $db->fetchAll("SELECT id, author_id, body, created_at FROM {$articles}");
        foreach ($existing as $article) {
            $db->insert('articles_revisions', [
                'article_id' => $article['id'],
                'author_id' => $article['author_id'],
                'body' => $article['body'],
                'comment' => 'Initial version, migrated',
                'created_at' => $article['created_at'],
            ]);
        }

        $db->execute("ALTER TABLE {$articles} DROP COLUMN body");
    }

    public function down(Database $db): void
    {
        $articles = $db->table('articles');

        $db->execute("ALTER TABLE {$articles} ADD COLUMN body TEXT NULL AFTER excerpt");

        // Best-effort: restore each article's latest revision back onto the column.
        $revisions = $db->table('articles_revisions');
        $latest = $db->fetchAll("
            SELECT r.article_id, r.body FROM {$revisions} r
            INNER JOIN (
                SELECT article_id, MAX(id) AS max_id FROM {$revisions} GROUP BY article_id
            ) m ON m.max_id = r.id
        ");
        foreach ($latest as $row) {
            $db->execute(
                "UPDATE {$articles} SET body = :body WHERE id = :id",
                ['body' => $row['body'], 'id' => $row['article_id']]
            );
        }

        $db->execute("ALTER TABLE {$articles} MODIFY COLUMN body TEXT NOT NULL");
        $db->execute('DROP TABLE IF EXISTS ' . $revisions);
    }
};
