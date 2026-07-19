<?php

declare(strict_types=1);

namespace Stratum\Modules\Donations;

use Stratum\Core\App;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class DonationController
{
    public function __construct(private readonly App $app)
    {
    }

    public function index(Request $request): Response
    {
        $service = new DonationService($this->app->db);

        $campaigns = array_map(
            fn (array $c): array => $c + ['raised' => $service->raisedAmount((int) $c['id'])],
            $service->listCampaigns()
        );

        $content = $this->app->templates->render('donations', 'index', [
            'campaigns' => $campaigns,
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function campaign(Request $request): Response
    {
        $service = new DonationService($this->app->db);
        $campaign = $service->findCampaign((int) $request->param('id', '0'));
        if ($campaign === null) {
            return Response::notFound();
        }

        $currentUser = $this->app->auth->user();
        $myContributions = $currentUser !== null
            ? $service->listContributionsForUserAndCampaign((int) $currentUser['id'], (int) $campaign['id'])
            : [];

        $raised = $service->raisedAmount((int) $campaign['id']);
        $progressPercent = (float) $campaign['goal_amount'] > 0
            ? min(100, (int) round(((float) $raised / (float) $campaign['goal_amount']) * 100))
            : 0;

        $content = $this->app->templates->render('donations', 'campaign', [
            'campaign' => $campaign,
            'raised' => $raised,
            'progressPercent' => $progressPercent,
            'myContributions' => $myContributions,
            'hasPending' => count(array_filter($myContributions, static fn (array $c): bool => $c['status'] === 'pending')) > 0,
            'canContribute' => $this->app->auth->can('donations.contribute'),
            'isLoggedIn' => $this->app->auth->check(),
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function recordIntent(Request $request): Response
    {
        if (($guard = $this->requireCapability('donations.contribute')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new DonationService($this->app->db);
        $campaignId = (int) $request->param('id', '0');
        if ($service->findCampaign($campaignId) === null) {
            return Response::notFound();
        }

        $user = $this->app->auth->user();
        $service->recordIntent($campaignId, (int) $user['id']);

        return Response::redirect('/donations/campaigns/' . $campaignId);
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
