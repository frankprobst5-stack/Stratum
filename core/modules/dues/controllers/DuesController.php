<?php

declare(strict_types=1);

namespace Stratum\Modules\Dues;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class DuesController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = new DuesService($this->app->db, $this->app->permissions);

        $content = $this->app->templates->render('dues', 'index', [
            'plans' => $service->listPlans(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function plan(Request $request): Response
    {
        $service = new DuesService($this->app->db, $this->app->permissions);
        $plan = $service->findPlan((int) $request->param('id', '0'));
        if ($plan === null) {
            return Response::notFound();
        }

        $currentUser = $this->app->auth->user();
        $myPayments = $currentUser !== null
            ? $service->listPaymentsForUserAndPlan((int) $currentUser['id'], (int) $plan['id'])
            : [];

        $currentPremiumPayment = (bool) $plan['is_premium'] && $currentUser !== null
            ? $service->currentPaymentForPlan((int) $currentUser['id'], (int) $plan['id'])
            : null;

        $content = $this->app->templates->render('dues', 'plan', [
            'plan' => $plan,
            'myPayments' => $myPayments,
            'hasPending' => count(array_filter($myPayments, static fn (array $p): bool => $p['status'] === 'pending')) > 0,
            'currentPremiumPayment' => $currentPremiumPayment,
            'canPay' => $this->app->auth->can('dues.pay'),
            'isLoggedIn' => $this->app->auth->check(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function recordIntent(Request $request): Response
    {
        if (($guard = $this->requireCapability('dues.pay')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new DuesService($this->app->db, $this->app->permissions);
        $planId = (int) $request->param('id', '0');
        if ($service->findPlan($planId) === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        $service->recordIntent($planId, (int) $user['id']);

        return Response::redirect('/dues/plans/' . $planId);
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
}
