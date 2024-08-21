<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240807122500 extends AbstractMigration
{
    public function up(): void
    {
        // remove old indexes and add new composite index
        $table = $this->table('user');
        $table->removeIndex('username')->save();
        $table->removeIndex('email')->save();
        $table->removeIndex('phone')->save();
        $table->addIndex(['username', 'email', 'phone'], ['unique' => true])->save();
    }

    public function down(): void
    {
        // remove new index
        $table = $this->table('user');
        $table
            ->removeIndex(['username', 'email', 'phone'])
            ->save();
    }
}
