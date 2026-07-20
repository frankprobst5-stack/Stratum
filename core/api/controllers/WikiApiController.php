<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Wiki\WikiService;

final class WikiApiController extends ApiController
{
    /** Public — no auth required, same access model the web /wiki route already has. */
    public function index(Request $request): Response
    {
        $pagination = $this->paginationParams($request);
        $all = (new WikiService($this->app->db))->listPages();

        return ApiResponse::paginated(
            array_slice($all, $pagination['offset'], $pagination['perPage']),
            $pagination['page'],
            $pagination['perPage'],
            count($all)
        );
    }

    public function show(Request $request): Response
    {
        $wiki = new WikiService($this->app->db);
        $page = $wiki->findPageBySlug((string) $request->param('slug', ''));
        if ($page === null) {
            return ApiResponse::notFound();
        }

        $revision = $wiki->currentRevision((int) $page['id']);

        return ApiResponse::data($page + ['body' => $revision['body'] ?? '']);
    }
}
