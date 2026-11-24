<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20261124120000 extends AbstractMigration
{
    /**
     * Refresh token rotation with a short grace
     *
     * Each refresh replaces the token's `unique`. Two requests that carry the same expired
     * access token reach the refresh almost together: the second one presents a token that
     * was just rotated and would sign the user out. The replaced value is kept in `previous`
     * and is accepted for a few seconds more.
     */
    public function up(): void
    {
        $this->table('user_token')
            ->addColumn('previous', 'string', ['limit' => 64, 'default' => ''])
            ->addIndex('previous')
            ->update();
    }

    public function down(): void
    {
        $this->table('user_token')
            ->removeIndex('previous')
            ->removeColumn('previous')
            ->update();
    }
}
