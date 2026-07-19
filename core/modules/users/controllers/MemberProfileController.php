<?php

declare(strict_types=1);

namespace Stratum\Modules\Users;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

/**
 * The public "view another member's profile" page — didn't exist before
 * Friends/Following needed it (`/profile` only ever showed/edited your
 * own account). Read-only: username, avatar, banner, about me, rank,
 * join date, plus Friends/Following relationship state and actions.
 */
final class MemberProfileController
{
    public function __construct(private readonly App $app)
    {
    }

    public function show(Request $request): Response
    {
        $authors = new AuthService($this->app->db);
        $member = $authors->findByUsername((string) $request->param('username', ''));
        if ($member === null) {
            return Response::notFound();
        }

        $friends = new FriendService($this->app->db);
        $follows = new FollowService($this->app->db);
        $currentUser = $this->app->auth->user();
        $viewerId = $currentUser !== null ? (int) $currentUser['id'] : null;

        $content = $this->app->templates->render('users', 'member-profile', [
            'member' => $member,
            'rankName' => $authors->rankName($member['rank_id'] !== null ? (int) $member['rank_id'] : null),
            'badges' => (new BadgeService($this->app->db))->listForUser((int) $member['id']),
            'friendCount' => $friends->friendCount((int) $member['id']),
            'followerCount' => $follows->followerCount((int) $member['id']),
            'followingCount' => $follows->followingCount((int) $member['id']),
            'isLoggedIn' => $viewerId !== null,
            'relationship' => $viewerId !== null ? $friends->relationshipStatus($viewerId, (int) $member['id']) : 'none',
            'isFollowing' => $viewerId !== null && $follows->isFollowing($viewerId, (int) $member['id']),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function sendFriendRequest(Request $request): Response
    {
        if (($guard = $this->requireAuth($request)) !== null) {
            return $guard;
        }

        $target = $this->targetFromParam($request);
        if ($target === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        $result = (new FriendService($this->app->db))->sendRequest((int) $user['id'], (int) $target['id']);

        if ($result === 'pending') {
            $this->app->notify([
                'user_id' => (int) $target['id'],
                'actor_id' => (int) $user['id'],
                'type' => 'friend.request',
                'message' => $user['username'] . ' sent you a friend request',
                'url' => '/members/' . $target['username'],
            ]);
        } elseif ($result === 'auto_accepted') {
            // $target had already sent us a pending request — our own request
            // just auto-accepted theirs, so they're the one who should hear
            // "your request was accepted," same as a normal accept would notify.
            $this->app->notify([
                'user_id' => (int) $target['id'],
                'actor_id' => (int) $user['id'],
                'type' => 'friend.accepted',
                'message' => $user['username'] . ' accepted your friend request',
                'url' => '/members/' . $user['username'],
            ]);
        }

        return Response::redirect('/members/' . $target['username']);
    }

    public function acceptFriendRequest(Request $request): Response
    {
        if (($guard = $this->requireAuth($request)) !== null) {
            return $guard;
        }

        $sender = $this->targetFromParam($request);
        if ($sender === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        if ((new FriendService($this->app->db))->accept((int) $user['id'], (int) $sender['id'])) {
            $this->app->notify([
                'user_id' => (int) $sender['id'],
                'actor_id' => (int) $user['id'],
                'type' => 'friend.accepted',
                'message' => $user['username'] . ' accepted your friend request',
                'url' => '/members/' . $user['username'],
            ]);
        }

        return Response::redirect($this->safeRedirectTarget($request->input('redirect_to', '/friends')));
    }

    public function declineFriendRequest(Request $request): Response
    {
        if (($guard = $this->requireAuth($request)) !== null) {
            return $guard;
        }

        $sender = $this->targetFromParam($request);
        if ($sender === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        (new FriendService($this->app->db))->decline((int) $user['id'], (int) $sender['id']);

        return Response::redirect($this->safeRedirectTarget($request->input('redirect_to', '/friends')));
    }

    public function removeFriend(Request $request): Response
    {
        if (($guard = $this->requireAuth($request)) !== null) {
            return $guard;
        }

        $target = $this->targetFromParam($request);
        if ($target === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        (new FriendService($this->app->db))->removeFriend((int) $user['id'], (int) $target['id']);

        return Response::redirect($this->safeRedirectTarget($request->input('redirect_to', '/members/' . $target['username'])));
    }

    /** Any logged-in member can follow — no capability, same as post/photo likes. */
    public function toggleFollow(Request $request): Response
    {
        if (($guard = $this->requireAuth($request)) !== null) {
            return $guard;
        }

        $target = $this->targetFromParam($request);
        if ($target === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        (new FollowService($this->app->db))->toggle((int) $user['id'], (int) $target['id']);

        return Response::redirect('/members/' . $target['username']);
    }

    /** @return array<string, mixed>|null */
    private function targetFromParam(Request $request): ?array
    {
        return (new AuthService($this->app->db))->findByUsername((string) $request->param('username', ''));
    }

    private function requireAuth(Request $request): ?Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        return null;
    }

    /** Only ever redirect to a local path — never trust the client-supplied target as-is (same guard CommentsController/BookmarkController use). */
    private function safeRedirectTarget(?string $path): string
    {
        if ($path === null || $path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            return '/';
        }

        return $path;
    }
}
