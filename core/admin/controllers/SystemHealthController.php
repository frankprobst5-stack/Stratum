<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Core\SystemHealthService;

final class SystemHealthController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('admin.access')) !== null) {
            return $guard;
        }

        $health = new SystemHealthService($this->app->db, $this->app->rootDir);

        $content = $this->app->templates->render('admin', 'system-health', [
            'checks' => $health->checks(),
            'diskSpace' => $health->diskSpace(),
            'phpLimits' => $health->phpLimits(),
            'lastCronRun' => $health->lastCronRun(),
            'recentErrorCount' => $health->recentErrorCount(),
            'phpVersion' => PHP_VERSION,
            'mysqlVersion' => (string) $this->app->db->pdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }
}
