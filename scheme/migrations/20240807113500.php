<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240807113500 extends AbstractMigration
{
    public function up(): void
    {
        // remove old indexes and add new composite index
        $table = $this->table('reference');
        $table->removeIndex('title')->save();
        $table->removeIndex('type')->save();
        $table->addIndex(['type', 'title'], ['unique' => true])->save();
    }

    public function down(): void
    {
        // remove new index
        $table = $this->table('reference');
        $table
            ->removeIndex(['type', 'title'], ['unique' => true])
            ->save();
    }
}
