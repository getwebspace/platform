<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20260828140000 extends AbstractMigration
{
    /**
     * API keys, replacing the flat `entity_keys` parameter
     */
    public function up(): void
    {
        $table = $this->table('api_key', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('title', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('scopes', 'text', ['default' => '{}'])
            ->addColumn('is_full_access', 'boolean', ['default' => false])
            ->addColumn('status', 'string', ['limit' => 100, 'default' => 'work'])
            ->addColumn('date', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex('status')
            ->create();
    }

    public function down(): void
    {
        $this->table('api_key')->drop()->save();
    }
}
