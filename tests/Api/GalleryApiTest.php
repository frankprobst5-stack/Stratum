<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\GalleryApiController;
use Stratum\Modules\Gallery\GalleryService;
use Tests\TestCase;

final class GalleryApiTest extends TestCase
{
    /** @var int[] album ids created by this test, cleaned up (cascades to photos) in tearDown() */
    private array $albumIds = [];

    protected function tearDown(): void
    {
        foreach ($this->albumIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('gallery_photos') . ' WHERE album_id = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('gallery_albums') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->albumIds = [];

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function createAlbum(string $title): array
    {
        $now = date('Y-m-d H:i:s');
        $albumId = (int) $this->db->insert('gallery_albums', [
            'title' => $title,
            'description' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->albumIds[] = $albumId;

        return (new GalleryService($this->db, '/tmp'))->findAlbum($albumId);
    }

    /**
     * Inserted directly rather than through GalleryService::addPhotos() —
     * that method calls move_uploaded_file() against a real $_FILES
     * tmp_name, which only succeeds inside a genuine HTTP upload request,
     * not a CLI test process.
     */
    private function createPhoto(int $albumId, int $uploaderId): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('gallery_photos', [
            'album_id' => $albumId,
            'uploader_id' => $uploaderId,
            'caption' => 'test caption',
            'filename' => '2026/07/test.jpg',
            'thumbnail_filename' => '2026/07/test_thumb.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 123,
            'width' => 100,
            'height' => 100,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function testAlbumsListsAlbum(): void
    {
        $album = $this->createAlbum('API test album ' . bin2hex(random_bytes(4)));

        $controller = new GalleryApiController($this->app);
        $response = $controller->albums($this->makeRequest('GET', '/api/v1/gallery/albums', ['per_page' => '100']));
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $ids = array_map('intval', array_column($body['data'], 'id'));
        $this->assertContains($album['id'], $ids);
    }

    public function testPhotosReturnsAlbumWithItsPhotos(): void
    {
        $album = $this->createAlbum('API test album with photos ' . bin2hex(random_bytes(4)));
        $author = $this->createUser();
        $this->createPhoto((int) $album['id'], (int) $author['id']);
        $this->createPhoto((int) $album['id'], (int) $author['id']);

        $controller = new GalleryApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/gallery/albums/' . $album['id'] . '/photos');
        $request->setRouteParams(['id' => (string) $album['id']]);

        $response = $controller->photos($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertSame($album['title'], $body['data']['title']);
        $this->assertCount(2, $body['data']['photos']);
    }

    public function testPhotosReturns404ForUnknownAlbum(): void
    {
        $controller = new GalleryApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/gallery/albums/999999999/photos');
        $request->setRouteParams(['id' => '999999999']);

        $response = $controller->photos($request);

        $this->assertSame(404, $response->status());
    }
}
