<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20261109120000 extends AbstractMigration
{
    /**
     * Form spam protection
     *
     * Suspicious submissions are kept (a false positive must not lose a customer)
     * but flagged: `is_spam` hides them from the normal list and stops the e-mail,
     * `spam_reason` tells why they were flagged.
     */
    public function up(): void
    {
        $this->table('form_data')
            ->addColumn('is_spam', 'boolean', ['default' => false])
            ->addColumn('spam_reason', 'string', ['limit' => 255, 'default' => ''])
            ->addIndex('is_spam')
            ->update();
    }

    public function down(): void
    {
        $this->table('form_data')
            ->removeIndex('is_spam')
            ->removeColumn('spam_reason')
            ->removeColumn('is_spam')
            ->update();
    }
}
