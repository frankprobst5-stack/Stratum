<?php

declare(strict_types=1);

namespace Stratum\Modules\Ads;

use Stratum\Core\Database;

final class AdService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function listAdvertisers(): array
    {
        return $this->db->fetchAll('SELECT * FROM ' . $this->db->table('ad_advertisers') . ' ORDER BY name');
    }

    /** @return array<string, mixed>|null */
    public function findAdvertiser(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('ad_advertisers') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    public function createAdvertiser(string $name, string $contactName, string $contactEmail, string $notes): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('ad_advertisers', [
            'name' => $name,
            'contact_name' => $contactName !== '' ? $contactName : null,
            'contact_email' => $contactEmail !== '' ? $contactEmail : null,
            'notes' => $notes !== '' ? $notes : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<int, array<string, mixed>> campaigns joined with advertiser name */
    public function listCampaigns(): array
    {
        $campaignsTable = $this->db->table('ad_campaigns');
        $advertisersTable = $this->db->table('ad_advertisers');

        return $this->db->fetchAll(
            "SELECT c.*, a.name AS advertiser_name
             FROM {$campaignsTable} c
             JOIN {$advertisersTable} a ON a.id = c.advertiser_id
             ORDER BY c.created_at DESC"
        );
    }

    /** @return array<string, mixed>|null */
    public function findCampaign(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('ad_campaigns') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    public function createCampaign(int $advertiserId, string $name, ?string $startsAt, ?string $endsAt): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('ad_campaigns', [
            'advertiser_id' => $advertiserId,
            'name' => $name,
            'starts_at' => $startsAt !== '' ? $startsAt : null,
            'ends_at' => $endsAt !== '' ? $endsAt : null,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function setCampaignActive(int $campaignId, bool $isActive): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('ad_campaigns') . ' SET is_active = :is_active, updated_at = :now WHERE id = :id',
            ['is_active' => $isActive ? 1 : 0, 'now' => date('Y-m-d H:i:s'), 'id' => $campaignId]
        );
    }

    /** @return array<int, array<string, mixed>> banners joined with campaign + advertiser name */
    public function listBanners(): array
    {
        $bannersTable = $this->db->table('ad_banners');
        $campaignsTable = $this->db->table('ad_campaigns');
        $advertisersTable = $this->db->table('ad_advertisers');

        return $this->db->fetchAll(
            "SELECT b.*, c.name AS campaign_name, a.name AS advertiser_name
             FROM {$bannersTable} b
             JOIN {$campaignsTable} c ON c.id = b.campaign_id
             JOIN {$advertisersTable} a ON a.id = c.advertiser_id
             ORDER BY b.created_at DESC"
        );
    }

    /** @return array<string, mixed>|null */
    public function findBanner(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('ad_banners') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    public function createBanner(int $campaignId, string $zone, string $imageUrl, string $linkUrl, string $altText): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('ad_banners', [
            'campaign_id' => $campaignId,
            'zone' => $zone,
            'image_url' => $imageUrl,
            'link_url' => $linkUrl,
            'alt_text' => $altText,
            'is_active' => 1,
            'impression_count' => 0,
            'click_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function setBannerActive(int $bannerId, bool $isActive): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('ad_banners') . ' SET is_active = :is_active, updated_at = :now WHERE id = :id',
            ['is_active' => $isActive ? 1 : 0, 'now' => date('Y-m-d H:i:s'), 'id' => $bannerId]
        );
    }

    /**
     * One random active banner for $zone, from an active campaign whose
     * schedule window (if any) currently includes NOW() — MySQL's own
     * NOW(), never a PHP-computed date, same timezone-safety rule dues'
     * expiry checks already established. Increments the banner's
     * impression_count as a side effect of being selected for render,
     * matching links/downloads' "count on the read that matters" shape.
     *
     * @return array<string, mixed>|null
     */
    public function activeBannerForZone(string $zone): ?array
    {
        $bannersTable = $this->db->table('ad_banners');
        $campaignsTable = $this->db->table('ad_campaigns');

        $banner = $this->db->fetchOne(
            "SELECT b.* FROM {$bannersTable} b
             JOIN {$campaignsTable} c ON c.id = b.campaign_id
             WHERE b.zone = :zone AND b.is_active = 1 AND c.is_active = 1
               AND (c.starts_at IS NULL OR c.starts_at <= NOW())
               AND (c.ends_at IS NULL OR c.ends_at >= NOW())
             ORDER BY RAND() LIMIT 1",
            ['zone' => $zone]
        );

        if ($banner === null) {
            return null;
        }

        $this->db->execute(
            'UPDATE ' . $this->db->table('ad_banners') . ' SET impression_count = impression_count + 1 WHERE id = :id',
            ['id' => $banner['id']]
        );

        return $banner;
    }

    public function incrementClickCount(int $bannerId): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('ad_banners') . ' SET click_count = click_count + 1 WHERE id = :id',
            ['id' => $bannerId]
        );
    }

    /** Live-computed, never stored — same "compute don't cache" discipline as donations' raisedAmount(). 0.0 if there have been no impressions yet. */
    public function clickThroughRate(array $banner): float
    {
        $impressions = (int) $banner['impression_count'];
        if ($impressions === 0) {
            return 0.0;
        }

        return ((int) $banner['click_count']) / $impressions * 100;
    }
}
