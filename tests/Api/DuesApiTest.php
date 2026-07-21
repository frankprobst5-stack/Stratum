<?php

declare(strict_types=1);

namespace Tests\Api;

use Stratum\Api\DuesApiController;
use Stratum\Modules\Dues\DuesService;
use Tests\TestCase;

final class DuesApiTest extends TestCase
{
    /** @var int[] plan ids created by this test, cleaned up in tearDown() */
    private array $planIds = [];

    protected function tearDown(): void
    {
        foreach ($this->planIds as $id) {
            $this->db->execute('DELETE FROM ' . $this->db->table('dues_payments') . ' WHERE plan_id = :id', ['id' => $id]);
            $this->db->execute('DELETE FROM ' . $this->db->table('dues_plans') . ' WHERE id = :id', ['id' => $id]);
        }
        $this->planIds = [];

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function createPlan(): array
    {
        $name = 'API test dues plan ' . bin2hex(random_bytes(4));
        (new DuesService($this->db, $this->app->permissions))->createPlan(
            $name,
            'a test plan',
            '5.00',
            'USD',
            'monthly',
            'https://cash.app/$testtag/5.00'
        );

        $plan = $this->db->fetchOne('SELECT * FROM ' . $this->db->table('dues_plans') . ' WHERE name = :name', ['name' => $name]);
        $this->planIds[] = (int) $plan['id'];

        return $plan;
    }

    public function testIndexListsPlan(): void
    {
        $plan = $this->createPlan();

        $controller = new DuesApiController($this->app);
        $response = $controller->index($this->makeRequest('GET', '/api/v1/dues/plans', ['per_page' => '1000']));
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $names = array_column($body['data'], 'name');
        $this->assertContains($plan['name'], $names);
    }

    public function testShowReturnsNullIsCurrentForGuest(): void
    {
        $plan = $this->createPlan();

        $controller = new DuesApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/dues/plans/' . $plan['id']);
        $request->setRouteParams(['id' => (string) $plan['id']]);

        $response = $controller->show($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertNull($body['data']['isCurrent']);
    }

    public function testShowReturnsFalseIsCurrentForAuthenticatedNonMember(): void
    {
        $plan = $this->createPlan();
        $user = $this->createUser();
        $app = $this->asUser($user);

        $controller = new DuesApiController($app);
        $request = $this->makeRequest('GET', '/api/v1/dues/plans/' . $plan['id']);
        $request->setRouteParams(['id' => (string) $plan['id']]);

        $response = $controller->show($request);
        $body = json_decode($response->body(), true);

        $this->assertSame(200, $response->status());
        $this->assertFalse($body['data']['isCurrent']);
    }

    public function testShowReturns404ForUnknownId(): void
    {
        $controller = new DuesApiController($this->app);
        $request = $this->makeRequest('GET', '/api/v1/dues/plans/999999999');
        $request->setRouteParams(['id' => '999999999']);

        $response = $controller->show($request);

        $this->assertSame(404, $response->status());
    }
}
