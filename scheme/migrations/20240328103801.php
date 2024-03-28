<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240328103801 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('publication_category', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('title', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('address', 'string', ['limit' => 1000, 'default' => '', 'null' => false])
            ->addColumn('parent_uuid', 'char', ['limit' => 36, 'null' => true])
            ->addForeignKey('parent_uuid', 'publication_category', 'uuid', ['delete' => 'CASCADE'])
            ->addColumn('description', 'text', ['default' => '', 'null' => false])
            ->addColumn('pagination', 'integer', ['default' => 10, 'null' => false])
            ->addColumn('children', 'boolean', ['default' => true, 'null' => false])
            ->addColumn('public', 'boolean', ['default' => true, 'null' => false])
            ->addColumn('sort', 'text', ['default' => '{}', 'null' => false])
            ->addColumn('template', 'text', ['default' => '{}', 'null' => false])
            ->addColumn('meta', 'text', ['default' => '{}', 'null' => false])
            ->addIndex('parent_uuid')
            ->addIndex(['address'], ['unique' => true])
            ->create();

        $table = $this->table('publication', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('title', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('address', 'string', ['limit' => 1000, 'default' => '', 'null' => false])
            ->addColumn('category_uuid', 'char', ['limit' => 36])
            ->addForeignKey('category_uuid', 'publication_category', 'uuid', ['delete' => 'CASCADE'])
            ->addColumn('user_uuid', 'char', ['limit' => 36, 'null' => true])
            ->addForeignKey('user_uuid', 'user', 'uuid', ['delete' => 'CASCADE'])
            ->addColumn('content', 'text', ['default' => '{}', 'null' => false])
            ->addColumn('meta', 'text', ['default' => '{}', 'null' => false])
            ->addColumn('date', 'datetime', ['default' => 'CURRENT_TIMESTAMP', 'null' => false])
            ->addColumn('external_id', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addIndex('category_uuid')
            ->addIndex('user_uuid')
            ->addIndex(['address'], ['unique' => true])
            ->create();
    }
}
