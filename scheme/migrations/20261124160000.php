<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20261124160000 extends AbstractMigration
{
    /**
     * Title of a product among its variants ("Blue", "XL", "500 ml")
     *
     * Variants are products that share an address; when this is empty the shop falls back to
     * the attribute that tells them apart, then to the vendor code.
     */
    public function up(): void
    {
        $this->table('catalog_product')
            ->addColumn('variant_title', 'string', ['limit' => 255, 'default' => ''])
            ->update();
    }

    public function down(): void
    {
        $this->table('catalog_product')
            ->removeColumn('variant_title')
            ->update();
    }
}
