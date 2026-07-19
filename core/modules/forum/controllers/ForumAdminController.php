<?php

declare(strict_types=1);

namespace Stratum\Modules\Forum;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Users\AuthService;

final class ForumAdminController extends AdminController
{
    private const BOARD_SCOPE = 'forum_board';
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('forum.manage')) !== null) {
            return $guard;
        }

        $forum = new ForumService($this->app->db);

        $content = $this->app->templates->render('forum', 'admin-index', [
            'categories' => $forum->listCategories(),
            'boards' => $forum->listBoards(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createCategory(Request $request): Response
    {
        if (($guard = $this->guard('forum.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            (new ForumService($this->app->db))->createCategory($name);
        }

        return Response::redirect('/admin/forum');
    }

    public function createBoard(Request $request): Response
    {
        if (($guard = $this->guard('forum.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $categoryId = (int) $request->input('category_id', '0');
        $name = trim((string) $request->input('name', ''));
        $description = (string) $request->input('description', '');
        $parentId = (int) $request->input('parent_id', '0') ?: null;

        if ($categoryId > 0 && $name !== '') {
            (new ForumService($this->app->db))->createBoard($categoryId, $name, $description, $parentId);
        }

        return Response::redirect('/admin/forum');
    }

    public function boardModerators(Request $request): Response
    {
        if (($guard = $this->guard('forum.manage')) !== null) {
            return $guard;
        }

        $forum = new ForumService($this->app->db);
        $board = $forum->findBoard((int) $request->param('id', '0'));
        if ($board === null) {
            return Response::notFound();
        }

        $role = $this->moderatorRoleForBoard($board);
        $authors = new AuthService($this->app->db);
        $moderators = array_map(
            fn (int $userId): array => $authors->findById($userId) ?? ['id' => $userId, 'username' => 'Unknown'],
            $this->app->permissions->usersInRole((int) $role['id'])
        );

        $content = $this->app->templates->render('forum', 'board-moderators', [
            'board' => $board,
            'moderators' => $moderators,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function addBoardModerator(Request $request): Response
    {
        if (($guard = $this->guard('forum.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $forum = new ForumService($this->app->db);
        $board = $forum->findBoard((int) $request->param('id', '0'));
        if ($board === null) {
            return Response::notFound();
        }

        $username = trim((string) $request->input('username', ''));
        if ($username !== '') {
            $user = (new AuthService($this->app->db))->findByUsername($username);
            if ($user !== null) {
                $role = $this->moderatorRoleForBoard($board);
                $this->app->permissions->addRoleToUser((int) $user['id'], (int) $role['id']);

                $admin = $this->app->auth->user();
                $this->app->notify([
                    'user_id' => (int) $user['id'],
                    'actor_id' => $admin !== null ? (int) $admin['id'] : null,
                    'type' => 'forum.moderator',
                    'message' => 'You are now a moderator of "' . $board['name'] . '"',
                    'url' => '/forum/boards/' . $board['slug'],
                ]);
            }
        }

        return Response::redirect('/admin/forum/boards/' . $board['id'] . '/moderators');
    }

    public function removeBoardModerator(Request $request): Response
    {
        if (($guard = $this->guard('forum.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $forum = new ForumService($this->app->db);
        $board = $forum->findBoard((int) $request->param('id', '0'));
        if ($board === null) {
            return Response::notFound();
        }

        $role = $this->moderatorRoleForBoard($board);
        $this->app->permissions->removeRoleFromUser((int) $request->param('userId', '0'), (int) $role['id']);

        return Response::redirect('/admin/forum/boards/' . $board['id'] . '/moderators');
    }

    /**
     * Finds this board's dedicated moderator role, creating it (and its
     * scoped forum.moderate grant) on first use — self-heals for boards
     * that existed before per-board moderation did, same as boards created
     * from now on. See the Scoped Permission Engine plan's Decisions.
     *
     * @param array<string, mixed> $board
     * @return array<string, mixed>
     */
    private function moderatorRoleForBoard(array $board): array
    {
        $boardId = (int) $board['id'];
        $existing = $this->app->permissions->findRoleForScope(self::BOARD_SCOPE, $boardId);
        if ($existing !== null) {
            return $existing;
        }

        $roleId = $this->app->permissions->createRole(
            "Moderators — {$board['name']} (#{$boardId})",
            self::BOARD_SCOPE,
            $boardId
        );

        $capability = $this->app->permissions->findCapabilityByKey('forum.moderate');
        if ($capability !== null) {
            $this->app->permissions->grant((int) $roleId, (int) $capability['id'], self::BOARD_SCOPE, $boardId);
        }

        return ['id' => $roleId, 'name' => "Moderators — {$board['name']} (#{$boardId})"];
    }
}
