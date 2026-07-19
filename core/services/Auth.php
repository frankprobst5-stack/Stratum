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

    public function __construct(
        private readonly Session $session,
        private readonly Database $db,
        private readonly AuthServiceInterface $identity,
        private readonly PermissionEngine $permissions
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

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $id = $this->session->get('user_id');
        if ($id === null) {
            return null;
        }

        $this->user = $this->identity->findById((int) $id);

        return $this->user;
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
