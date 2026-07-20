<?php

declare(strict_types=1);

namespace Stratum\Api;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Calendar\CalendarService;

final class CalendarApiController extends ApiController
{
    /** Public — no auth required, same access model the web /calendar route already has. */
    public function index(Request $request): Response
    {
        $pagination = $this->paginationParams($request);
        $all = (new CalendarService($this->app->db))->listUpcomingEvents();

        return ApiResponse::paginated(
            array_slice($all, $pagination['offset'], $pagination['perPage']),
            $pagination['page'],
            $pagination['perPage'],
            count($all)
        );
    }

    public function show(Request $request): Response
    {
        $event = (new CalendarService($this->app->db))->findEvent((int) $request->param('id', '0'));
        if ($event === null) {
            return ApiResponse::notFound();
        }

        return ApiResponse::data($event);
    }
}
