<?php

declare(strict_types=1);

namespace Stratum\Modules\Moderation;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class ModerationController
{
    public function __construct(private readonly App $app)
    {
    }

    public function showCreate(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->auth->can('moderation.report')) {
            return Response::forbidden();
        }

        $type = (string) $request->query('type', '');
        $id = (int) $request->query('id', '0');

        $target = (new ModerationService($this->app->db))->resolveTarget($type, $id);
        if ($target === null) {
            return Response::notFound();
        }

        $content = $this->app->templates->render('moderation', 'create', [
            'type' => $type,
            'id' => $id,
            'target' => $target,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->auth->can('moderation.report')) {
            return Response::forbidden();
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $type = (string) $request->input('reportable_type', '');
        $id = (int) $request->input('reportable_id', '0');
        $reason = trim((string) $request->input('reason', ''));

        $service = new ModerationService($this->app->db);

        // Title/URL come from the server-side resolver, never the client —
        // a report row is something an admin will click, so a spoofable URL
        // is not acceptable (same posture as notifications' producer-built
        // URLs). This also rejects unknown types and deleted content.
        $target = $service->resolveTarget($type, $id);
        if ($target === null) {
            return Response::notFound();
        }

        if ($reason === '') {
            return Response::redirect('/reports/new?type=' . rawurlencode($type) . '&id=' . $id);
        }

        $reporterId = (int) $this->app->auth->user()['id'];

        if (!$service->hasOpenReport($type, $id, $reporterId)) {
            $service->create($type, $id, $reporterId, $reason, $target['title'], $target['url']);
        }

        return Response::redirect($target['url']);
    }
}
