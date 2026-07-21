<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\DonationsApiController;
use Stratum\Modules\Donations\DonationService;
use Tests\TestCase;

final class DonationsApiTest extends TestCase
{
    /** @var int[] campaign ids created by this test, cleaned up in tearDown() */
    private array $campaignIds = [];

    protected function tearDown(): void
    {
        foreach ($this->campaignIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('donation_contributions') . ' WHERE campaign_id = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('donation_campaigns') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->campaignIds = [];

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function createCampaign(): array
    {
        $title = 'API test donation campaign ' . bin2hex(random_bytes(4));
        (new DonationService($this->db))->createCampaign(
            $title,
            'a test campaign',
            '100.00',
            'USD',
            'https://cash.app/$testtag/'
        );

        $campaign = $this->db->fetchOne('SELECT * FROM ' . $this->db->table('donation_campaigns') . ' WHERE title = :title', ['title' => $title]);
        $this->campaignIds[] = (int) $campaign['id'];

        return $campaign;
    }

    public function testIndexListsCampaignWithRaisedAmount(): void
    {
        $campaign = $this->createCampaign();

        $controller = new DonationsApiController($this->app);
        $response = $controller->index($this->makeRequest('GET', '/api/v1/donations/campaigns', ['per_page' => '1000']));
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $match = array_values(array_filter(
            $body['data'],
            static fn (array $c): bool => (int) $c['id'] === (int) $campaign['id']
        ));
        $this->assertNotEmpty($match);
        $this->assertArrayHasKey('raised', $match[0]);
    }

    public function testShowReturnsCampaignWithRaisedAmount(): void
    {
        $campaign = $this->createCampaign();

        $controller = new DonationsApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/donations/campaigns/' . $campaign['id']);
        $request->setRouteParams(['id' => (string) $campaign['id']]);

        $response = $controller->show($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertSame($campaign['title'], $body['data']['title']);
        $this->assertArrayHasKey('raised', $body['data']);
    }

    public function testShowReturns404ForUnknownId(): void
    {
        $controller = new DonationsApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/donations/campaigns/999999999');
        $request->setRouteParams(['id' => '999999999']);

        $response = $controller->show($request);

        $this->assertSame(404, $response->status());
    }
}
