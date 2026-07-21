<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Tags\TagService;

final class TagsApiController extends ApiController
{
    /** Public — no auth required, same access model the web /tags route already has. */
    public function index(Request $request): Response
    {
        $pagination = $this->paginationParams($request);
        $all = (new TagService($this->app->db))->popularTags(100000);

        return ApiResponse::paginated(
            array_slice($all, $pagination['offset'], $pagination['perPage']),
            $pagination['page'],
            $pagination['perPage'],
            count($all)
        );
    }

    public function show(Request $request): Response
    {
        $tags = new TagService($this->app->db);
        $tag = $tags->findBySlug((string) $request->param('slug', ''));
        if ($tag === null) {
            return ApiResponse::notFound();
        }

        return ApiResponse::data($tag + ['content' => $tags->contentForTag((int) $tag['id'])]);
    }
}
