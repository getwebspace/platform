<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class V20260831120000 extends AbstractMigration
{
    /**
     * Reviews and questions
     *
     * One table for every kind of user feedback attached to a publication or a
     * catalog product. The split lives in columns:
     *  - `type`        review | question
     *  - `entity_type` publication | catalog_product
     *  - `entity_uuid` the publication / product it belongs to
     *  - `parent_uuid` set on a reply to another review/question
     *
     * Files are connected through `file_related` (polymorphic, object_type =
     * App\Domain\Models\Review), the same way publications and products do it.
     */
    public function up(): void
    {
        $table = $this->table('review', ['id' => false, 'primary_key' => 'uuid']);
        $table
            ->addColumn('uuid', 'char', ['limit' => 36])
            ->addColumn('parent_uuid', 'char', ['limit' => 36, 'null' => true, 'default' => null])
            ->addColumn('user_uuid', 'char', ['limit' => 36, 'null' => true, 'default' => null])
            ->addColumn('type', 'string', ['limit' => 100, 'default' => 'review'])
            ->addColumn('entity_type', 'string', ['limit' => 100, 'default' => 'publication'])
            ->addColumn('entity_uuid', 'char', ['limit' => 36])
            ->addColumn('rating', 'integer', ['default' => 0])
            ->addColumn('message', 'text', ['default' => ''])
            ->addColumn('status', 'string', ['limit' => 100, 'default' => 'moderate'])
            ->addColumn('date', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['entity_type', 'entity_uuid'])
            ->addIndex('parent_uuid')
            ->addIndex('user_uuid')
            ->addIndex('type')
            ->addIndex('status')
            ->create();
    }

    public function down(): void
    {
        $this->table('review')->drop()->save();
    }
}
