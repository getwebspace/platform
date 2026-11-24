<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20261124150000 extends AbstractMigration
{
    /**
     * Stock status of a product ("In stock", "Pre-order", "Out of stock"...)
     *
     * Points to a `stock_status` reference record; empty when the product has none.
     */
    public function up(): void
    {
        $this->table('catalog_product')
            ->addColumn('stock_status_uuid', 'char', ['limit' => 36, 'null' => true, 'default' => null])
            ->addIndex('stock_status_uuid')
            ->update();
    }

    public function down(): void
    {
        $this->table('catalog_product')
            ->removeIndex('stock_status_uuid')
            ->removeColumn('stock_status_uuid')
            ->update();
    }
}
