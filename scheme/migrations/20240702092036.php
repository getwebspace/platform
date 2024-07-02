<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20240702092036 extends AbstractMigration
{
    public function change(): void
    {
        // add user loyalty field
        $table = $this->table('user');
        $table
            ->addColumn('loyalty', 'text', ['default' => '[]', 'after' => 'source'])
            ->update();
    }
}
