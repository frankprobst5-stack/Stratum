<?php

declare(strict_types=1);

namespace Stratum\Modules\Activity;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Users\AuthService;

final class ActivityController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $items = (new ActivityService($this->app->db, $this->app->modules))->recent();

        $authors = new AuthService($this->app->db);
        $usernames = [];
        foreach ($items as &$item) {
            if ($item['actor_id'] === null) {
                $item['actor'] = null;
                continue;
            }

            if (!array_key_exists($item['actor_id'], $usernames)) {
                $user = $authors->findById($item['actor_id']);
                $usernames[$item['actor_id']] = $user['username'] ?? 'Unknown';
            }

            $item['actor'] = $usernames[$item['actor_id']];
        }
        unset($item);

        $content = $this->app->templates->render('activity', 'index', [
            'items' => $items,
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }
}
