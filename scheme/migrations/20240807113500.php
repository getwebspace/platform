<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240807113500 extends AbstractMigration
{
    public function change(): void
    {
        // remove old indexes and add new composite index
        $table = $this->table('reference');
        $table->removeIndex('title')->update();
        $table->removeIndex('type')->update();
        $table->addIndex(['type', 'title'], ['unique' => true])->update();
    }
}
