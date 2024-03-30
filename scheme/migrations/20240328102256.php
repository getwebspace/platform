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
            ->addColumn('title', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('action', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('progress', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0'])
            ->addColumn('status', 'string', ['limit' => 100, 'default' => 'queue'])
            ->addColumn('params', 'text', ['default' => '{}'])
            ->addColumn('output', 'string', ['limit' => 1000, 'default' => ''])
            ->addColumn('date', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex('title')
            ->addIndex('status')
            ->create();
    }
}
