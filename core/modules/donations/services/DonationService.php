<?php

declare(strict_types=1);

namespace Stratum\Modules\Donations;

use Stratum\Core\Database;

final class DonationService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function listCampaigns(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM ' . $this->db->table('donation_campaigns');
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY title';

        return $this->db->fetchAll($sql);
    }

    /** @return array<string, mixed>|null */
    public function findCampaign(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('donation_campaigns') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /** True on success; false if the payment link's scheme isn't http/https. */
    public function createCampaign(string $title, string $description, string $goalAmount, string $currencyCode, string $paymentUrl): bool
    {
        $scheme = strtolower((string) parse_url($paymentUrl, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('donation_campaigns', [
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'goal_amount' => $goalAmount,
            'currency_code' => $currencyCode !== '' ? strtoupper($currencyCode) : 'USD',
            'payment_url' => $paymentUrl,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return true;
    }

    public function setCampaignActive(int $campaignId, bool $isActive): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('donation_campaigns') . ' SET is_active = :is_active, updated_at = :now WHERE id = :id',
            ['is_active' => $isActive ? 1 : 0, 'now' => date('Y-m-d H:i:s'), 'id' => $campaignId]
        );
    }

    /** Computed live from confirmed contributions — never cached, so a future edit/correction is always reflected. */
    public function raisedAmount(int $campaignId): string
    {
        $row = $this->db->fetchOne(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM ' . $this->db->table('donation_contributions') . '
             WHERE campaign_id = :campaign_id AND status = :status',
            ['campaign_id' => $campaignId, 'status' => 'confirmed']
        );

        return (string) ($row['total'] ?? '0');
    }

    /**
     * Records intent-to-donate for $userId on $campaignId — a no-op if a
     * pending record already exists for that user+campaign, so repeat
     * clicks don't pile up duplicate rows.
     */
    public function recordIntent(int $campaignId, int $userId): void
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('donation_contributions') . '
             WHERE campaign_id = :campaign_id AND user_id = :user_id AND status = :status',
            ['campaign_id' => $campaignId, 'user_id' => $userId, 'status' => 'pending']
        );

        if ($existing !== null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('donation_contributions', [
            'campaign_id' => $campaignId,
            'user_id' => $userId,
            'donor_name' => null,
            'status' => 'pending',
            'amount' => null,
            'notes' => null,
            'recorded_by' => null,
            'confirmed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function confirmContribution(int $contributionId, int $recordedBy, string $amount, string $notes): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('donation_contributions') . '
             SET status = :status, amount = :amount, notes = :notes,
                 recorded_by = :recorded_by, confirmed_at = :confirmed_at, updated_at = :now
             WHERE id = :id',
            [
                'status' => 'confirmed',
                'amount' => $amount,
                'notes' => $notes !== '' ? $notes : null,
                'recorded_by' => $recordedBy,
                'confirmed_at' => date('Y-m-d H:i:s'),
                'now' => date('Y-m-d H:i:s'),
                'id' => $contributionId,
            ]
        );
    }

    /**
     * Admin-only direct entry — the admin is attesting they already
     * received this contribution (e.g. cash/check from a non-member), so
     * it's inserted already 'confirmed', no pending step. Attributed to
     * either $userId (an existing account) or $donorName (free text for
     * someone with no account) — exactly one of the two should be set by
     * the caller.
     */
    public function recordDirectContribution(int $campaignId, ?int $userId, ?string $donorName, int $recordedBy, string $amount, string $notes): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->insert('donation_contributions', [
            'campaign_id' => $campaignId,
            'user_id' => $userId,
            'donor_name' => $donorName !== null && $donorName !== '' ? $donorName : null,
            'status' => 'confirmed',
            'amount' => $amount,
            'notes' => $notes !== '' ? $notes : null,
            'recorded_by' => $recordedBy,
            'confirmed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** True once confirmed contributions meet or exceed the campaign's goal — the one thing raisedAmount()'s existing live progress bar doesn't already surface: a discrete crossing event a caller can notify on. */
    public function hasReachedGoal(int $campaignId): bool
    {
        $campaign = $this->findCampaign($campaignId);
        if ($campaign === null) {
            return false;
        }

        return (float) $this->raisedAmount($campaignId) >= (float) $campaign['goal_amount'];
    }

    /** @return array<string, mixed>|null */
    public function findContribution(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('donation_contributions') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @return array<int, array<string, mixed>> a user's own contribution records for one campaign, newest first */
    public function listContributionsForUserAndCampaign(int $userId, int $campaignId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('donation_contributions') . '
             WHERE user_id = :user_id AND campaign_id = :campaign_id ORDER BY created_at DESC',
            ['user_id' => $userId, 'campaign_id' => $campaignId]
        );
    }

    /**
     * @return array<int, array<string, mixed>> pending records across all campaigns, joined with campaign
     *     title only — the contributor's display name is resolved by the controller via
     *     AuthService::findById() (soft-delete-aware "Unknown" fallback, same as dues), not
     *     joined here, same lesson applied directly rather than re-derived.
     */
    public function listPending(): array
    {
        return $this->listContributionsByStatus('pending');
    }

    /** @return array<int, array<string, mixed>> confirmed records across all campaigns, joined with campaign title */
    public function listConfirmed(): array
    {
        return $this->listContributionsByStatus('confirmed');
    }

    /** @return array<int, array<string, mixed>> */
    private function listContributionsByStatus(string $status): array
    {
        $contributionsTable = $this->db->table('donation_contributions');
        $campaignsTable = $this->db->table('donation_campaigns');

        return $this->db->fetchAll(
            "SELECT c.*, cm.title AS campaign_title
             FROM {$contributionsTable} c
             JOIN {$campaignsTable} cm ON cm.id = c.campaign_id
             WHERE c.status = :status
             ORDER BY c.created_at DESC",
            ['status' => $status]
        );
    }
}
