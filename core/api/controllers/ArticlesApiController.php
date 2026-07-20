<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Articles\ArticleService;

final class ArticlesApiController extends ApiController
{
    /** Public — no auth required, same access model the web /articles route already has. */
    public function index(Request $request): Response
    {
        $pagination = $this->paginationParams($request);
        $all = (new ArticleService($this->app->db))->listPublished();

        return ApiResponse::paginated(
            array_slice($all, $pagination['offset'], $pagination['perPage']),
            $pagination['page'],
            $pagination['perPage'],
            count($all)
        );
    }

    public function show(Request $request): Response
    {
        $article = (new ArticleService($this->app->db))->findPublishedBySlug((string) $request->param('slug', ''));
        if ($article === null) {
            return ApiResponse::notFound();
        }

        return ApiResponse::data($article);
    }
}
