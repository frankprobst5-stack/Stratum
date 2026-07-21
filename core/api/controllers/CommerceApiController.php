<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Commerce\CommerceService;

/**
 * Reads only — deliberately, confirmed 2026-07-20. Commerce touches real
 * money (Cash App, manual admin confirm), so initiating a purchase stays
 * web-only through the existing flow rather than opening a new payment
 * code path through the API. See docs/roadmap.md's Stage 10 Seventh Slice.
 */
final class CommerceApiController extends ApiController
{
    /** Public — no auth required, same access model the web /commerce route already has. */
    public function index(Request $request): Response
    {
        $pagination = $this->paginationParams($request);
        $all = (new CommerceService($this->app->db))->listProducts();

        return ApiResponse::paginated(
            array_slice($all, $pagination['offset'], $pagination['perPage']),
            $pagination['page'],
            $pagination['perPage'],
            count($all)
        );
    }

    public function show(Request $request): Response
    {
        $commerce = new CommerceService($this->app->db);
        $product = $commerce->findProduct((int) $request->param('id', '0'));
        if ($product === null) {
            return ApiResponse::notFound();
        }

        $hasPurchased = null;
        if ($this->app->auth->check()) {
            $hasPurchased = $commerce->hasPurchased((int) $this->app->auth->user()['id'], (int) $product['id']);
        }

        return ApiResponse::data($product + ['hasPurchased' => $hasPurchased]);
    }
}
