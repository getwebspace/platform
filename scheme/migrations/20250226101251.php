<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20250226101251 extends AbstractMigration
{
    /**
     * Remove params.value nullable
     */
    public function change(): void
    {
        $table = $this->table('params');
        $table->changeColumn('value', 'text', ['null' => false, 'default' => ''])
            ->update();
    }
}
