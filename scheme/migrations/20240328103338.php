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
            ->addColumn('name', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('ext', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('type', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('size', 'integer', ['default' => 0, 'null' => false])
            ->addColumn('salt', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('hash', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('private', 'boolean', ['default' => 0, 'null' => false])
            ->addColumn('date', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->create();

        $table = $this->table('file_related');
        $table
            ->addColumn('file_uuid', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('entity_uuid', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('order', 'integer', ['default' => 1, 'null' => false])
            ->addColumn('comment', 'text', ['default' => '', 'null' => false])
            ->addColumn('object_type', 'string', ['limit' => 255, 'null' => false])
            ->addIndex('file_uuid')
            ->addIndex('entity_uuid')
            ->create();
    }
}
