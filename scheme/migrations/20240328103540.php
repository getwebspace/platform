<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240328103540 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('user_group', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('title', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('description', 'text', ['default' => ''])
            ->addColumn('access', 'text', ['default' => '{}'])
            ->create();

        $table = $this->table('user', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('group_uuid', 'char', ['limit' => 36, 'null' => true])
            ->addForeignKey('group_uuid', 'user_group', 'uuid', ['delete' => 'CASCADE'])
            ->addColumn('username', 'string', ['limit' => 64, 'default' => ''])
            ->addColumn('email', 'string', ['limit' => 120, 'default' => ''])
            ->addColumn('phone', 'string', ['limit' => 25, 'default' => ''])
            ->addColumn('password', 'string', ['limit' => 140, 'default' => ''])
            ->addColumn('firstname', 'string', ['limit' => 64, 'default' => ''])
            ->addColumn('lastname', 'string', ['limit' => 64, 'default' => ''])
            ->addColumn('patronymic', 'string', ['limit' => 64, 'default' => ''])
            ->addColumn('birthdate', 'date', ['default' => null, 'null' => true])
            ->addColumn('gender', 'string', ['limit' => 64, 'default' => ''])
            ->addColumn('country', 'string', ['limit' => 128, 'default' => ''])
            ->addColumn('city', 'string', ['limit' => 128, 'default' => ''])
            ->addColumn('address', 'string', ['limit' => 512, 'default' => ''])
            ->addColumn('postcode', 'string', ['limit' => 32, 'default' => ''])
            ->addColumn('additional', 'string', ['limit' => 1000, 'default' => ''])
            ->addColumn('allow_mail', 'boolean', ['default' => true])
            ->addColumn('company', 'text', ['default' => '{}'])
            ->addColumn('legal', 'text', ['default' => '{}'])
            ->addColumn('messenger', 'text', ['default' => '{}'])
            ->addColumn('status', 'string', ['limit' => 100, 'default' => 'work'])
            ->addColumn('register', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('change', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('website', 'string', ['limit' => 128, 'default' => ''])
            ->addColumn('source', 'string', ['limit' => 512, 'default' => ''])
            ->addColumn('auth_code', 'string', ['limit' => 12, 'default' => '', 'null' => false])
            ->addColumn('language', 'string', ['limit' => 5, 'default' => '', 'null' => false])
            ->addColumn('external_id', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('token', 'text', ['default' => '[]', 'null' => false])
            ->create();

        $table = $this->table('user_token', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('user_uuid', 'char', ['limit' => 36, 'null' => false])
            ->addForeignKey('user_uuid', 'user', 'uuid', ['delete' => 'CASCADE'])
            ->addColumn('unique', 'text', ['default' => '', 'null' => false])
            ->addColumn('comment', 'text', ['default' => '', 'null' => false])
            ->addColumn('ip', 'string', ['limit' => 16, 'default' => '', 'null' => false])
            ->addColumn('agent', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('date', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex('user_uuid')
            ->create();

        $table = $this->table('user_integration', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('user_uuid', 'char', ['limit' => 36, 'null' => false])
            ->addForeignKey('user_uuid', 'user', 'uuid', ['delete' => 'CASCADE'])
            ->addColumn('provider', 'text', ['default' => '', 'null' => false])
            ->addColumn('unique', 'string', ['limit' => 128, 'default' => '', 'null' => false])
            ->addColumn('date', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex('user_uuid')
            ->addIndex(['user_uuid', 'provider', 'unique'], ['unique' => true])
            ->create();

        $table = $this->table('user_subscriber', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('email', 'string', ['limit' => 120, 'default' => '', 'null' => false])
            ->addColumn('date', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addIndex(['email'], ['unique' => true])
            ->create();
    }
}
