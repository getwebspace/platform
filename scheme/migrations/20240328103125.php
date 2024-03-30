<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240328103125 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('guestbook', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('name', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('email', 'string', ['limit' => 120, 'default' => ''])
            ->addColumn('message', 'text', ['default' => ''])
            ->addColumn('response', 'text', ['default' => ''])
            ->addColumn('status', 'string', ['limit' => 100, 'default' => 'work'])
            ->addColumn('date', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();
    }
}
