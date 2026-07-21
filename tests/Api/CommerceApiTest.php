<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\CommerceApiController;
use Stratum\Modules\Commerce\CommerceService;
use Tests\TestCase;

final class CommerceApiTest extends TestCase
{
    /** @var int[] product ids created by this test, cleaned up in tearDown() */
    private array $productIds = [];
    /** @var int[] downloads_files ids the fixture products reference, cleaned up in tearDown() */
    private array $fileIds = [];
    /** @var int[] downloads_categories ids created by this test, cleaned up in tearDown() */
    private array $categoryIds = [];

    protected function tearDown(): void
    {
        foreach ($this->productIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('commerce_purchases') . ' WHERE product_id = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('commerce_products') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->productIds = [];

        foreach ($this->fileIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('downloads_versions') . ' WHERE file_id = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('downloads_files') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->fileIds = [];

        foreach ($this->categoryIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('downloads_categories') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->categoryIds = [];

        parent::tearDown();
    }

    /** @return array<string, mixed> a real commerce product, backed by a real downloads file (same direct-insert fixture pattern DownloadsApiTest uses, since the upload path needs a genuine HTTP request) */
    private function createProduct(): array
    {
        $now = date('Y-m-d H:i:s');
        $categoryId = (int) $this->db->insert('downloads_categories', [
            'name' => 'API test commerce category ' . bin2hex(random_bytes(4)),
            'slug' => 'api-test-commerce-category-' . bin2hex(random_bytes(4)),
            'weight' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->categoryIds[] = $categoryId;

        $fileId = (int) $this->db->insert('downloads_files', [
            'category_id' => $categoryId,
            'title' => 'API test commerce file ' . bin2hex(random_bytes(4)),
            'description' => null,
            'download_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->fileIds[] = $fileId;
        $this->db->insert('downloads_versions', [
            'file_id' => $fileId,
            'uploader_id' => null,
            'filename' => '2026/07/test.txt',
            'original_name' => 'test.txt',
            'mime_type' => 'text/plain',
            'size' => 10,
            'version_number' => 1,
            'created_at' => $now,
        ]);

        $commerce = new CommerceService($this->db);
        $commerce->createProduct($fileId, '9.99', 'https://cash.app/$testtag/9.99');

        $product = $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('commerce_products') . ' WHERE download_file_id = :id',
            ['id' => $fileId]
        );
        $this->productIds[] = (int) $product['id'];

        return $product;
    }

    public function testIndexListsProduct(): void
    {
        $product = $this->createProduct();

        $controller = new CommerceApiController($this->app);
        $response = $controller->index($this->makeRequest('GET', '/api/v1/commerce/products', ['per_page' => '1000']));
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $ids = array_map('intval', array_column($body['data'], 'id'));
        $this->assertContains((int) $product['id'], $ids);
    }

    public function testShowReturnsNullHasPurchasedForGuest(): void
    {
        $product = $this->createProduct();

        $controller = new CommerceApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/commerce/products/' . $product['id']);
        $request->setRouteParams(['id' => (string) $product['id']]);

        $response = $controller->show($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertNull($body['data']['hasPurchased']);
    }

    public function testShowReturnsFalseHasPurchasedForAuthenticatedNonBuyer(): void
    {
        $product = $this->createProduct();
        $user = $this->createUser();
        $app = $this->asUser($user);

        $controller = new CommerceApiController($app);
        $request = $this->makeRequest('GET', '/api/v1/commerce/products/' . $product['id']);
        $request->setRouteParams(['id' => (string) $product['id']]);

        $response = $controller->show($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertFalse($body['data']['hasPurchased']);
    }

    public function testShowReturns404ForUnknownId(): void
    {
        $controller = new CommerceApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/commerce/products/999999999');
        $request->setRouteParams(['id' => '999999999']);

        $response = $controller->show($request);

        $this->assertSame(404, $response->status());
    }
}
