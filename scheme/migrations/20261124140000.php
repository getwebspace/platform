<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20261124140000 extends AbstractMigration
{
    /**
     * Description and kind of a reference record
     *
     * `value` of a delivery is a list of services with conditions, so these two cannot live
     * in it. `description` is free text for any reference (a delivery, later a payment);
     * `kind` is the subtype inside a reference type, for a delivery: pickup, courier, carrier.
     */
    public function up(): void
    {
        $this->table('reference')
            ->addColumn('description', 'text', ['default' => ''])
            ->addColumn('kind', 'string', ['limit' => 32, 'default' => ''])
            ->update();
    }

    public function down(): void
    {
        $this->table('reference')
            ->removeColumn('kind')
            ->removeColumn('description')
            ->update();
    }
}
