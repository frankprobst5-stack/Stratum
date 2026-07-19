<?php

declare(strict_types=1);

namespace Stratum\Modules\Moderation;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Users\AuthService;

final class ModerationAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('moderation.manage')) !== null) {
            return $guard;
        }

        $service = new ModerationService($this->app->db);
        $authors = new AuthService($this->app->db);
        $usernames = [];

        $decorate = function (array $report) use ($authors, &$usernames): array {
            foreach (['reporter_id' => 'reporterName', 'resolved_by' => 'resolverName'] as $column => $key) {
                $userId = $report[$column] !== null ? (int) $report[$column] : null;
                if ($userId === null) {
                    $report[$key] = null;
                    continue;
                }

                if (!array_key_exists($userId, $usernames)) {
                    $user = $authors->findById($userId);
                    $usernames[$userId] = $user['username'] ?? 'Unknown';
                }

                $report[$key] = $usernames[$userId];
            }

            return $report;
        };

        $content = $this->app->templates->render('moderation', 'admin-index', [
            'open' => array_map($decorate, $service->listOpen()),
            'closed' => array_map($decorate, $service->listClosed()),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function resolve(Request $request): Response
    {
        return $this->closeReport($request, 'resolved');
    }

    public function dismiss(Request $request): Response
    {
        return $this->closeReport($request, 'dismissed');
    }

    private function closeReport(Request $request, string $status): Response
    {
        if (($guard = $this->guard('moderation.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new ModerationService($this->app->db);
        $report = $service->find((int) $request->param('id', '0'));
        if ($report === null) {
            return Response::notFound();
        }

        $moderator = $this->app->auth->user();
        $note = trim((string) $request->input('note', ''));

        $closed = $service->close((int) $report['id'], $status, (int) $moderator['id'], $note);

        if ($closed) {
            // Tell the reporter their report was handled. Self-reports the
            // moderator handles themselves are dropped by the notifications
            // listener's recipient === actor skip rule — no check needed here.
            $this->app->notify([
                'user_id' => (int) $report['reporter_id'],
                'actor_id' => (int) $moderator['id'],
                'type' => 'report_' . $status,
                'message' => 'Your report on ' . $report['content_title'] . ' was ' . $status,
                'url' => $report['content_url'],
            ]);
        }

        return Response::redirect('/admin/moderation');
    }
}
