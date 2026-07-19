<?php

declare(strict_types=1);

use Stratum\Core\Database;
use Stratum\Core\Migration;

/**
 * Premium memberships — "a paid membership tier gated the same way any
 * other capability is, layered onto Stage 6a's `dues` plans rather than
 * a separate system" (Stage 7 spec). Before this, `dues` was pure
 * payment-history tracking with zero access-control tie-in anywhere —
 * confirmed by a full search of the module before writing this
 * migration, not assumed.
 *
 * `grants_capability_key` is a plain string, not a foreign key to
 * `capabilities.id` — capabilities are looked up by key everywhere else
 * in this app (`PermissionEngine::findCapabilityByKey()`), and a
 * capability's numeric id isn't stable/meaningful the way its key is
 * (module boot order determines insertion order, not anything an admin
 * should have to think about).
 *
 * `dues_payments.expires_at` is nullable and only meaningful when the
 * paid plan is premium — a one-time-period premium plan has no expiry
 * (NULL means "never lapses"), monthly/annual plans get a real computed
 * date at confirmation time (see DuesService::confirmPayment()).
 */
return new class implements Migration {
    public function up(Database $db): void
    {
        $db->execute('
            ALTER TABLE ' . $db->table('dues_plans') . '
            ADD COLUMN is_premium TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
            ADD COLUMN grants_capability_key VARCHAR(191) NULL AFTER is_premium
        ');

        $db->execute('
            ALTER TABLE ' . $db->table('dues_payments') . '
            ADD COLUMN expires_at DATETIME NULL AFTER confirmed_at,
            ADD KEY idx_dues_payments_expires (expires_at)
        ');
    }

    public function down(Database $db): void
    {
        $db->execute('ALTER TABLE ' . $db->table('dues_payments') . ' DROP KEY idx_dues_payments_expires, DROP COLUMN expires_at');
        $db->execute('ALTER TABLE ' . $db->table('dues_plans') . ' DROP COLUMN is_premium, DROP COLUMN grants_capability_key');
    }
};
