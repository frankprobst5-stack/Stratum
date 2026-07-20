<?php

declare(strict_types=1);

namespace Tests\Core;

use Stratum\Core\ApiTokenService;
use Stratum\Core\Auth;
use Stratum\Core\Session;
use Stratum\Modules\Users\AuthService;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    private const PASSWORD = 'Test-Password-123!';

    /** @var string[] identifiers written to login_attempts by this test, cleaned up in tearDown() */
    private array $loginAttemptIdentifiers = [];

    protected function tearDown(): void
    {
        foreach ($this->loginAttemptIdentifiers as $identifier) {
            $this->db->execute(
                'DELETE FROM ' . $this->db->table('login_attempts') . ' WHERE identifier = :identifier',
                ['identifier' => $identifier]
            );
        }
        $this->loginAttemptIdentifiers = [];

        parent::tearDown();
    }

    public function testAttemptSucceedsWithCorrectCredentials(): void
    {
        $user = $this->createUser(['password' => self::PASSWORD]);
        $this->loginAttemptIdentifiers[] = $user['username'];

        $ok = $this->app->auth->attempt($user['username'], self::PASSWORD, '203.0.113.1');

        $this->assertTrue($ok);
        $this->assertTrue($this->app->auth->check());
        $this->assertSame((int) $user['id'], (int) $this->app->auth->user()['id']);
    }

    public function testAttemptFailsWithWrongPassword(): void
    {
        $user = $this->createUser(['password' => self::PASSWORD]);
        $this->loginAttemptIdentifiers[] = $user['username'];

        $ok = $this->app->auth->attempt($user['username'], 'wrong-password', '203.0.113.2');

        $this->assertFalse($ok);
        $this->assertFalse($this->app->auth->check());
    }

    public function testAttemptLocksOutAfterTooManyFailures(): void
    {
        $user = $this->createUser(['password' => self::PASSWORD]);
        $this->loginAttemptIdentifiers[] = $user['username'];
        $ip = '203.0.113.3';

        // Auth::MAX_ATTEMPTS is 10 — nine failures still leave one attempt
        // before lockout, the tenth failure is the one that trips it.
        for ($i = 0; $i < 10; $i++) {
            $this->app->auth->attempt($user['username'], 'wrong-password', $ip);
        }

        // Even the *correct* password is now refused — tooManyAttempts()
        // short-circuits before credentials are even checked.
        $ok = $this->app->auth->attempt($user['username'], self::PASSWORD, $ip);

        $this->assertFalse($ok);
    }

    public function testCanReturnsFalseForGuest(): void
    {
        $this->assertFalse($this->app->auth->can('forum.reply'));
    }

    public function testCanReturnsTrueOnlyAfterGrant(): void
    {
        $user = $this->createUser();
        $app = $this->asUser($user);

        $this->assertFalse($app->auth->can('forum.reply'));

        $this->grantCapability((int) $user['id'], 'forum.reply');

        // can() -> PermissionEngine::userCan() queries fresh every call
        // (no caching layer), so the same Auth instance picks up the grant
        // immediately — unlike Auth::user() itself, which does cache the
        // resolved identity for the life of the object.
        $this->assertTrue($app->auth->can('forum.reply'));
    }

    public function testBearerTokenResolvesUser(): void
    {
        $user = $this->createUser();
        $apiTokens = new ApiTokenService($this->db);
        $created = $apiTokens->createToken((int) $user['id'], 'test token');

        $auth = $this->authWithBearerToken($created['token']);

        $this->assertTrue($auth->check());
        $this->assertSame((int) $user['id'], (int) $auth->user()['id']);
    }

    public function testInvalidBearerTokenResolvesNobody(): void
    {
        $auth = $this->authWithBearerToken('strat_' . bin2hex(random_bytes(32)));

        $this->assertFalse($auth->check());
    }

    public function testRevokedBearerTokenNoLongerResolves(): void
    {
        $user = $this->createUser();
        $apiTokens = new ApiTokenService($this->db);
        $created = $apiTokens->createToken((int) $user['id'], 'test token');
        $apiTokens->revoke($created['id'], (int) $user['id']);

        $auth = $this->authWithBearerToken($created['token']);

        $this->assertFalse($auth->check());
    }

    private function authWithBearerToken(string $rawToken): Auth
    {
        $request = $this->makeRequest('GET', '/api/v1/articles', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $rawToken,
        ]);

        return new Auth(
            new Session(false),
            $this->db,
            new AuthService($this->db),
            $this->app->permissions,
            $request,
            new ApiTokenService($this->db)
        );
    }
}
