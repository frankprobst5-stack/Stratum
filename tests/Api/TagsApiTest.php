<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\TagsApiController;
use Stratum\Modules\Articles\ArticleService;
use Stratum\Modules\Tags\TagService;
use Tests\TestCase;

final class TagsApiTest extends TestCase
{
    /** @var int[] article ids created by this test, cleaned up in tearDown() */
    private array $articleIds = [];
    /** @var string[] tag names created by this test, cleaned up in tearDown() */
    private array $tagNames = [];

    protected function tearDown(): void
    {
        foreach ($this->tagNames as $name) {
            $tag = $this->db->fetchOne('SELECT id FROM ' . $this->db->table('tags') . ' WHERE name = :name', ['name' => $name]);
            if ($tag !== null) {
                $this->db->execute('DELETE FROM ' . $this->db->table('taggables') . ' WHERE tag_id = :id', ['id' => $tag['id']]);
                $this->db->execute('DELETE FROM ' . $this->db->table('tags') . ' WHERE id = :id', ['id' => $tag['id']]);
            }
        }
        $this->tagNames = [];

        foreach ($this->articleIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('articles_revisions') . ' WHERE article_id = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('articles') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->articleIds = [];

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function createTaggedArticle(string $tagName): array
    {
        $author = $this->createUser();
        $service = new ArticleService($this->db);
        $result = $service->create([
            'category_id' => null,
            'author_id' => (int) $author['id'],
            'title' => 'API test tag article ' . bin2hex(random_bytes(4)),
            'excerpt' => '',
            'body' => 'body',
            'publish_action' => 'now',
        ]);
        $this->articleIds[] = (int) $result['articleId'];

        (new TagService($this->db))->setTags('article', (int) $result['articleId'], $tagName);
        $this->tagNames[] = $tagName;

        return $service->find((int) $result['articleId']);
    }

    public function testIndexListsTag(): void
    {
        $tagName = 'api-test-tag-' . bin2hex(random_bytes(4));
        $this->createTaggedArticle($tagName);

        $controller = new TagsApiController($this->app);
        $response = $controller->index($this->makeRequest('GET', '/api/v1/tags', ['per_page' => '1000']));
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $names = array_column($body['data'], 'name');
        $this->assertContains($tagName, $names);
    }

    public function testShowReturnsTagWithTaggedContent(): void
    {
        $tagName = 'api-test-tag-' . bin2hex(random_bytes(4));
        $article = $this->createTaggedArticle($tagName);

        // Look up the real persisted slug rather than assuming it equals the
        // name — TagService::uniqueSlug() runs the name through Slug::make(),
        // a private implementation detail this test shouldn't have to guess.
        $tagRow = $this->db->fetchOne('SELECT slug FROM ' . $this->db->table('tags') . ' WHERE name = :name', ['name' => $tagName]);

        $controller = new TagsApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/tags/' . $tagRow['slug']);
        $request->setRouteParams(['slug' => $tagRow['slug']]);

        $response = $controller->show($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertSame($tagName, $body['data']['name']);
        $titles = array_column($body['data']['content'], 'title');
        $this->assertContains($article['title'], $titles);
    }

    public function testShowReturns404ForUnknownSlug(): void
    {
        $controller = new TagsApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/tags/does-not-exist');
        $request->setRouteParams(['slug' => 'does-not-exist']);

        $response = $controller->show($request);

        $this->assertSame(404, $response->status());
    }
}
