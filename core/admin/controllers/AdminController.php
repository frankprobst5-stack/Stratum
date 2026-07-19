<?php

declare(strict_types=1);

namespace Stratum\Admin;

use Stratum\Core\App;
use Stratum\Core\Response;

abstract class AdminController
{
    public function __construct(protected readonly App $app)
    {
    }

    /**
     * Call at the top of every action with the capability that action
     * requires; returns a redirect/403 Response if the guard fails, else null.
     */
    protected function guard(string $capability): ?Response
    {
        if (!$this->app->auth->check()) {
            return Response::redirect('/login');
        }

        if (!$this->app->auth->can($capability)) {
            return Response::forbidden();
        }

        return null;
    }
}
