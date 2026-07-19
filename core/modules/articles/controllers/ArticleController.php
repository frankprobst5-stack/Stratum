<?php

declare(strict_types=1);

namespace Stratum\Modules\Articles;

use Stratum\Core\App;
use Stratum\Core\BBCodeParser;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Core\SeoService;
use Stratum\Modules\Bookmarks\BookmarkService;
use Stratum\Modules\Comments\CommentService;
use Stratum\Modules\Ratings\RatingService;
use Stratum\Modules\Tags\TagService;
use Stratum\Modules\Users\AuthService;

final class ArticleController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = new ArticleService($this->app->db);
        $authors = new AuthService($this->app->db);

        $articles = array_map(
            fn (array $article): array => $article + ['authorName' => $this->authorName($authors, $article)],
            $service->listPublished()
        );

        $content = $this->app->templates->render('articles', 'index', ['articles' => $articles]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function show(Request $request): Response
    {
        $slug = (string) $request->param('slug', '');
        $service = new ArticleService($this->app->db);
        $article = $service->findPublishedBySlug($slug);

        if ($article === null) {
            return Response::notFound();
        }

        $revision = $service->currentRevision((int) $article['id']);
        $authors = new AuthService($this->app->db);
        $comments = new CommentService($this->app->db);
        $bbcode = new BBCodeParser();

        $commentRows = $comments->listFor('article', (int) $article['id']);
        $commentRows = array_map(
            fn (array $comment): array => $comment + ['authorName' => $this->authorName($authors, $comment)],
            $commentRows
        );

        // isEnabled gate, not a `requires` edge — with bookmarks off, the
        // button just doesn't render (same pattern forum's Report link uses).
        $showBookmark = $this->app->modules->isEnabled('bookmarks') && $this->app->auth->check();
        $isBookmarked = $showBookmark
            && (new BookmarkService($this->app->db))->isBookmarked('article', (int) $article['id'], (int) $this->app->auth->user()['id']);

        $showRatings = $this->app->modules->isEnabled('ratings');
        $ratingSummary = null;
        $myRating = null;
        if ($showRatings) {
            $ratings = new RatingService($this->app->db);
            $ratingSummary = $ratings->summaryFor('article', (int) $article['id']);
            $currentUser = $this->app->auth->user();
            $myRating = $currentUser !== null ? $ratings->myRating('article', (int) $article['id'], (int) $currentUser['id']) : null;
        }

        $tags = $this->app->modules->isEnabled('tags')
            ? (new TagService($this->app->db))->tagsFor('article', (int) $article['id'])
            : [];

        $content = $this->app->templates->render('articles', 'show', [
            'article' => $article + [
                'authorName' => $this->authorName($authors, $article),
                'renderedBody' => $revision !== null ? $bbcode->render($revision['body']) : '',
            ],
            'tags' => $tags,
            'comments' => $commentRows,
            'canComment' => $this->app->auth->can('comments.create'),
            'canManage' => $this->app->auth->can('articles.manage'),
            'isLoggedIn' => $this->app->auth->check(),
            'showBookmark' => $showBookmark,
            'isBookmarked' => $isBookmarked,
            'showRatings' => $showRatings,
            'canRate' => $showRatings && $this->app->auth->can('ratings.create'),
            'ratingSummary' => $ratingSummary,
            'myRating' => $myRating,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        $seo = [
            'title' => $article['title'],
            'description' => $article['excerpt'] ?? (new SeoService())->excerpt((string) ($revision['body'] ?? '')),
            'ogType' => 'article',
        ];

        return Response::html($this->app->renderPage($content, $request, $seo));
    }

    public function history(Request $request): Response
    {
        $service = new ArticleService($this->app->db);
        $article = $service->findPublishedBySlug((string) $request->param('slug', ''));
        if ($article === null) {
            return Response::notFound();
        }

        $authors = new AuthService($this->app->db);
        $revisions = array_map(
            fn (array $r): array => $r + ['authorName' => $this->authorName($authors, $r)],
            $service->listRevisions((int) $article['id'])
        );

        $content = $this->app->templates->render('articles', 'history', [
            'article' => $article,
            'revisions' => $revisions,
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function showRevision(Request $request): Response
    {
        $service = new ArticleService($this->app->db);
        $article = $service->findPublishedBySlug((string) $request->param('slug', ''));
        if ($article === null) {
            return Response::notFound();
        }

        $revision = $service->findRevision((int) $request->param('revisionId', '0'));
        if ($revision === null || (int) $revision['article_id'] !== (int) $article['id']) {
            return Response::notFound();
        }

        $authors = new AuthService($this->app->db);
        $bbcode = new BBCodeParser();

        $content = $this->app->templates->render('articles', 'revision', [
            'article' => $article,
            'revision' => $revision,
            'renderedBody' => $bbcode->render($revision['body']),
            'authorName' => $this->authorName($authors, $revision),
            'canManage' => $this->app->auth->can('articles.manage'),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function restoreRevision(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->auth->can('articles.manage')) {
            return Response::forbidden();
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new ArticleService($this->app->db);
        $article = $service->findPublishedBySlug((string) $request->param('slug', ''));
        if ($article === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        $service->restoreRevision((int) $article['id'], (int) $user['id'], (int) $request->param('revisionId', '0'));

        return Response::redirect('/articles/' . $article['slug'] . '/history');
    }

    /** @param array<string, mixed> $row a row with a 'user_id' or 'author_id' column */
    private function authorName(AuthService $authors, array $row): string
    {
        $userId = (int) ($row['author_id'] ?? $row['user_id'] ?? 0);
        $user = $authors->findById($userId);

        return $user['username'] ?? 'Unknown';
    }
}
