<?php

declare(strict_types=1);

namespace Stratum\Modules\Chat;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class ChatAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('chat.manage')) !== null) {
            return $guard;
        }

        $chat = new ChatService($this->app->db);
        $rooms = $chat->listAllRooms();
        foreach ($rooms as &$room) {
            $room['members'] = $room['visibility'] === 'private' ? $chat->listMembers((int) $room['id']) : [];
        }

        $content = $this->app->templates->render('chat', 'admin-index', [
            'rooms' => $rooms,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    /** Admin rooms only — permanent, public or private. A member's own room is created via the public ChatController::createRoom(). */
    public function create(Request $request): Response
    {
        if (($guard = $this->guard('chat.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new ChatService($this->app->db))->createAdminRoom(
            (string) $request->input('name', ''),
            $request->input('topic'),
            (string) $request->input('visibility', 'public')
        );

        return Response::redirect('/admin/chat');
    }

    public function update(Request $request): Response
    {
        if (($guard = $this->guard('chat.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new ChatService($this->app->db))->updateRoom(
            (int) $request->param('id', '0'),
            (string) $request->input('name', ''),
            $request->input('topic'),
            (string) $request->input('visibility', 'public')
        );

        return Response::redirect('/admin/chat');
    }

    /** Deletes ANY room — admin-created or member-created alike, the confirmed "admin has control over all chat rooms" rule. */
    public function delete(Request $request): Response
    {
        if (($guard = $this->guard('chat.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new ChatService($this->app->db))->deleteRoom((int) $request->param('id', '0'));

        return Response::redirect('/admin/chat');
    }

    /** The only way a private room ever gains a member — no self-serve join, by design. */
    public function addMember(Request $request): Response
    {
        if (($guard = $this->guard('chat.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new ChatService($this->app->db))->addMemberByUsername(
            (int) $request->param('id', '0'),
            (string) $request->input('username', '')
        );

        return Response::redirect('/admin/chat');
    }

    public function removeMember(Request $request): Response
    {
        if (($guard = $this->guard('chat.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        (new ChatService($this->app->db))->removeMember(
            (int) $request->param('id', '0'),
            (int) $request->param('userId', '0')
        );

        return Response::redirect('/admin/chat');
    }
}
