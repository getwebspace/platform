<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240328102540 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('reference', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('type', 'string', ['limit' => 100, 'default' => 'text'])
            ->addColumn('title', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('value', 'text', ['default' => '{}'])
            ->addColumn('order', 'integer', ['default' => 1])
            ->addColumn('status', 'boolean', ['default' => 0])
            ->addIndex(['type', 'title'], ['unique' => true])
            ->create();
    }
}
