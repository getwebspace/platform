<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240328110109 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('params', ['id' => false, 'primary_key' => 'name']);
        $table
            ->addColumn('name', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('value', 'text', ['default' => '', 'null' => false])
            ->create();
    }
}
