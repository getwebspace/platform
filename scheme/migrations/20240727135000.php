<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240727135000 extends AbstractMigration
{
    public function change(): void
    {
        // remove file private field
        $table = $this->table('file');
        $table
            ->removeColumn('private')
            ->update();
    }
}
