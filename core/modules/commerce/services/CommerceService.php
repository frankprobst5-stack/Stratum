<?php

declare(strict_types=1);

namespace Stratum\Modules\Commerce;

use Stratum\Core\Database;

final class CommerceService
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @return array<int, array<string, mixed>> products joined with their download's title, newest first */
    public function listProducts(bool $activeOnly = true): array
    {
        $productsTable = $this->db->table('commerce_products');
        $filesTable = $this->db->table('downloads_files');

        $sql = "SELECT p.*, f.title AS download_title, f.description AS download_description
                FROM {$productsTable} p
                JOIN {$filesTable} f ON f.id = p.download_file_id AND f.deleted_at IS NULL";
        if ($activeOnly) {
            $sql .= ' WHERE p.is_active = 1';
        }
        $sql .= ' ORDER BY p.created_at DESC';

        return $this->db->fetchAll($sql);
    }

    /** @return array<string, mixed>|null product joined with its download's title */
    public function findProduct(int $id): ?array
    {
        $productsTable = $this->db->table('commerce_products');
        $filesTable = $this->db->table('downloads_files');

        return $this->db->fetchOne(
            "SELECT p.*, f.title AS download_title, f.description AS download_description
             FROM {$productsTable} p
             JOIN {$filesTable} f ON f.id = p.download_file_id
             WHERE p.id = :id",
            ['id' => $id]
        );
    }

    /** True on success; false if the payment link's scheme isn't http/https. */
    public function createProduct(int $downloadFileId, string $price, string $paymentUrl): bool
    {
        $scheme = strtolower((string) parse_url($paymentUrl, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('commerce_products', [
            'download_file_id' => $downloadFileId,
            'price' => $price,
            'payment_url' => $paymentUrl,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return true;
    }

    public function setProductActive(int $productId, bool $isActive): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('commerce_products') . ' SET is_active = :is_active, updated_at = :now WHERE id = :id',
            ['is_active' => $isActive ? 1 : 0, 'now' => date('Y-m-d H:i:s'), 'id' => $productId]
        );
    }

    /**
     * Records intent-to-purchase for $userId on $productId — a no-op if a
     * pending record already exists for that user+product, so repeat
     * clicks don't pile up duplicate rows.
     */
    public function recordIntent(int $productId, int $userId): void
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('commerce_purchases') . '
             WHERE product_id = :product_id AND user_id = :user_id AND status = :status',
            ['product_id' => $productId, 'user_id' => $userId, 'status' => 'pending']
        );

        if ($existing !== null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('commerce_purchases', [
            'product_id' => $productId,
            'user_id' => $userId,
            'status' => 'pending',
            'amount' => null,
            'notes' => null,
            'recorded_by' => null,
            'confirmed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function confirmPurchase(int $purchaseId, int $recordedBy, string $amount, string $notes): void
    {
        $this->db->execute(
            'UPDATE ' . $this->db->table('commerce_purchases') . '
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
                'id' => $purchaseId,
            ]
        );
    }

    /** True once this user has a confirmed purchase for this product — a one-time purchase, so no expiry check needed (contrast with dues' currentPaymentForPlan()). */
    public function hasPurchased(int $userId, int $productId): bool
    {
        return $this->db->fetchOne(
            'SELECT id FROM ' . $this->db->table('commerce_purchases') . '
             WHERE user_id = :user_id AND product_id = :product_id AND status = :status LIMIT 1',
            ['user_id' => $userId, 'product_id' => $productId, 'status' => 'confirmed']
        ) !== null;
    }

    /** @return array<string, mixed>|null */
    public function findPurchase(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM ' . $this->db->table('commerce_purchases') . ' WHERE id = :id',
            ['id' => $id]
        );
    }

    /** @return array<int, array<string, mixed>> a user's own purchase records for one product, newest first */
    public function listPurchasesForUserAndProduct(int $userId, int $productId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM ' . $this->db->table('commerce_purchases') . '
             WHERE user_id = :user_id AND product_id = :product_id ORDER BY created_at DESC',
            ['user_id' => $userId, 'product_id' => $productId]
        );
    }

    /** @return array<int, array<string, mixed>> pending purchases across all products, joined with the download's title */
    public function listPending(): array
    {
        return $this->listPurchasesByStatus('pending');
    }

    /** @return array<int, array<string, mixed>> confirmed purchases across all products, joined with the download's title */
    public function listConfirmed(): array
    {
        return $this->listPurchasesByStatus('confirmed');
    }

    /** @return array<int, array<string, mixed>> */
    private function listPurchasesByStatus(string $status): array
    {
        $purchasesTable = $this->db->table('commerce_purchases');
        $productsTable = $this->db->table('commerce_products');
        $filesTable = $this->db->table('downloads_files');

        return $this->db->fetchAll(
            "SELECT pu.*, f.title AS download_title
             FROM {$purchasesTable} pu
             JOIN {$productsTable} p ON p.id = pu.product_id
             JOIN {$filesTable} f ON f.id = p.download_file_id
             WHERE pu.status = :status
             ORDER BY pu.created_at DESC",
            ['status' => $status]
        );
    }
}
