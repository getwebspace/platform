<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240328110153 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('form', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('title', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('address', 'string', ['limit' => 1000, 'default' => ''])
            ->addColumn('template', 'text', ['default' => ''])
            ->addColumn('templateFile', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('recaptcha', 'boolean', ['default' => true])
            ->addColumn('authorSend', 'boolean', ['default' => false])
            ->addColumn('origin', 'text', ['default' => '{}'])
            ->addColumn('mailto', 'text', ['default' => '{}'])
            ->addColumn('duplicate', 'string', ['limit' => 255, 'default' => ''])
            ->addIndex(['address'], ['unique' => true])
            ->create();

        $table = $this->table('form_data', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('form_uuid', 'char', ['limit' => 36, 'default' => '00000000-0000-0000-0000-000000000000'])
            ->addForeignKey('form_uuid', 'form', 'uuid', ['delete' => 'CASCADE'])
            ->addColumn('data', 'text', ['default' => '{}'])
            ->addColumn('message', 'text', ['default' => ''])
            ->addColumn('date', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['form_uuid'])
            ->create();
    }
}
