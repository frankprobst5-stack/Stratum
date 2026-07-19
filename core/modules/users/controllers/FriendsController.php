<?php

declare(strict_types=1);

namespace Stratum\Modules\Users;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class FriendsController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        $service = new FriendService($this->app->db);
        $user = $this->app->auth->user();
        $userId = (int) $user['id'];

        $content = $this->app->templates->render('users', 'friends', [
            'friends' => $service->listFriends($userId),
            'incoming' => $service->listIncomingRequests($userId),
            'outgoing' => $service->listOutgoingRequests($userId),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }
}
