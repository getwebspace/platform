<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20250226101251 extends AbstractMigration
{
    /**
     * Remove params.value nullable
     *
     * changeColumn is not auto-reversible, so up/down are explicit,
     * otherwise `rollback -t 0` stops here and leaves the schema in place
     */
    public function up(): void
    {
        $table = $this->table('params');
        $table
            ->changeColumn('value', 'text', ['null' => false, 'default' => ''])
            ->update();
    }

    public function down(): void
    {
        $table = $this->table('params');
        $table
            ->changeColumn('value', 'text', ['null' => true, 'default' => ''])
            ->update();
    }
}
