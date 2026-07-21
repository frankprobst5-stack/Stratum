<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\RatingsApiController;
use Stratum\Modules\Articles\ArticleService;
use Tests\TestCase;

final class RatingsApiTest extends TestCase
{
    /** @var int[] article ids created by this test, cleaned up in tearDown() */
    private array $articleIds = [];

    protected function tearDown(): void
    {
        foreach ($this->articleIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('ratings') . ' WHERE ratable_type = :type AND ratable_id = :id', ['type' => 'article', 'id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('articles_revisions') . ' WHERE article_id = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('articles') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->articleIds = [];

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function createArticle(): array
    {
        $author = $this->createUser();
        $service = new ArticleService($this->db);
        $result = $service->create([
            'category_id' => null,
            'author_id' => (int) $author['id'],
            'title' => 'API test rating article ' . bin2hex(random_bytes(4)),
            'excerpt' => '',
            'body' => 'body',
            'publish_action' => 'now',
        ]);
        $this->articleIds[] = (int) $result['articleId'];

        return $service->find((int) $result['articleId']);
    }

    public function testShowReturnsZeroSummaryWithNoRatings(): void
    {
        $article = $this->createArticle();

        $controller = new RatingsApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/ratings/article/' . $article['id']);
        $request->setRouteParams(['type' => 'article', 'id' => (string) $article['id']]);

        $response = $controller->show($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        // PHP's json_encode() collapses a whole-number float (0.0) to bare
        // "0" in the JSON text, so it decodes back as an int, not a float —
        // a JSON round-trip quirk, not an API bug. assertEquals (loose) is
        // correct here; assertSame would be testing PHP's JSON encoder, not
        // this endpoint.
        $this->assertEquals(0.0, $body['data']['average']);
        $this->assertSame(0, $body['data']['count']);
        $this->assertNull($body['data']['myRating']);
    }

    public function testShowRejectsUnknownType(): void
    {
        $controller = new RatingsApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/ratings/bogus/1');
        $request->setRouteParams(['type' => 'bogus', 'id' => '1']);

        $response = $controller->show($request);

        $this->assertSame(422, $response->status());
    }

    public function testRateRequiresAuthentication(): void
    {
        $article = $this->createArticle();

        $controller = new RatingsApiController($this->app);
        $request = $this->makeRequest('POST', '/api/v1/ratings/article/' . $article['id'], body: ['score' => '5']);
        $request->setRouteParams(['type' => 'article', 'id' => (string) $article['id']]);

        $response = $controller->rate($request);

        $this->assertSame(401, $response->status());
    }

    public function testRateForbiddenWithoutCapability(): void
    {
        $article = $this->createArticle();
        $rater = $this->createUser();
        $app = $this->asUser($rater);

        $controller = new RatingsApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/ratings/article/' . $article['id'], body: ['score' => '5']);
        $request->setRouteParams(['type' => 'article', 'id' => (string) $article['id']]);

        $response = $controller->rate($request);

        $this->assertSame(403, $response->status());
    }

    public function testRateSucceedsWithCapabilityAndUpdatesSummary(): void
    {
        $article = $this->createArticle();
        $rater = $this->createUser();
        $this->grantCapability((int) $rater['id'], 'ratings.create');
        $app = $this->asUser($rater);

        $controller = new RatingsApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/ratings/article/' . $article['id'], body: ['score' => '4']);
        $request->setRouteParams(['type' => 'article', 'id' => (string) $article['id']]);

        $response = $controller->rate($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertEquals(4.0, $body['data']['average']);
        $this->assertSame(1, $body['data']['count']);
        $this->assertSame(4, $body['data']['myRating']);
    }

    public function testRateRejectsOutOfRangeScore(): void
    {
        $article = $this->createArticle();
        $rater = $this->createUser();
        $this->grantCapability((int) $rater['id'], 'ratings.create');
        $app = $this->asUser($rater);

        $controller = new RatingsApiController($app);
        $request = $this->makeRequest('POST', '/api/v1/ratings/article/' . $article['id'], body: ['score' => '9']);
        $request->setRouteParams(['type' => 'article', 'id' => (string) $article['id']]);

        $response = $controller->rate($request);

        $this->assertSame(422, $response->status());
    }
}
