<?php

declare(strict_types=1);

namespace Stratum\Modules\Dues;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Users\AuthService;

final class DuesAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('dues.manage')) !== null) {
            return $guard;
        }

        $service = new DuesService($this->app->db, $this->app->permissions);
        $authors = new AuthService($this->app->db);

        $decorate = fn (array $payment): array => $payment + [
            'payerName' => $this->payerName($authors, $payment['user_id'] !== null ? (int) $payment['user_id'] : null),
        ];

        $content = $this->app->templates->render('dues', 'admin-index', [
            'plans' => $service->listPlans(false),
            'pending' => array_map($decorate, $service->listPending()),
            'paid' => array_map($decorate, $service->listPaid()),
            'capabilities' => $this->app->permissions->listCapabilities(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createPlan(Request $request): Response
    {
        if (($guard = $this->guard('dues.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $name = trim((string) $request->input('name', ''));
        $amount = trim((string) $request->input('amount', ''));
        $period = (string) $request->input('period', 'one_time');
        $paymentUrl = trim((string) $request->input('payment_url', ''));

        if ($name === '' || $amount === '' || $paymentUrl === '') {
            return Response::redirect('/admin/dues');
        }

        $isPremium = $request->input('is_premium') === '1';
        $grantsCapabilityKey = trim((string) $request->input('grants_capability_key', ''));

        $service = new DuesService($this->app->db, $this->app->permissions);
        $created = $service->createPlan(
            $name,
            (string) $request->input('description', ''),
            $amount,
            (string) $request->input('currency_code', 'USD'),
            $period,
            $paymentUrl,
            $isPremium,
            $grantsCapabilityKey !== '' ? $grantsCapabilityKey : null
        );

        if (!$created) {
            return Response::html('Payment link must be a valid http:// or https:// URL.', 422);
        }

        return Response::redirect('/admin/dues');
    }

    public function togglePlanActive(Request $request): Response
    {
        if (($guard = $this->guard('dues.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new DuesService($this->app->db, $this->app->permissions);
        $plan = $service->findPlan((int) $request->param('id', '0'));
        if ($plan !== null) {
            $service->setPlanActive((int) $plan['id'], !$plan['is_active']);
        }

        return Response::redirect('/admin/dues');
    }

    public function confirmPayment(Request $request): Response
    {
        if (($guard = $this->guard('dues.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new DuesService($this->app->db, $this->app->permissions);
        $payment = $service->findPayment((int) $request->param('id', '0'));
        if ($payment === null) {
            return Response::notFound();
        }

        $admin = $this->app->auth->user();
        $service->confirmPayment(
            (int) $payment['id'],
            (int) $admin['id'],
            (string) $request->input('amount_paid', ''),
            (string) $request->input('period_label', ''),
            (string) $request->input('notes', '')
        );

        // user_id is nullable on payments — a null recipient is skipped by
        // the listener. Actor deliberately omitted so an admin confirming
        // their own payment still gets notified.
        $plan = $service->findPlan((int) $payment['plan_id']);
        $this->app->notify([
            'user_id' => $payment['user_id'] !== null ? (int) $payment['user_id'] : null,
            'type' => 'dues.confirmed',
            'message' => $plan !== null
                ? 'Your dues payment for "' . $plan['name'] . '" was confirmed'
                : 'Your dues payment was confirmed',
            'url' => '/dues/plans/' . (int) $payment['plan_id'],
        ]);

        return Response::redirect('/admin/dues');
    }

    private function payerName(AuthService $authors, ?int $userId): string
    {
        if ($userId === null) {
            return 'Unknown';
        }

        $user = $authors->findById($userId);

        return $user['username'] ?? 'Unknown';
    }
}
