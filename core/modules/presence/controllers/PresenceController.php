<?php

declare(strict_types=1);

namespace Stratum\Modules\Presence;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class PresenceController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = new PresenceService($this->app->db);

        $content = $this->app->templates->render('presence', 'index', [
            'members' => $service->onlineMembers(),
            'guestCount' => $service->guestCount(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }
}
