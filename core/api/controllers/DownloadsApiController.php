<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Downloads\DownloadService;

final class DownloadsApiController extends ApiController
{
    /** Public — no auth required, same access model the web /downloads route already has. */
    public function index(Request $request): Response
    {
        $pagination = $this->paginationParams($request);
        // listRecent() takes a hard SQL LIMIT rather than returning
        // everything (unlike ArticleService::listPublished()) — a large
        // ceiling here, then the same in-PHP slice every other index()
        // action already uses, keeps this endpoint's pagination behavior
        // identical to articles/forum/calendar's.
        $all = (new DownloadService($this->app->db, $this->storageDir()))->listRecent(100000);

        return ApiResponse::paginated(
            array_slice($all, $pagination['offset'], $pagination['perPage']),
            $pagination['page'],
            $pagination['perPage'],
            count($all)
        );
    }

    public function show(Request $request): Response
    {
        $service = new DownloadService($this->app->db, $this->storageDir());
        $file = $service->findFile((int) $request->param('id', '0'));
        if ($file === null) {
            return ApiResponse::notFound();
        }

        return ApiResponse::data($file + [
            'currentVersion' => $service->currentVersion((int) $file['id']),
            'mirrors' => $service->listMirrors((int) $file['id']),
        ]);
    }

    private function storageDir(): string
    {
        return $this->app->rootDir . '/storage/uploads/downloads';
    }
}
