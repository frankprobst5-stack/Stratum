<?php

declare(strict_types=1);

namespace Stratum\Modules\OrgSpaces;

use Stratum\Core\App;
use Stratum\Core\BBCodeParser;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Users\AuthService;

final class OrgForumController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireMember((int) $org['id'])) !== null) {
            return $guard;
        }

        $forum = new OrgForumService($this->app->db);
        $authors = new AuthService($this->app->db);

        $topics = array_map(
            fn (array $t): array => $t + ['authorName' => $this->authorName($authors, (int) $t['author_id'])],
            $forum->listTopics((int) $org['id'])
        );

        $content = $this->app->templates->render('org_spaces', 'forum-index', [
            'org' => $org,
            'topics' => $topics,
            'canManage' => $this->canManageOrg((int) $org['id']),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createTopic(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireMember((int) $org['id'], verifyCsrf: true, request: $request)) !== null) {
            return $guard;
        }

        $title = trim((string) $request->input('title', ''));
        $body = trim((string) $request->input('body', ''));
        if ($title === '' || $body === '') {
            return Response::redirect('/organizations/' . $org['slug'] . '/forum');
        }

        $user = $this->app->auth->user();
        $forum = new OrgForumService($this->app->db);
        $ids = $forum->createTopicWithFirstPost((int) $org['id'], (int) $user['id'], $title, $body);

        return Response::redirect('/organizations/' . $org['slug'] . '/forum/topics/' . $ids['topicId']);
    }

    public function topic(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireMember((int) $org['id'])) !== null) {
            return $guard;
        }

        $forum = new OrgForumService($this->app->db);
        $topic = $forum->findTopic((int) $request->param('id', '0'));
        if ($topic === null || (int) $topic['org_id'] !== (int) $org['id']) {
            return Response::notFound();
        }

        $authors = new AuthService($this->app->db);
        $bbcode = new BBCodeParser();
        $posts = array_map(fn (array $post): array => $post + [
            'authorName' => $this->authorName($authors, (int) $post['author_id']),
            'renderedBody' => $bbcode->render($post['body']),
        ], $forum->listPosts((int) $topic['id']));

        $content = $this->app->templates->render('org_spaces', 'forum-topic', [
            'org' => $org,
            'topic' => $topic,
            'posts' => $posts,
            'canManage' => $this->canManageOrg((int) $org['id']),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function reply(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireMember((int) $org['id'], verifyCsrf: true, request: $request)) !== null) {
            return $guard;
        }

        $forum = new OrgForumService($this->app->db);
        $topicId = (int) $request->param('id', '0');
        $topic = $forum->findTopic($topicId);
        if ($topic === null || (int) $topic['org_id'] !== (int) $org['id']) {
            return Response::notFound();
        }

        if ((bool) $topic['is_locked'] && !$this->canManageOrg((int) $org['id'])) {
            return Response::html('This topic is locked.', 403);
        }

        $body = trim((string) $request->input('body', ''));
        if ($body !== '') {
            $user = $this->app->auth->user();
            $forum->reply($topicId, (int) $user['id'], $body);

            // Self-replies are skipped inside the notification listener,
            // same as the public forum's reply notification.
            $this->app->notify([
                'user_id' => (int) $topic['author_id'],
                'actor_id' => (int) $user['id'],
                'type' => 'org.forum_reply',
                'message' => $user['username'] . ' replied to your topic "' . $topic['title'] . '" in ' . $org['name'],
                'url' => '/organizations/' . $org['slug'] . '/forum/topics/' . $topicId,
            ]);
        }

        return Response::redirect('/organizations/' . $org['slug'] . '/forum/topics/' . $topicId);
    }

    public function lock(Request $request): Response
    {
        return $this->moderateTopic($request, static fn (OrgForumService $f, int $id) => $f->setLocked($id, true));
    }

    public function unlock(Request $request): Response
    {
        return $this->moderateTopic($request, static fn (OrgForumService $f, int $id) => $f->setLocked($id, false));
    }

    public function deleteTopic(Request $request): Response
    {
        return $this->moderateTopic($request, static fn (OrgForumService $f, int $id) => $f->softDeleteTopic($id));
    }

    public function deletePost(Request $request): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireManage((int) $org['id'], $request)) !== null) {
            return $guard;
        }

        $forum = new OrgForumService($this->app->db);
        $post = $forum->findPost((int) $request->param('id', '0'));
        if ($post !== null) {
            $topic = $forum->findTopic((int) $post['topic_id']);
            if ($topic !== null && (int) $topic['org_id'] === (int) $org['id']) {
                $forum->softDeletePost((int) $post['id']);
            }
        }

        return Response::redirect('/organizations/' . $org['slug'] . '/forum');
    }

    /** @param callable(OrgForumService, int): void $action */
    private function moderateTopic(Request $request, callable $action): Response
    {
        $org = $this->requireActiveOrg($request);
        if ($org instanceof Response) {
            return $org;
        }

        if (($guard = $this->requireManage((int) $org['id'], $request)) !== null) {
            return $guard;
        }

        $forum = new OrgForumService($this->app->db);
        $topicId = (int) $request->param('id', '0');
        $topic = $forum->findTopic($topicId);
        if ($topic === null || (int) $topic['org_id'] !== (int) $org['id']) {
            return Response::notFound();
        }

        $action($forum, $topicId);

        return Response::redirect('/organizations/' . $org['slug'] . '/forum/topics/' . $topicId);
    }

    /** @return array<string, mixed>|Response */
    private function requireActiveOrg(Request $request): array|Response
    {
        $service = new OrgSpaceService($this->app->db, $this->app->permissions);
        $org = $service->findOrgBySlug((string) $request->param('slug', ''));
        if ($org === null || !$org['is_active']) {
            return Response::notFound();
        }

        return $org;
    }

    /**
     * Content here is never public — guest gets redirected to login, a
     * logged-in non-member gets a hard 403 (officers/admin always pass via
     * canManageOrg). CSRF is checked here too when $verifyCsrf is true, so
     * every mutating action gets the same guard as the read gate.
     */
    private function requireMember(int $orgId, bool $verifyCsrf = false, ?Request $request = null): ?Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        $user = $this->app->auth->user();
        $service = new OrgSpaceService($this->app->db, $this->app->permissions);
        if (!$service->isMember((int) $user['id'], $orgId) && !$this->canManageOrg($orgId)) {
            return Response::forbidden();
        }

        if ($verifyCsrf && !$this->app->session->verifyCsrf($request?->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        return null;
    }

    private function requireManage(int $orgId, Request $request): ?Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->canManageOrg($orgId)) {
            return Response::forbidden();
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        return null;
    }

    private function canManageOrg(int $orgId): bool
    {
        return $this->app->auth->can('org_spaces.moderate', 'org', $orgId);
    }

    private function authorName(AuthService $authors, int $userId): string
    {
        $user = $authors->findById($userId);

        return $user['username'] ?? 'Unknown';
    }
}
