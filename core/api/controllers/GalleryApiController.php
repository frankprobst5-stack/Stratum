<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Gallery\GalleryService;

final class GalleryApiController extends ApiController
{
    /** Public — no auth required, same access model the web /gallery route already has. */
    public function albums(Request $request): Response
    {
        $pagination = $this->paginationParams($request);
        $all = (new GalleryService($this->app->db, $this->storageDir()))->listAlbums();

        return ApiResponse::paginated(
            array_slice($all, $pagination['offset'], $pagination['perPage']),
            $pagination['page'],
            $pagination['perPage'],
            count($all)
        );
    }

    public function photos(Request $request): Response
    {
        $gallery = new GalleryService($this->app->db, $this->storageDir());
        $album = $gallery->findAlbum((int) $request->param('id', '0'));
        if ($album === null) {
            return ApiResponse::notFound();
        }

        return ApiResponse::data($album + ['photos' => $gallery->listPhotos((int) $album['id'])]);
    }

    private function storageDir(): string
    {
        return $this->app->rootDir . '/storage/uploads/gallery';
    }
}
