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
            ->addColumn('title', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('address', 'string', ['limit' => 1000, 'default' => '', 'null' => false])
            ->addColumn('template', 'text', ['default' => '', 'null' => false])
            ->addColumn('templateFile', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('recaptcha', 'boolean', ['default' => true, 'null' => false])
            ->addColumn('authorSend', 'boolean', ['default' => false, 'null' => false])
            ->addColumn('origin', 'text', ['default' => '{}', 'null' => false])
            ->addColumn('mailto', 'text', ['default' => '{}', 'null' => false])
            ->addColumn('duplicate', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addIndex(['address'], ['unique' => true])
            ->create();

        $table = $this->table('form_data', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('form_uuid', 'char', ['limit' => 36, 'default' => '00000000-0000-0000-0000-000000000000', 'null' => false])
            ->addForeignKey('form_uuid', 'form', 'uuid', ['delete' => 'CASCADE'])
            ->addColumn('data', 'text', ['default' => '{}', 'null' => false])
            ->addColumn('message', 'text', ['default' => '', 'null' => false])
            ->addColumn('date', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['form_uuid'])
            ->create();
    }
}
