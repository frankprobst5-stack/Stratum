<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Users\BadgeService;

/** Badge definitions live here (reuses `users.manage` — badges are a member-system concern, not worth a dedicated capability); awarding/revoking a badge to a specific member lives on UsersController's member-detail page instead, alongside staff notes. */
final class BadgesController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('users.manage')) !== null) {
            return $guard;
        }

        $content = $this->app->templates->render('admin', 'badges', [
            'badges' => (new BadgeService($this->app->db))->listBadges(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->guard('users.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            (new BadgeService($this->app->db))->createBadge(
                $name,
                (string) $request->input('description', ''),
                (string) $request->input('icon_url', '')
            );
        }

        return Response::redirect('/admin/badges');
    }
}
