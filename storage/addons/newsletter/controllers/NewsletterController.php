<?php

declare(strict_types=1);

namespace Stratum\Modules\Newsletter;

use Stratum\Core\App;
use Stratum\Core\BBCodeParser;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class NewsletterController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = new NewsletterService($this->app->db);

        $content = $this->app->templates->render('newsletter', 'index', [
            'issues' => $service->listIssues(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    /** /newsletter/{slug} always redirects to page 1 — there's no separate "issue overview," the TOC lives in the sidebar of every page. */
    public function issueRedirect(Request $request): Response
    {
        $service = new NewsletterService($this->app->db);
        $issue = $this->publishedIssueBySlug($service, (string) $request->param('slug', ''));
        if ($issue === null) {
            return Response::notFound();
        }

        return Response::redirect('/newsletter/' . $issue['slug'] . '/1');
    }

    public function page(Request $request): Response
    {
        $service = new NewsletterService($this->app->db);
        $issue = $this->publishedIssueBySlug($service, (string) $request->param('slug', ''));
        if ($issue === null) {
            return Response::notFound();
        }

        $position = (int) $request->param('position', '0');
        $page = $service->pageAtPosition((int) $issue['id'], $position);
        if ($page === null) {
            return Response::notFound();
        }

        $pages = $service->listPages((int) $issue['id']);
        $bbcode = new BBCodeParser();

        $content = $this->app->templates->render('newsletter', 'issue', [
            'issue' => $issue,
            'page' => $page,
            'renderedBody' => $bbcode->render($page['body']),
            'toc' => $pages,
            'position' => $position,
            'pageCount' => count($pages),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    /** @return array<string, mixed>|null a published issue by slug — unpublished issues are never reachable by a guest, same 404 either way as a nonexistent slug. */
    private function publishedIssueBySlug(NewsletterService $service, string $slug): ?array
    {
        $issue = $service->findIssueBySlug($slug);
        if ($issue === null || !$issue['is_published']) {
            return null;
        }

        return $issue;
    }
}
