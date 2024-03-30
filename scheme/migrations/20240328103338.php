<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240328103338 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('file', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('name', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('ext', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('type', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('size', 'integer', ['default' => 0])
            ->addColumn('salt', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('hash', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('private', 'boolean', ['default' => 0])
            ->addColumn('date', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();

        $table = $this->table('file_related');
        $table
            ->addColumn('file_uuid', 'char', ['limit' => 36])
            ->addColumn('entity_uuid', 'char', ['limit' => 36])
            ->addColumn('order', 'integer', ['default' => 1])
            ->addColumn('comment', 'text', ['default' => ''])
            ->addColumn('object_type', 'string', ['limit' => 255])
            ->addIndex('file_uuid')
            ->addIndex('entity_uuid')
            ->create();
    }
}
