<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240807122500 extends AbstractMigration
{
    public function change(): void
    {
        // remove old indexes and add new composite index
        $table = $this->table('user');
        $table->removeIndexByName('username')->update();
        $table->removeIndexByName('email')->update();
        $table->removeIndexByName('phone')->update();
        $table->addIndex(['username', 'email', 'phone'], ['unique' => true])->update();
    }
}
