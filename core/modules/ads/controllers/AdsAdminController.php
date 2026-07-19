<?php

declare(strict_types=1);

namespace Stratum\Modules\Ads;

use Stratum\Admin\AdminController;
use Stratum\Core\Request;
use Stratum\Core\Response;

final class AdsAdminController extends AdminController
{
    private const ZONES = ['header', 'sidebar_left', 'sidebar_right', 'footer'];

    public function index(Request $request): Response
    {
        if (($guard = $this->guard('ads.manage')) !== null) {
            return $guard;
        }

        $service = new AdService($this->app->db);

        $banners = array_map(
            fn (array $b): array => $b + ['ctr' => $service->clickThroughRate($b)],
            $service->listBanners()
        );

        $content = $this->app->templates->render('ads', 'admin-index', [
            'advertisers' => $service->listAdvertisers(),
            'campaigns' => $service->listCampaigns(),
            'banners' => $banners,
            'zones' => self::ZONES,
            'csrfToken' => $this->app->session->csrfToken(),
        ]);

        return Response::html($this->app->renderPage($content, $request));
    }

    public function createAdvertiser(Request $request): Response
    {
        if (($guard = $this->guard('ads.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $name = trim((string) $request->input('name', ''));
        if ($name !== '') {
            (new AdService($this->app->db))->createAdvertiser(
                $name,
                trim((string) $request->input('contact_name', '')),
                trim((string) $request->input('contact_email', '')),
                trim((string) $request->input('notes', ''))
            );
        }

        return Response::redirect('/admin/ads');
    }

    public function createCampaign(Request $request): Response
    {
        if (($guard = $this->guard('ads.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $advertiserId = (int) $request->input('advertiser_id', '0');
        $name = trim((string) $request->input('name', ''));

        if ($advertiserId > 0 && $name !== '') {
            $startsAt = trim((string) $request->input('starts_at', ''));
            $endsAt = trim((string) $request->input('ends_at', ''));

            (new AdService($this->app->db))->createCampaign(
                $advertiserId,
                $name,
                $startsAt !== '' ? $startsAt : null,
                $endsAt !== '' ? $endsAt : null
            );
        }

        return Response::redirect('/admin/ads');
    }

    public function toggleCampaign(Request $request): Response
    {
        if (($guard = $this->guard('ads.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new AdService($this->app->db);
        $campaign = $service->findCampaign((int) $request->param('id', '0'));
        if ($campaign !== null) {
            $service->setCampaignActive((int) $campaign['id'], !$campaign['is_active']);
        }

        return Response::redirect('/admin/ads');
    }

    public function createBanner(Request $request): Response
    {
        if (($guard = $this->guard('ads.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $campaignId = (int) $request->input('campaign_id', '0');
        $zone = (string) $request->input('zone', '');
        $imageUrl = trim((string) $request->input('image_url', ''));
        $linkUrl = trim((string) $request->input('link_url', ''));

        if ($campaignId > 0 && in_array($zone, self::ZONES, true) && $this->isValidUrl($imageUrl) && $this->isValidUrl($linkUrl)) {
            (new AdService($this->app->db))->createBanner(
                $campaignId,
                $zone,
                $imageUrl,
                $linkUrl,
                trim((string) $request->input('alt_text', ''))
            );
        }

        return Response::redirect('/admin/ads');
    }

    public function toggleBanner(Request $request): Response
    {
        if (($guard = $this->guard('ads.manage')) !== null) {
            return $guard;
        }

        if (!$this->app->session->verifyCsrf($request->input('_csrf'))) {
            return Response::html('Invalid request.', 400);
        }

        $service = new AdService($this->app->db);
        $banner = $service->findBanner((int) $request->param('id', '0'));
        if ($banner !== null) {
            $service->setBannerActive((int) $banner['id'], !$banner['is_active']);
        }

        return Response::redirect('/admin/ads');
    }

    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && (str_starts_with($url, 'http://') || str_starts_with($url, 'https://'));
    }
}
