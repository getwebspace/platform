<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20261125130000 extends AbstractMigration
{
    /**
     * State of the scheduled jobs: when one ran last, how it ended, whether it is switched off
     */
    public function up(): void
    {
        $this->table('schedule', ['id' => false, 'primary_key' => 'key'])
            ->addColumn('key', 'string', ['limit' => 255])
            ->addColumn('schedule', 'string', ['limit' => 255, 'default' => ''])
            ->addColumn('last_run', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('last_success', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => ''])
            ->addColumn('error', 'text', ['null' => true, 'default' => null])
            ->addColumn('duration', 'float', ['default' => 0])
            ->addColumn('failures', 'integer', ['default' => 0])
            ->addColumn('enabled', 'boolean', ['default' => true])
            ->addColumn('run_now', 'boolean', ['default' => false])
            ->create();
    }

    public function down(): void
    {
        $this->table('schedule')->drop()->save();
    }
}
