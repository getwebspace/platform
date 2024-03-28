<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240328095759 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('page', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('title', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('address', 'string', ['limit' => 1000, 'default' => ''])
            ->addColumn('content', 'text', ['default' => ''])
            ->addColumn('type', 'string', ['limit' => 100])
            ->addColumn('template', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('meta', 'text', ['default' => '{}'])
            ->addColumn('date', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex('title')
            ->addIndex('address')
            ->create();
    }
}
