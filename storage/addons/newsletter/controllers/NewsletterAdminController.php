<?php

declare(strict_types=1);

namespace Stratum\Modules\Newsletter;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class NewsletterAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('newsletter.edit_issue')) !== null) {
            return $guard;
        }

        $service = new NewsletterService($this->app->db);

        $content = $this->app->templates->render('newsletter', 'admin-index', [
            'issues' => $service->listIssues(false),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createIssue(Request $request): Response
    {
        if (($guard = $this->guard('newsletter.edit_issue')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            return Response::redirect('/admin/newsletter');
        }

        $service = new NewsletterService($this->app->db);
        $issueId = $service->createIssue($title);

        return Response::redirect('/admin/newsletter/' . $issueId . '/pages');
    }

    public function togglePublish(Request $request): Response
    {
        if (($guard = $this->guard('newsletter.publish')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new NewsletterService($this->app->db);
        $issue = $service->findIssue((int) $request->param('id', '0'));
        if ($issue !== null) {
            $service->setIssuePublished((int) $issue['id'], !$issue['is_published']);
        }

        return Response::redirect('/admin/newsletter');
    }

    public function pages(Request $request): Response
    {
        if (($guard = $this->guard('newsletter.edit_issue')) !== null) {
            return $guard;
        }

        $service = new NewsletterService($this->app->db);
        $issue = $service->findIssue((int) $request->param('id', '0'));
        if ($issue === null) {
            return Response::notFound();
        }

        $content = $this->app->templates->render('newsletter', 'admin-pages', [
            'issue' => $issue,
            'pages' => $service->listPages((int) $issue['id']),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function addPage(Request $request): Response
    {
        if (($guard = $this->guard('newsletter.edit_issue')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new NewsletterService($this->app->db);
        $issueId = (int) $request->param('id', '0');
        if ($service->findIssue($issueId) === null) {
            return Response::notFound();
        }

        $title = trim((string) $request->input('title', ''));
        $body = (string) $request->input('body', '');
        if ($title === '') {
            return Response::redirect('/admin/newsletter/' . $issueId . '/pages');
        }

        $service->addPage($issueId, $title, $body);

        return Response::redirect('/admin/newsletter/' . $issueId . '/pages');
    }

    public function updatePage(Request $request): Response
    {
        if (($guard = $this->guard('newsletter.edit_issue')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new NewsletterService($this->app->db);
        $page = $service->findPage((int) $request->param('pageId', '0'));
        if ($page === null) {
            return Response::notFound();
        }

        $title = trim((string) $request->input('title', ''));
        if ($title !== '') {
            $service->updatePage((int) $page['id'], $title, (string) $request->input('body', ''));
        }

        return Response::redirect('/admin/newsletter/' . $page['issue_id'] . '/pages');
    }

    public function deletePage(Request $request): Response
    {
        if (($guard = $this->guard('newsletter.edit_issue')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new NewsletterService($this->app->db);
        $page = $service->findPage((int) $request->param('pageId', '0'));
        if ($page === null) {
            return Response::notFound();
        }

        $issueId = (int) $page['issue_id'];
        $service->deletePage((int) $page['id']);

        return Response::redirect('/admin/newsletter/' . $issueId . '/pages');
    }

    public function movePageUp(Request $request): Response
    {
        return $this->movePage($request, up: true);
    }

    public function movePageDown(Request $request): Response
    {
        return $this->movePage($request, up: false);
    }

    private function movePage(Request $request, bool $up): Response
    {
        if (($guard = $this->guard('newsletter.edit_issue')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new NewsletterService($this->app->db);
        $page = $service->findPage((int) $request->param('pageId', '0'));
        if ($page === null) {
            return Response::notFound();
        }

        $issueId = (int) $page['issue_id'];
        $up ? $service->movePageUp((int) $page['id']) : $service->movePageDown((int) $page['id']);

        return Response::redirect('/admin/newsletter/' . $issueId . '/pages');
    }
}
