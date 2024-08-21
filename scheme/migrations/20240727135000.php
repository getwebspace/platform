<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240727135000 extends AbstractMigration
{
    public function up(): void
    {
        // remove file private field
        $table = $this->table('file');
        $table
            ->removeColumn('private')
            ->update();
    }

    public function down(): void
    {
        // revert file private field
        $table = $this->table('file');
        $table
            ->addColumn('private', 'boolean', ['default' => 0])
            ->update();
    }
}
