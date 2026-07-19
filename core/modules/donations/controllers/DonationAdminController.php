<?php

declare(strict_types=1);

namespace Stratum\Modules\Donations;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;
use Stratum\Modules\Users\AuthService;

final class DonationAdminController extends AdminController
{
    public function index(Request $request): Response
    {
        if (($guard = $this->guard('donations.manage')) !== null) {
            return $guard;
        }

        $service = new DonationService($this->app->db);
        $authors = new AuthService($this->app->db);

        $decorate = fn (array $contribution): array => $contribution + [
            'contributorName' => $this->contributorName($authors, $contribution),
        ];

        $campaigns = array_map(
            fn (array $c): array => $c + ['raised' => $service->raisedAmount((int) $c['id'])],
            $service->listCampaigns(false)
        );

        $content = $this->app->templates->render('donations', 'admin-index', [
            'campaigns' => $campaigns,
            'pending' => array_map($decorate, $service->listPending()),
            'confirmed' => array_map($decorate, $service->listConfirmed()),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createCampaign(Request $request): Response
    {
        if (($guard = $this->guard('donations.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $title = trim((string) $request->input('title', ''));
        $goalAmount = trim((string) $request->input('goal_amount', ''));
        $paymentUrl = trim((string) $request->input('payment_url', ''));

        if ($title === '' || $goalAmount === '' || $paymentUrl === '') {
            return Response::redirect('/admin/donations');
        }

        $service = new DonationService($this->app->db);
        $created = $service->createCampaign(
            $title,
            (string) $request->input('description', ''),
            $goalAmount,
            (string) $request->input('currency_code', 'USD'),
            $paymentUrl
        );

        if (!$created) {
            return Response::html('Payment link must be a valid http:// or https:// URL.', 422);
        }

        return Response::redirect('/admin/donations');
    }

    public function toggleCampaignActive(Request $request): Response
    {
        if (($guard = $this->guard('donations.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new DonationService($this->app->db);
        $campaign = $service->findCampaign((int) $request->param('id', '0'));
        if ($campaign !== null) {
            $service->setCampaignActive((int) $campaign['id'], !$campaign['is_active']);
        }

        return Response::redirect('/admin/donations');
    }

    public function confirmContribution(Request $request): Response
    {
        if (($guard = $this->guard('donations.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new DonationService($this->app->db);
        $contribution = $service->findContribution((int) $request->param('id', '0'));
        if ($contribution === null) {
            return Response::notFound();
        }

        $campaignId = (int) $contribution['campaign_id'];
        $raisedBefore = $service->raisedAmount($campaignId);

        $admin = $this->app->auth->user();
        $service->confirmContribution(
            (int) $contribution['id'],
            (int) $admin['id'],
            (string) $request->input('amount', ''),
            (string) $request->input('notes', '')
        );

        // user_id is null for cash/free-text donor entries — the listener
        // skips those. Actor omitted for the same reason as dues.
        $campaign = $service->findCampaign($campaignId);
        $this->app->notify([
            'user_id' => $contribution['user_id'] !== null ? (int) $contribution['user_id'] : null,
            'type' => 'donation.confirmed',
            'message' => $campaign !== null
                ? 'Your donation to "' . $campaign['title'] . '" was confirmed — thank you!'
                : 'Your donation was confirmed — thank you!',
            'url' => '/donations/campaigns/' . $campaignId,
        ]);

        $this->notifyIfGoalJustReached($service, $campaignId, $raisedBefore, $campaign);

        return Response::redirect('/admin/donations');
    }

    public function recordContribution(Request $request): Response
    {
        if (($guard = $this->guard('donations.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new DonationService($this->app->db);
        $campaignId = (int) $request->input('campaign_id', '0');
        if ($service->findCampaign($campaignId) === null) {
            return Response::redirect('/admin/donations');
        }

        $amount = trim((string) $request->input('amount', ''));
        $username = trim((string) $request->input('username', ''));
        $donorName = trim((string) $request->input('donor_name', ''));

        if ($amount === '' || ($username === '' && $donorName === '')) {
            return Response::redirect('/admin/donations');
        }

        $userId = null;
        if ($username !== '') {
            $user = (new AuthService($this->app->db))->findByUsername($username);
            if ($user === null) {
                return Response::html('No user with that username.', 422);
            }
            $userId = (int) $user['id'];
        }

        $raisedBefore = $service->raisedAmount($campaignId);

        $admin = $this->app->auth->user();
        $service->recordDirectContribution(
            $campaignId,
            $userId,
            $userId === null ? $donorName : null,
            (int) $admin['id'],
            $amount,
            (string) $request->input('notes', '')
        );

        $this->notifyIfGoalJustReached($service, $campaignId, $raisedBefore, $service->findCampaign($campaignId));

        return Response::redirect('/admin/donations');
    }

    /**
     * Fires a one-time "goal reached" notification to every admin
     * holding `donations.manage` — the one genuine gap in the base
     * `donations` module's already-live goal/progress-bar mechanic
     * (goal_amount + raisedAmount() already existed; nothing fired an
     * event on crossing 100%). Comparing before/after within this one
     * request is what keeps it firing exactly once at the crossing,
     * not on every contribution once a campaign is already past goal.
     *
     * @param array<string, mixed>|null $campaign
     */
    private function notifyIfGoalJustReached(DonationService $service, int $campaignId, string $raisedBefore, ?array $campaign): void
    {
        if ($campaign === null) {
            return;
        }

        $goal = (float) $campaign['goal_amount'];
        if ((float) $raisedBefore >= $goal || !$service->hasReachedGoal($campaignId)) {
            return;
        }

        foreach ($this->adminsWithCapability('donations.manage') as $adminId) {
            $this->app->notify([
                'user_id' => $adminId,
                'type' => 'donation.goal_reached',
                'message' => '"' . $campaign['title'] . '" has reached its donation goal!',
                'url' => '/donations/campaigns/' . $campaignId,
            ]);
        }
    }

    /** @return int[] deduplicated user ids holding $capabilityKey via any site-wide role */
    private function adminsWithCapability(string $capabilityKey): array
    {
        $capability = $this->app->permissions->findCapabilityByKey($capabilityKey);
        if ($capability === null) {
            return [];
        }

        $roleIds = array_column(
            array_filter(
                $this->app->permissions->listGrants(),
                static fn (array $grant): bool => $grant['capability_id'] === (int) $capability['id']
            ),
            'role_id'
        );

        $userIds = [];
        foreach ($roleIds as $roleId) {
            foreach ($this->app->permissions->usersInRole($roleId) as $userId) {
                $userIds[$userId] = true;
            }
        }

        return array_keys($userIds);
    }

    /** @param array<string, mixed> $contribution */
    private function contributorName(AuthService $authors, array $contribution): string
    {
        if (!empty($contribution['donor_name'])) {
            return (string) $contribution['donor_name'];
        }

        if ($contribution['user_id'] === null) {
            return 'Unknown';
        }

        $user = $authors->findById((int) $contribution['user_id']);

        return $user['username'] ?? 'Unknown';
    }
}
