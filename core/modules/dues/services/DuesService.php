<?php

declare(strict_types=1);

namespace Stratum\Modules\Dues;

use Stratum\Core\Database;
use Stratum\Core\PermissionEngine;

final class DuesService
{
    /** Scope namespace for the auto-provisioned per-plan premium role — same pattern OrgSpaceService's officer roles and forum's per-board moderator roles already established. */
    private const PREMIUM_SCOPE = 'dues_plan';

    public function __construct(
        private readonly Database $db,
        private readonly PermissionEngine $permissions
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function listPlans(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM ' . $this->db->table('dues_plans');
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY name';

        return $this->db->fetchAll($sql);
    }

    /** @return array<string, mixed>|null */
    public function findPlan(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('dues_plans') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /** True on success; false if the payment link's scheme isn't http/https. */
    public function createPlan(
        string $name,
        string $description,
        string $amount,
        string $currencyCode,
        string $period,
        string $paymentUrl,
        bool $isPremium = false,
        ?string $grantsCapabilityKey = null
    ): bool {
        $scheme = strtolower((string) parse_url($paymentUrl, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('dues_plans', [
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'amount' => $amount,
            'currency_code' => $currencyCode !== '' ? strtoupper($currencyCode) : 'USD',
            'period' => $period,
            'payment_url' => $paymentUrl,
            'is_active' => 1,
            'is_premium' => $isPremium ? 1 : 0,
            'grants_capability_key' => $isPremium && $grantsCapabilityKey !== null && $grantsCapabilityKey !== '' ? $grantsCapabilityKey : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return true;
    }

    public function setPlanActive(int $planId, bool $isActive): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('dues_plans') . ' SET is_active = :is_active, updated_at = :now WHERE id = :id',
            ['is_active' => $isActive ? 1 : 0, 'now' => date('Y-m-d H:i:s'), 'id' => $planId]
        );
    }

    /**
     * Records intent-to-pay for $userId on $planId — a no-op if a pending
     * record already exists for that user+plan, so repeat clicks don't
     * pile up duplicate rows.
     */
    public function recordIntent(int $planId, int $userId): void
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('dues_payments') . '
             WHERE plan_id = :plan_id AND user_id = :user_id AND status = :status',
            ['plan_id' => $planId, 'user_id' => $userId, 'status' => 'pending']
        );

        if ($existing !== null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('dues_payments', [
            'plan_id' => $planId,
            'user_id' => $userId,
            'status' => 'pending',
            'amount_paid' => null,
            'period_label' => null,
            'notes' => null,
            'recorded_by' => null,
            'confirmed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * For a premium plan, this also computes `expires_at` (based on the
     * plan's period) and grants the payer the plan's configured
     * capability via an auto-provisioned scoped role — same lazy-create
     * pattern org_spaces' officer roles and forum's per-board moderator
     * roles already established, not a new access-control mechanism.
     */
    public function confirmPayment(int $paymentId, int $recordedBy, string $amountPaid, string $periodLabel, string $notes): void
    {
        $payment = $this->findPayment($paymentId);
        if ($payment === null) {
            return;
        }

        $plan = $this->findPlan((int) $payment['plan_id']);
        $isPremium = $plan !== null && (bool) $plan['is_premium'];
        $expiresAt = $isPremium ? $this->computeExpiry((string) $plan['period']) : null;

        $this->db->execute(
            'UPDATE ' . $this->db->table('dues_payments') . '
             SET status = :status, amount_paid = :amount_paid, period_label = :period_label,
                 notes = :notes, recorded_by = :recorded_by, confirmed_at = :confirmed_at,
                 expires_at = :expires_at, updated_at = :now
             WHERE id = :id',
            [
                'status' => 'paid',
                'amount_paid' => $amountPaid,
                'period_label' => $periodLabel !== '' ? $periodLabel : null,
                'notes' => $notes !== '' ? $notes : null,
                'recorded_by' => $recordedBy,
                'confirmed_at' => date('Y-m-d H:i:s'),
                'expires_at' => $expiresAt,
                'now' => date('Y-m-d H:i:s'),
                'id' => $paymentId,
            ]
        );

        if ($isPremium && $payment['user_id'] !== null) {
            $this->grantPremiumRole((int) $payment['user_id'], $plan);
        }
    }

    /** True if $userId currently holds a non-expired 'paid' payment for $planId — the one thing dues never computed before this feature: not just payment history, but "are they current right now." */
    public function isCurrentOnPlan(int $userId, int $planId): bool
    {
        return $this->currentPaymentForPlan($userId, $planId) !== null;
    }

    /**
     * The specific payment row currently keeping $userId "current" on
     * $planId, if any — same WHERE shape as isCurrentOnPlan(), returning
     * the row instead of a bool so a caller can show its `expires_at`.
     * Compares against MySQL's own NOW(), never a PHP-computed date —
     * the same timezone-safety rule this app adopted after a real bug
     * during scheduled article publishing.
     *
     * @return array<string, mixed>|null
     */
    public function currentPaymentForPlan(int $userId, int $planId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('dues_payments') . '
             WHERE user_id = :user_id AND plan_id = :plan_id AND status = :status
             AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY confirmed_at DESC LIMIT 1',
            ['user_id' => $userId, 'plan_id' => $planId, 'status' => 'paid']
        );
    }

    /**
     * Lazily creates (or finds) the per-plan premium role and grants it
     * the plan's configured capability, exactly once — subsequent calls
     * for the same plan just return the existing role. Null if the plan
     * has no capability configured (a premium plan an admin hasn't
     * finished setting up yet); nothing to grant, so no role is created.
     *
     * The ROLE itself is scoped (`self::PREMIUM_SCOPE`/$planId) purely
     * for matrix-exclusion bookkeeping — the same reason org_spaces'
     * officer roles and forum's per-board moderator roles are scoped
     * (migration 003's own docblock). **The capability GRANT is
     * deliberately site-wide** (`grant()` called with no scope args),
     * not scoped to the same dues_plan/$planId pair — a real bug caught
     * during live testing, not designed correctly the first time: most
     * capabilities in this app (`forms.manage`, `articles.manage`,
     * etc.) are checked via a plain scope-less `Auth::can($key)`, and
     * `PermissionEngine::userCan()`'s own documented behavior is that a
     * *scoped* grant only ever satisfies a check that passes that exact
     * same scope — a scoped grant here would silently never actually
     * unlock anything for a normal, non-scope-aware capability. Only
     * capabilities that are themselves inherently scoped (e.g.
     * `forum.moderate` checked with a specific board id) would ever
     * need a scoped grant, which isn't this feature's use case.
     *
     * @param array<string, mixed> $plan
     * @return array<string, mixed>|null
     */
    public function premiumRoleForPlan(array $plan): ?array
    {
        $planId = (int) $plan['id'];
        $existing = $this->permissions->findRoleForScope(self::PREMIUM_SCOPE, $planId);
        if ($existing !== null) {
            return $existing;
        }

        if (empty($plan['grants_capability_key'])) {
            return null;
        }

        $capability = $this->permissions->findCapabilityByKey((string) $plan['grants_capability_key']);
        if ($capability === null) {
            return null;
        }

        $roleId = $this->permissions->createRole(
            "Premium — {$plan['name']} (#{$planId})",
            self::PREMIUM_SCOPE,
            $planId
        );
        $this->permissions->grant((int) $roleId, (int) $capability['id']);

        return $this->permissions->findRoleForScope(self::PREMIUM_SCOPE, $planId);
    }

    /**
     * Revokes the premium role from anyone whose most recent payment for
     * a premium plan has lapsed — called from `cron.daily` (see
     * dues' Module.php), the same "revisit once a day, compare against
     * MySQL's own NOW()" shape scheduled article publishing already
     * established, not a new scheduling mechanism.
     */
    public function revokeExpiredPremiumMemberships(): int
    {
        $premiumPlans = $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('dues_plans') . ' WHERE is_premium = 1'
        );

        $revoked = 0;
        foreach ($premiumPlans as $plan) {
            $planId = (int) $plan['id'];
            $role = $this->permissions->findRoleForScope(self::PREMIUM_SCOPE, $planId);
            if ($role === null) {
                continue;
            }

            foreach ($this->permissions->usersInRole((int) $role['id']) as $userId) {
                if (!$this->isCurrentOnPlan($userId, $planId)) {
                    $this->permissions->removeRoleFromUser($userId, (int) $role['id']);
                    $revoked++;
                }
            }
        }

        return $revoked;
    }

    /** @param array<string, mixed> $plan */
    private function grantPremiumRole(int $userId, array $plan): void
    {
        $role = $this->premiumRoleForPlan($plan);
        if ($role !== null) {
            $this->permissions->addRoleToUser($userId, (int) $role['id']);
        }
    }

    /** 'monthly' -> +30 days, 'annual' -> +365 days, anything else (incl. 'one_time') -> never expires. */
    private function computeExpiry(string $period): ?string
    {
        $days = match ($period) {
            'monthly' => 30,
            'annual' => 365,
            default => null,
        };

        return $days !== null ? date('Y-m-d H:i:s', strtotime("+{$days} days")) : null;
    }

    /** @return array<string, mixed>|null */
    public function findPayment(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('dues_payments') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @return array<int, array<string, mixed>> a user's own payment records for one plan, newest first */
    public function listPaymentsForUserAndPlan(int $userId, int $planId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('dues_payments') . '
             WHERE user_id = :user_id AND plan_id = :plan_id ORDER BY created_at DESC',
            ['user_id' => $userId, 'plan_id' => $planId]
        );
    }

    /**
     * @return array<int, array<string, mixed>> pending records across all plans,
     *     joined with plan name only — the payer's display name is resolved by
     *     the controller via AuthService::findById() (soft-delete-aware "Unknown"
     *     fallback, same pattern as forum/org_spaces), not joined here, since a
     *     raw SQL join can't see past deleted_at the way that service does.
     */
    public function listPending(): array
    {
        return $this->listPaymentsByStatus('pending');
    }

    /** @return array<int, array<string, mixed>> paid records across all plans, joined with plan name */
    public function listPaid(): array
    {
        return $this->listPaymentsByStatus('paid');
    }

    /** @return array<int, array<string, mixed>> */
    private function listPaymentsByStatus(string $status): array
    {
        $paymentsTable = $this->db->table('dues_payments');
        $plansTable = $this->db->table('dues_plans');

        return $this->db->fetchAll(
            "SELECT p.*, pl.name AS plan_name
             FROM {$paymentsTable} p
             JOIN {$plansTable} pl ON pl.id = p.plan_id
             WHERE p.status = :status
             ORDER BY p.created_at DESC",
            ['status' => $status]
        );
    }
}
