<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240330121912 extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('catalog_product', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('title', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('type', 'string', ['limit' => 100, 'default' => 'product'])
            ->addColumn('description', 'text', ['default' => ''])
            ->addColumn('extra', 'text', ['default' => ''])
            ->addColumn('address', 'string', ['limit' => 1000, 'default' => ''])
            ->addColumn('category_uuid', 'char', ['limit' => 36])
            ->addForeignKey('category_uuid', 'catalog_category', 'uuid', ['delete' => 'CASCADE'])
            ->addColumn('vendorcode', 'text', ['default' => ''])
            ->addColumn('barcode', 'text', ['default' => ''])
            ->addColumn('tax', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0'])
            ->addColumn('priceFirst', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0'])
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0'])
            ->addColumn('priceWholesale', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0'])
            ->addColumn('priceWholesaleFrom', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0'])
            ->addColumn('discount', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0'])
            ->addColumn('special', 'boolean', ['default' => '0'])
            ->addColumn('dimension', 'text', ['default' => '{}'])
            ->addColumn('quantity', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '1'])
            ->addColumn('quantityMin', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '1'])
            ->addColumn('stock', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => '0'])
            ->addColumn('country', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('manufacturer', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('tags', 'text', ['default' => '{}'])
            ->addColumn('order', 'integer', ['default' => '1'])
            ->addColumn('status', 'string', ['limit' => 100, 'default' => 'work'])
            ->addColumn('date', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('meta', 'text', ['default' => '{}'])
            ->addColumn('external_id', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('export', 'string', ['limit' => 64, 'default' => 'manual'])
            ->addIndex(['address'])
            ->addIndex(['category_uuid'])
            ->addIndex(['country'])
            ->addIndex(['manufacturer'])
            ->addIndex(['order'])
            ->addIndex(['price', 'priceFirst', 'priceWholesale'])
            ->addIndex(['category_uuid', 'address', 'dimension', 'external_id'], ['unique' => true])
            ->create();
    }
}
