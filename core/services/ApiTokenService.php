<?php

declare(strict_types=1);

namespace Stratum\Core;

/**
 * Personal API tokens (Stage 10). Lives in core/services/, not the users
 * module — Auth (core) needs to depend on this to resolve a Bearer token
 * into a user, and core can't depend on a module's own namespace, only
 * the reverse.
 */
final class ApiTokenService
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Generates a new token, stores only its hash, and returns the raw
     * value — the only moment it's ever available; the caller must show
     * it to the user immediately, it can never be retrieved again.
     *
     * @return array{id: int, token: string}
     */
    public function createToken(int $userId, string $name): array
    {
        $raw = 'strat_' . bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);

        $id = (int) $this->db->insert('api_tokens', [
            'user_id' => $userId,
            'token_hash' => $hash,
            'name' => $name,
            'last_used_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'revoked_at' => null,
        ]);

        return ['id' => $id, 'token' => $raw];
    }

    /** @return array<int, array<string, mixed>> newest first, active and revoked alike (so the UI can show revoked as such) */
    public function listForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('api_tokens') . '
             WHERE user_id = :user_id ORDER BY created_at DESC',
            ['user_id' => $userId]
        );
    }

    /** Scoped to the owner — a user can never revoke another user's token. */
    public function revoke(int $tokenId, int $userId): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('api_tokens') . '
             SET revoked_at = :now WHERE id = :id AND user_id = :user_id AND revoked_at IS NULL',
            ['now' => date('Y-m-d H:i:s'), 'id' => $tokenId, 'user_id' => $userId]
        );
    }

    /**
     * Resolves a raw Bearer token to its owning user id — the one method
     * Auth actually calls. Touches last_used_at on every successful use,
     * same "cheap observability" reasoning donation/dues confirmed_at
     * timestamps already establish. Returns null for anything invalid,
     * unknown, or revoked — deliberately not distinguishing why, same
     * posture Auth::attempt() already takes on a failed login.
     */
    public function resolveUserIdFromToken(string $rawToken): ?int
    {
        $hash = hash('sha256', $rawToken);

        $row = $this->db->fetchOne(
            'SELECT id, user_id FROM ' . $this->db->table('api_tokens') . '
             WHERE token_hash = :hash AND revoked_at IS NULL',
            ['hash' => $hash]
        );
        if ($row === null) {
            return null;
        }

        $this->db->execute(
            'UPDATE ' . $this->db->table('api_tokens') . ' SET last_used_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $row['id']]
        );

        return (int) $row['user_id'];
    }
}
