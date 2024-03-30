<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240330085412 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('catalog_category', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('title', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('description', 'text', ['default' => ''])
            ->addColumn('address', 'string', ['limit' => 1000, 'default' => ''])
            ->addColumn('parent_uuid', 'char', ['limit' => 36, 'default' => null, 'null' => true])
            ->addForeignKey('parent_uuid', 'catalog_category', 'uuid', ['delete' => 'CASCADE'])
            ->addColumn('pagination', 'integer', ['default' => 10])
            ->addColumn('children', 'boolean', ['default' => false])
            ->addColumn('hidden', 'boolean', ['default' => false])
            ->addColumn('order', 'integer', ['default' => 1])
            ->addColumn('status', 'string', ['limit' => 100, 'default' => 'work'])
            ->addColumn('sort', 'text', ['default' => '{}'])
            ->addColumn('meta', 'text', ['default' => '{}'])
            ->addColumn('template', 'text', ['default' => '{}'])
            ->addColumn('external_id', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('export', 'string', ['limit' => 64, 'default' => 'manual'])
            ->addColumn('system', 'string', ['limit' => 512, 'default' => ''])
            ->addIndex(['address'])
            ->addIndex(['order'])
            ->addIndex(['parent_uuid'])
            ->addIndex(['parent_uuid', 'address', 'external_id'], ['unique' => true])
            ->create();
    }
}
