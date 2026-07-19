<?php

declare(strict_types=1);

namespace Stratum\Modules\Articles;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Tags\TagService;

final class ArticlesAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('articles.manage')) !== null) {
            return $guard;
        }

        $service = new ArticleService($this->app->db);

        $content = $this->app->templates->render('articles', 'admin-index', [
            'articles' => $service->listAll(),
            'categories' => $service->listCategories(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function showCreate(Request $request): Response
    {
        if (($guard = $this->guard('articles.manage')) !== null) {
            return $guard;
        }

        $service = new ArticleService($this->app->db);

        $content = $this->app->templates->render('articles', 'admin-form', [
            'article' => null,
            'body' => '',
            'tags' => '',
            'showTags' => $this->app->modules->isEnabled('tags'),
            'categories' => $service->listCategories(),
            'csrfToken' => $this->app->session->csrfToken(),
            'formAction' => '/admin/articles/create',
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function create(Request $request): Response
    {
        if (($guard = $this->guard('articles.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new ArticleService($this->app->db);
        $user = $this->app->auth->user();

        $result = $service->create([
            'author_id' => $user['id'],
            'category_id' => (int) $request->input('category_id', '0') ?: null,
            'title' => (string) $request->input('title', ''),
            'excerpt' => (string) $request->input('excerpt', ''),
            'featured_image_url' => (string) $request->input('featured_image_url', ''),
            'body' => (string) $request->input('body', ''),
            'publish_action' => (string) $request->input('publish_action', 'draft'),
            'scheduled_at' => (string) $request->input('scheduled_at', ''),
        ]);

        if ($this->app->modules->isEnabled('tags')) {
            (new TagService($this->app->db))->setTags('article', $result['articleId'], (string) $request->input('tags', ''));
        }

        return Response::redirect('/admin/articles');
    }

    public function showEdit(Request $request): Response
    {
        if (($guard = $this->guard('articles.manage')) !== null) {
            return $guard;
        }

        $service = new ArticleService($this->app->db);
        $article = $service->find((int) $request->param('id', '0'));

        if ($article === null) {
            return Response::notFound();
        }

        $revision = $service->currentRevision((int) $article['id']);
        $showTags = $this->app->modules->isEnabled('tags');

        $content = $this->app->templates->render('articles', 'admin-form', [
            'article' => $article,
            'body' => $revision['body'] ?? '',
            'tags' => $showTags ? (new TagService($this->app->db))->tagsForAsCsv('article', (int) $article['id']) : '',
            'showTags' => $showTags,
            'categories' => $service->listCategories(),
            'csrfToken' => $this->app->session->csrfToken(),
            'formAction' => '/admin/articles/' . $article['id'] . '/edit',
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function update(Request $request): Response
    {
        if (($guard = $this->guard('articles.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $id = (int) $request->param('id', '0');
        $service = new ArticleService($this->app->db);

        $service->update($id, [
            'category_id' => (int) $request->input('category_id', '0') ?: null,
            'title' => (string) $request->input('title', ''),
            'excerpt' => (string) $request->input('excerpt', ''),
            'featured_image_url' => (string) $request->input('featured_image_url', ''),
            'publish_action' => (string) $request->input('publish_action', 'draft'),
            'scheduled_at' => (string) $request->input('scheduled_at', ''),
        ]);

        $body = trim((string) $request->input('body', ''));
        if ($body !== '') {
            $user = $this->app->auth->user();
            $comment = trim((string) $request->input('comment', ''));
            $service->addRevision($id, (int) $user['id'], $body, $comment);
        }

        if ($this->app->modules->isEnabled('tags')) {
            (new TagService($this->app->db))->setTags('article', $id, (string) $request->input('tags', ''));
        }

        return Response::redirect('/admin/articles');
    }

    public function delete(Request $request): Response
    {
        if (($guard = $this->guard('articles.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new ArticleService($this->app->db))->softDelete((int) $request->param('id', '0'));

        return Response::redirect('/admin/articles');
    }

    public function createCategory(Request $request): Response
    {
        if (($guard = $this->guard('articles.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            (new ArticleService($this->app->db))->createCategory($name);
        }

        return Response::redirect('/admin/articles');
    }
}
