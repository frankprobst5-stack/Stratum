<?php

declare(strict_types=1);

namespace Stratum\Modules\Wiki;

use Stratum\Core\App;
use Stratum\Core\BBCodeParser;
use Stratum\Core\Request;
use Stratum\Core\ReputationService;
use Stratum\Core\Response;
use Stratum\Core\SeoService;
use Stratum\Modules\Bookmarks\BookmarkService;
use Stratum\Modules\Comments\CommentService;
use Stratum\Modules\Tags\TagService;
use Stratum\Modules\Users\AuthService;

final class WikiController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $wiki = new WikiService($this->app->db);
        $categories = [];
        foreach ($wiki->listCategories() as $category) {
            $categories[$category['id']] = $category['name'];
        }

        $pages = array_map(
            static fn (array $p): array => $p + ['categoryName' => $categories[$p['category_id']] ?? null],
            $wiki->listPages()
        );

        $content = $this->app->templates->render('wiki', 'index', ['pages' => $pages]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function show(Request $request): Response
    {
        $wiki = new WikiService($this->app->db);
        $page = $wiki->findPageBySlug((string) $request->param('slug', ''));
        if ($page === null) {
            return Response::notFound();
        }

        $revision = $wiki->currentRevision((int) $page['id']);
        $authors = new AuthService($this->app->db);
        $comments = new CommentService($this->app->db);
        $bbcode = new BBCodeParser();

        $commentRows = array_map(
            fn (array $c): array => $c + ['authorName' => $this->authorName($authors, (int) $c['user_id'])],
            $comments->listFor('wiki_page', (int) $page['id'])
        );

        $showBookmark = $this->app->modules->isEnabled('bookmarks') && $this->app->auth->check();
        $isBookmarked = $showBookmark
            && (new BookmarkService($this->app->db))->isBookmarked('wiki_page', (int) $page['id'], (int) $this->app->auth->user()['id']);

        $tags = $this->app->modules->isEnabled('tags')
            ? (new TagService($this->app->db))->tagsFor('wiki_page', (int) $page['id'])
            : [];

        $content = $this->app->templates->render('wiki', 'show', [
            'page' => $page,
            'revision' => $revision,
            'renderedBody' => $revision !== null ? $bbcode->render($revision['body']) : '',
            'authorName' => $revision !== null ? $this->authorName($authors, (int) $revision['author_id']) : '',
            'tags' => $tags,
            'comments' => $commentRows,
            'canComment' => $this->app->auth->can('comments.create'),
            'canEdit' => $this->app->auth->can('wiki.edit'),
            'isLoggedIn' => $this->app->auth->check(),
            'showBookmark' => $showBookmark,
            'isBookmarked' => $isBookmarked,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        $seo = [
            'title' => $page['title'],
            'description' => (new SeoService())->excerpt((string) ($revision['body'] ?? '')),
        ];

        return Response::html($this->app->renderPage($content, $request, $seo));
    }

    public function showCreate(Request $request): Response
    {
        if (($guard = $this->requireCapability('wiki.edit')) !== null) {
            return $guard;
        }

        $wiki = new WikiService($this->app->db);

        $content = $this->app->templates->render('wiki', 'form', [
            'page' => null,
            'body' => '',
            'tags' => '',
            'showTags' => $this->app->modules->isEnabled('tags'),
            'categories' => $wiki->listCategories(),
            'csrfToken' => $this->app->session->csrfToken(),
            'formAction' => '/wiki/create',
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->requireCapability('wiki.edit')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $title = trim((string) $request->input('title', ''));
        $body = trim((string) $request->input('body', ''));
        if ($title === '' || $body === '') {
            return Response::redirect('/wiki/create');
        }

        $categoryId = (int) $request->input('category_id', '0') ?: null;
        $user = $this->app->auth->user();
        $wiki = new WikiService($this->app->db);
        $ids = $wiki->createPage($categoryId, (int) $user['id'], $title, $body);
        $page = $wiki->findPage($ids['pageId']);
        (new ReputationService($this->app))->award((int) $user['id'], 2);

        if ($this->app->modules->isEnabled('tags')) {
            (new TagService($this->app->db))->setTags('wiki_page', $ids['pageId'], (string) $request->input('tags', ''));
        }

        return Response::redirect('/wiki/' . $page['slug']);
    }

    public function showEdit(Request $request): Response
    {
        if (($guard = $this->requireCapability('wiki.edit')) !== null) {
            return $guard;
        }

        $wiki = new WikiService($this->app->db);
        $page = $wiki->findPageBySlug((string) $request->param('slug', ''));
        if ($page === null) {
            return Response::notFound();
        }

        $revision = $wiki->currentRevision((int) $page['id']);
        $showTags = $this->app->modules->isEnabled('tags');

        $content = $this->app->templates->render('wiki', 'form', [
            'page' => $page,
            'body' => $revision['body'] ?? '',
            'tags' => $showTags ? (new TagService($this->app->db))->tagsForAsCsv('wiki_page', (int) $page['id']) : '',
            'showTags' => $showTags,
            'categories' => $wiki->listCategories(),
            'csrfToken' => $this->app->session->csrfToken(),
            'formAction' => '/wiki/' . $page['slug'] . '/edit',
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function update(Request $request): Response
    {
        if (($guard = $this->requireCapability('wiki.edit')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $wiki = new WikiService($this->app->db);
        $page = $wiki->findPageBySlug((string) $request->param('slug', ''));
        if ($page === null) {
            return Response::notFound();
        }

        $body = trim((string) $request->input('body', ''));
        if ($body !== '') {
            $user = $this->app->auth->user();
            $comment = trim((string) $request->input('comment', ''));
            $wiki->addRevision((int) $page['id'], (int) $user['id'], $body, $comment);
            (new ReputationService($this->app))->award((int) $user['id'], 1);
        }

        if ($this->app->modules->isEnabled('tags')) {
            (new TagService($this->app->db))->setTags('wiki_page', (int) $page['id'], (string) $request->input('tags', ''));
        }

        return Response::redirect('/wiki/' . $page['slug']);
    }

    public function history(Request $request): Response
    {
        $wiki = new WikiService($this->app->db);
        $page = $wiki->findPageBySlug((string) $request->param('slug', ''));
        if ($page === null) {
            return Response::notFound();
        }

        $authors = new AuthService($this->app->db);
        $revisions = array_map(
            fn (array $r): array => $r + ['authorName' => $this->authorName($authors, (int) $r['author_id'])],
            $wiki->listRevisions((int) $page['id'])
        );

        $content = $this->app->templates->render('wiki', 'history', [
            'page' => $page,
            'revisions' => $revisions,
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function showRevision(Request $request): Response
    {
        $wiki = new WikiService($this->app->db);
        $page = $wiki->findPageBySlug((string) $request->param('slug', ''));
        if ($page === null) {
            return Response::notFound();
        }

        $revision = $wiki->findRevision((int) $request->param('revisionId', '0'));
        if ($revision === null || (int) $revision['page_id'] !== (int) $page['id']) {
            return Response::notFound();
        }

        $authors = new AuthService($this->app->db);
        $bbcode = new BBCodeParser();

        $content = $this->app->templates->render('wiki', 'revision', [
            'page' => $page,
            'revision' => $revision,
            'renderedBody' => $bbcode->render($revision['body']),
            'authorName' => $this->authorName($authors, (int) $revision['author_id']),
            'canEdit' => $this->app->auth->can('wiki.edit'),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function restoreRevision(Request $request): Response
    {
        if (($guard = $this->requireCapability('wiki.edit')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $wiki = new WikiService($this->app->db);
        $page = $wiki->findPageBySlug((string) $request->param('slug', ''));
        if ($page === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        $wiki->restoreRevision((int) $page['id'], (int) $user['id'], (int) $request->param('revisionId', '0'));

        return Response::redirect('/wiki/' . $page['slug'] . '/history');
    }

    private function requireCapability(string $capability): ?Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->auth->can($capability)) {
            return Response::forbidden();
        }

        return null;
    }

    private function authorName(AuthService $authors, int $userId): string
    {
        $user = $authors->findById($userId);

        return $user['username'] ?? 'Unknown';
    }
}
