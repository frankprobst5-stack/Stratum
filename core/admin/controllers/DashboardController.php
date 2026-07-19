<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\AdminNoteService;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Core\TrashService;
use Stratum\Modules\Activity\ActivityService;
use Stratum\Modules\Presence\PresenceService;
use Stratum\Modules\Users\AuthService;

final class DashboardController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('admin.access')) !== null) {
            return $guard;
        }

        $modules = $this->app->modules;
        $db = $this->app->db;

        // Every panel below is gated the same way: only shown if its owning
        // module is enabled (its service class is only ever loaded then —
        // ModuleManager::boot() already ran by the time this executes, so
        // no manual require is needed) AND the viewing admin actually holds
        // the relevant capability. A capable-but-disabled combination just
        // means the panel doesn't render — the dashboard degrades
        // gracefully rather than erroring on a missing module.
        $recentActivity = $modules->isEnabled('activity')
            ? $this->resolveActorNames(array_slice((new ActivityService($db, $modules))->recent(), 0, 6))
            : [];

        $openReports = ($modules->isEnabled('moderation') && $this->app->auth->can('moderation.manage'))
            ? $this->openReportCount()
            : null;

        $trashCount = $this->app->auth->can('trash.manage')
            ? count((new TrashService($db, $modules))->listTrashed())
            : null;

        $presence = $modules->isEnabled('presence')
            ? (new PresenceService($db))->onlineMembers()
            : null;
        $guestCount = $modules->isEnabled('presence') ? (new PresenceService($db))->guestCount() : 0;

        $moduleList = $modules->list();

        $adminNotes = $this->resolveNoteAuthors((new AdminNoteService($db))->listRecent());

        $content = $this->app->templates->render('admin', 'dashboard', [
            'currentUser' => $this->app->auth->user(),
            'moduleCount' => count($moduleList),
            'enabledModuleCount' => count(array_filter($moduleList, static fn (array $m): bool => $m['enabled'])),
            'recentActivity' => $recentActivity,
            'openReports' => $openReports,
            'trashCount' => $trashCount,
            'onlineMembers' => $presence,
            'guestCount' => $guestCount,
            'adminNotes' => $adminNotes,
            'phpVersion' => PHP_VERSION,
            'mysqlVersion' => (string) $db->pdo()->getAttribute(\PDO::ATTR_SERVER_VERSION),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    /**
     * Admin-to-admin scratchpad — see AdminNoteService/the `admin_notes`
     * migration for why this is distinct from member notes. Lives on the
     * dashboard itself (one more panel, per the roadmap's own "not a
     * separate screen" call), not a dedicated controller.
     */
    public function addNote(Request $request): Response
    {
        if (($guard = $this->guard('admin.access')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $body = trim((string) $request->input('body', ''));
        if ($body !== '') {
            $user = $this->app->auth->user();
            (new AdminNoteService($this->app->db))->add((int) $user['id'], $body);
        }

        return Response::redirect('/admin');
    }

    public function deleteNote(Request $request): Response
    {
        if (($guard = $this->guard('admin.access')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new AdminNoteService($this->app->db))->delete((int) $request->param('id', '0'));

        return Response::redirect('/admin');
    }

    /**
     * @param array<int, array{id: int, body: string, author_id: ?int, created_at: string}> $notes
     * @return array<int, array<string, mixed>>
     */
    private function resolveNoteAuthors(array $notes): array
    {
        $authors = new AuthService($this->app->db);

        return array_map(function (array $note) use ($authors): array {
            $author = $note['author_id'] !== null ? $authors->findById($note['author_id']) : null;

            return $note + ['authorName' => $author['username'] ?? 'Unknown'];
        }, $notes);
    }

    private function openReportCount(): int
    {
        $table = $this->app->db->table('moderation_reports');
        $row = $this->app->db->fetchOne("SELECT COUNT(*) AS c FROM {$table} WHERE status = 'open'");

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Same controller-side actor_id -> username resolution as
     * ActivityController::index() — ActivityService deliberately leaves
     * actor names unjoined (see its class doc comment), so every consumer
     * resolves them itself via AuthService::findById().
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function resolveActorNames(array $items): array
    {
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

        return $items;
    }
}
