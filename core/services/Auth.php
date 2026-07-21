<?php

declare(strict_types=1);

namespace Stratum\Core;

final class Auth
{
    private const MAX_ATTEMPTS = 10;
    private const WINDOW_MINUTES = 15;

    /** @var array<string, mixed>|null */
    private ?array $user = null;
    private bool $resolved = false;

    /**
     * $request/$apiTokens are nullable — bin/cron.php builds an Auth with
     * no real HTTP request at all (deliberately, per its own docblock:
     * "minus the HTTP-specific pieces"), so Bearer-token resolution just
     * never applies there rather than forcing a fake Request into a
     * context that has none.
     */
    public function __construct(
        private readonly Session $session,
        private readonly Database $db,
        private readonly AuthServiceInterface $identity,
        private readonly PermissionEngine $permissions,
        private readonly ?Request $request = null,
        private readonly ?ApiTokenService $apiTokens = null
    ) {
    }

    public function attempt(string $login, string $password, string $ip): bool
    {
        if ($this->tooManyAttempts($login, $ip)) {
            return false;
        }

        $user = $this->identity->findByCredentials($login, $password);
        $this->recordAttempt($login, $ip, $user !== null);

        if ($user === null) {
            return false;
        }

        $this->session->regenerate();
        $this->session->set('user_id', $user['id']);
        $this->user = $user;
        $this->resolved = true;

        return true;
    }

    public function logout(): void
    {
        $this->session->remove('user_id');
        $this->session->regenerate();
        $this->user = null;
        $this->resolved = true;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /** Anonymous users never have a capability — guest-role resolution is deferred, see docs/roadmap.md Stage 2. */
    public function can(string $capabilityKey, ?string $scopeType = null, ?int $scopeId = null): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        return $this->permissions->userCan((int) $user['id'], $capabilityKey, $scopeType, $scopeId);
    }

    /**
     * Resolves the current user from a Bearer token first when one is
     * present on the request, falling back to the session otherwise.
     * Bearer wins over session (not the reverse) so there is never an
     * ambiguity about which credential actually authorized a request: a
     * plain web request never carries an Authorization header, so this
     * only changes behavior for the one case that matters — a request
     * carrying both a session cookie and an explicit Bearer token, where
     * the explicit, deliberately-presented credential should decide, not
     * whichever one happens to resolve first. Every capability check
     * downstream (`can()`) is identical either way, since both paths
     * converge on the same $this->user array. See
     * core/api/controllers/ApiController.php's guard() docblock and
     * docs/roadmap.md's Stage 10 security-audit entry.
     *
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;

        $fromBearer = $this->userFromBearerToken();
        if ($fromBearer !== null) {
            $this->user = $fromBearer;

            return $this->user;
        }

        $id = $this->session->get('user_id');
        $this->user = $id !== null ? $this->identity->findById((int) $id) : null;

        return $this->user;
    }

    /** @return array<string, mixed>|null */
    private function userFromBearerToken(): ?array
    {
        if ($this->request === null || $this->apiTokens === null) {
            return null;
        }

        $header = $this->request->server('HTTP_AUTHORIZATION') ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $rawToken = trim(substr($header, 7));
        if ($rawToken === '') {
            return null;
        }

        $userId = $this->apiTokens->resolveUserIdFromToken($rawToken);

        return $userId !== null ? $this->identity->findById($userId) : null;
    }

    private function tooManyAttempts(string $identifier, string $ip): bool
    {
        $table = $this->db->table('login_attempts');
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60);

        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS attempts FROM {$table}
             WHERE succeeded = 0 AND created_at >= :since AND (identifier = :identifier OR ip_address = :ip)",
            ['since' => $since, 'identifier' => $identifier, 'ip' => $ip]
        );

        return $row !== null && (int) $row['attempts'] >= self::MAX_ATTEMPTS;
    }

    private function recordAttempt(string $identifier, string $ip, bool $succeeded): void
    {
        $this->db->insert('login_attempts', [
            'identifier' => $identifier,
            'ip_address' => $ip,
            'succeeded' => $succeeded ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
