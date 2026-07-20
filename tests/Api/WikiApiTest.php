<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\WikiApiController;
use Stratum\Modules\Wiki\WikiService;
use Tests\TestCase;

final class WikiApiTest extends TestCase
{
    /** @var int[] page ids created by this test, cleaned up in tearDown() */
    private array $pageIds = [];

    protected function tearDown(): void
    {
        foreach ($this->pageIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('wiki_revisions') . ' WHERE page_id = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('wiki_pages') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->pageIds = [];

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function createPage(int $authorId, string $title, string $body): array
    {
        $service = new WikiService($this->db);
        $ids = $service->createPage(null, $authorId, $title, $body);
        $this->pageIds[] = $ids['pageId'];

        return $service->findPage($ids['pageId']);
    }

    public function testIndexListsPage(): void
    {
        $author = $this->createUser();
        $page = $this->createPage((int) $author['id'], 'API test wiki page ' . bin2hex(random_bytes(4)), 'body text');

        $controller = new WikiApiController($this->app);
        $response = $controller->index($this->makeRequest('GET', '/api/v1/wiki', ['per_page' => '100']));
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $slugs = array_column($body['data'], 'slug');
        $this->assertContains($page['slug'], $slugs);
    }

    public function testShowReturnsPageWithCurrentBody(): void
    {
        $author = $this->createUser();
        $page = $this->createPage((int) $author['id'], 'API test wiki show ' . bin2hex(random_bytes(4)), 'the real body');

        $controller = new WikiApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/wiki/' . $page['slug']);
        $request->setRouteParams(['slug' => $page['slug']]);

        $response = $controller->show($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertSame($page['slug'], $body['data']['slug']);
        $this->assertSame('the real body', $body['data']['body']);
    }

    public function testShowReturns404ForUnknownSlug(): void
    {
        $controller = new WikiApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/wiki/does-not-exist');
        $request->setRouteParams(['slug' => 'does-not-exist']);

        $response = $controller->show($request);

        $this->assertSame(404, $response->status());
    }
}
