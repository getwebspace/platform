<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240702092511 extends AbstractMigration
{
    public function change(): void
    {
        // change user_group default value {} => []
        $table = $this->table('user_group');
        $table
            ->changeColumn('access', 'text', ['default' => '[]'])
            ->update();
    }
}
