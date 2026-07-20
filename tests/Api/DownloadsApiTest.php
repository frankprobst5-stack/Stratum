<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\DownloadsApiController;
use Stratum\Modules\Downloads\DownloadService;
use Tests\TestCase;

final class DownloadsApiTest extends TestCase
{
    /** @var int[] category ids created by this test, cleaned up (cascades to files/versions) in tearDown() */
    private array $categoryIds = [];

    protected function tearDown(): void
    {
        foreach ($this->categoryIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('downloads_versions') . '
                WHERE file_id IN (SELECT id FROM ' . $this->db->table('downloads_files') . ' WHERE category_id = :id)', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('downloads_files') . ' WHERE category_id = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('downloads_categories') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->categoryIds = [];

        parent::tearDown();
    }

    private function createCategory(): int
    {
        $service = new DownloadService($this->db, '/tmp');
        $name = 'API test downloads category ' . bin2hex(random_bytes(4));
        $service->createCategory($name);

        $match = array_values(array_filter(
            $service->listCategories(),
            static fn (array $c): bool => $c['name'] === $name
        ));
        $id = (int) $match[0]['id'];
        $this->categoryIds[] = $id;

        return $id;
    }

    /**
     * Inserted directly rather than through DownloadService::createFile() —
     * that method calls move_uploaded_file() against a real $_FILES tmp_name,
     * which only ever succeeds inside a genuine HTTP upload request, not a
     * CLI test process (is_uploaded_file() always fails outside one).
     *
     * @return array<string, mixed>
     */
    private function createFile(int $categoryId, int $uploaderId, string $title): array
    {
        $now = date('Y-m-d H:i:s');
        $fileId = (int) $this->db->insert('downloads_files', [
            'category_id' => $categoryId,
            'title' => $title,
            'description' => null,
            'download_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->db->insert('downloads_versions', [
            'file_id' => $fileId,
            'uploader_id' => $uploaderId,
            'filename' => '2026/07/test.txt',
            'original_name' => 'test.txt',
            'mime_type' => 'text/plain',
            'size' => 123,
            'version_number' => 1,
            'created_at' => $now,
        ]);

        return (new DownloadService($this->db, '/tmp'))->findFile($fileId);
    }

    public function testIndexListsFile(): void
    {
        $categoryId = $this->createCategory();
        $author = $this->createUser();
        $file = $this->createFile($categoryId, (int) $author['id'], 'API test file ' . bin2hex(random_bytes(4)));

        $controller = new DownloadsApiController($this->app);
        $response = $controller->index($this->makeRequest('GET', '/api/v1/downloads', ['per_page' => '100']));
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $ids = array_map('intval', array_column($body['data'], 'id'));
        $this->assertContains($file['id'], $ids);
    }

    public function testShowReturnsFileWithCurrentVersionAndMirrors(): void
    {
        $categoryId = $this->createCategory();
        $author = $this->createUser();
        $file = $this->createFile($categoryId, (int) $author['id'], 'API test show file ' . bin2hex(random_bytes(4)));

        $controller = new DownloadsApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/downloads/' . $file['id']);
        $request->setRouteParams(['id' => (string) $file['id']]);

        $response = $controller->show($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertSame($file['title'], $body['data']['title']);
        $this->assertSame('test.txt', $body['data']['currentVersion']['original_name']);
        $this->assertSame([], $body['data']['mirrors']);
    }

    public function testShowReturns404ForUnknownId(): void
    {
        $controller = new DownloadsApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/downloads/999999999');
        $request->setRouteParams(['id' => '999999999']);

        $response = $controller->show($request);

        $this->assertSame(404, $response->status());
    }
}
