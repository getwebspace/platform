<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240328102256 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('task', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('title', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('action', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('progress', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0', 'null' => false])
            ->addColumn('status', 'string', ['limit' => 100, 'default' => 'queue', 'null' => false])
            ->addColumn('params', 'text', ['default' => '{}', 'null' => false])
            ->addColumn('output', 'string', ['limit' => 1000, 'default' => '', 'null' => false])
            ->addColumn('date', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex('title')
            ->addIndex('status')
            ->create();
    }
}
