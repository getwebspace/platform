<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20261125120000 extends AbstractMigration
{
    /**
     * Last sign of life of a task: set when it starts and on every progress update.
     * A task that works is judged stuck by this, not by the date it was created.
     */
    public function up(): void
    {
        $this->table('task')
            ->addColumn('heartbeat', 'datetime', ['null' => true, 'default' => null])
            ->update();
    }

    public function down(): void
    {
        $this->table('task')
            ->removeColumn('heartbeat')
            ->update();
    }
}
