<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240412160716 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('catalog_attribute', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('title', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('address', 'string', ['limit' => 255, 'default' => '', 'null' => false])
            ->addColumn('type', 'string', ['limit' => 100, 'default' => 'string', 'null' => false])
            ->addColumn('group', 'string', ['limit' => 255, 'default' => 'string', 'null' => false])
            ->addColumn('is_filter', 'boolean', ['default' => true])
            ->addIndex(['address'], ['unique' => true])
            ->addIndex(['group'])
            ->create();

        $table = $this->table('catalog_attribute_category', ['id' => false, 'primary_key' => ['category_uuid', 'attribute_uuid']]);
        $table
            ->addColumn('category_uuid', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('attribute_uuid', 'char', ['limit' => 36, 'null' => false])
            ->addIndex('category_uuid')
            ->addIndex('attribute_uuid')
            ->addForeignKey('category_uuid', 'catalog_category', 'uuid', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('attribute_uuid', 'catalog_attribute', 'uuid', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $table = $this->table('catalog_attribute_product', ['id' => false, 'primary_key' => ['product_uuid', 'attribute_uuid']]);
        $table
            ->addColumn('product_uuid', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('attribute_uuid', 'char', ['limit' => 36, 'null' => false])
            ->addColumn('value', 'string', ['limit' => 1000, 'default' => '', 'null' => false])
            ->addIndex('product_uuid')
            ->addIndex('attribute_uuid')
            ->addForeignKey('product_uuid', 'catalog_product', 'uuid', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('attribute_uuid', 'catalog_attribute', 'uuid', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
    }
}
