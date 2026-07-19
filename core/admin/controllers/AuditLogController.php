<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\AuditLogService;
use Stratum\Core\Request;
use Stratum\Core\Response;

/**
 * Gated on `roles.manage`, not the broader `admin.access` — this page
 * is specifically oversight of *other* admins' activity, the same
 * "trusted enough to see the full picture" tier the Permissions Audit
 * view already uses, not general admin-panel access.
 */
final class AuditLogController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('roles.manage')) !== null) {
            return $guard;
        }

        $service = new AuditLogService($this->app->db);
        $page = max(1, (int) $request->query('page', '1'));
        $total = $service->count();

        $content = $this->app->templates->render('admin', 'audit-log', [
            'entries' => $service->list($page),
            'page' => $page,
            'totalPages' => max(1, (int) ceil($total / $service->perPage())),
            'total' => $total,
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }
}
