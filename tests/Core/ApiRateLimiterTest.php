<?php

declare(strict_types=1);

namespace Tests\Core;

use Stratum\Core\ApiRateLimiter;
use Tests\TestCase;

final class ApiRateLimiterTest extends TestCase
{
    /** @var string[] identifiers created by this test, cleaned up in tearDown() */
    private array $identifiers = [];

    protected function tearDown(): void
    {
        foreach ($this->identifiers as $identifier) {
            $this->db->execute('DELETE FROM ' . $this->db->table('api_rate_limits') . ' WHERE identifier = :id', ['id' => $identifier]);
        }
        $this->identifiers = [];

        parent::tearDown();
    }

    public function testStaysUnderLimitForFewRequests(): void
    {
        $identifier = 'test:' . bin2hex(random_bytes(4));
        $this->identifiers[] = $identifier;
        $limiter = new ApiRateLimiter($this->db);

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($limiter->tooManyRequests($identifier, 10));
        }
    }

    public function testTripsOverAfterExceedingLimit(): void
    {
        $identifier = 'test:' . bin2hex(random_bytes(4));
        $this->identifiers[] = $identifier;
        $limiter = new ApiRateLimiter($this->db);

        $tripped = false;
        for ($i = 0; $i < 10; $i++) {
            if ($limiter->tooManyRequests($identifier, 5)) {
                $tripped = true;
                break;
            }
        }

        $this->assertTrue($tripped);
    }

    public function testDifferentIdentifiersHaveIndependentCounters(): void
    {
        $identifierA = 'test:' . bin2hex(random_bytes(4));
        $identifierB = 'test:' . bin2hex(random_bytes(4));
        $this->identifiers[] = $identifierA;
        $this->identifiers[] = $identifierB;
        $limiter = new ApiRateLimiter($this->db);

        for ($i = 0; $i < 6; $i++) {
            $limiter->tooManyRequests($identifierA, 5);
        }

        // A's counter tripped; B, never called, must still be well under the limit.
        $this->assertFalse($limiter->tooManyRequests($identifierB, 5));
    }

    public function testPruneOldWindowsRemovesOnlyStaleRows(): void
    {
        $staleIdentifier = 'test:' . bin2hex(random_bytes(4));
        $freshIdentifier = 'test:' . bin2hex(random_bytes(4));
        $this->identifiers[] = $staleIdentifier;
        $this->identifiers[] = $freshIdentifier;

        $this->db->insert('api_rate_limits', [
            'identifier' => $staleIdentifier,
            'window_start' => date('Y-m-d H:i:00', time() - (2 * 86400)),
            'request_count' => 3,
        ]);
        $this->db->insert('api_rate_limits', [
            'identifier' => $freshIdentifier,
            'window_start' => date('Y-m-d H:i:00'),
            'request_count' => 3,
        ]);

        (new ApiRateLimiter($this->db))->pruneOldWindows();

        $stale = $this->db->fetchOne('SELECT id FROM ' . $this->db->table('api_rate_limits') . ' WHERE identifier = :id', ['id' => $staleIdentifier]);
        $fresh = $this->db->fetchOne('SELECT id FROM ' . $this->db->table('api_rate_limits') . ' WHERE identifier = :id', ['id' => $freshIdentifier]);

        $this->assertNull($stale);
        $this->assertNotNull($fresh);
    }
}
