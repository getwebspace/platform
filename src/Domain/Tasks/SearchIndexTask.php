<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Domain\AbstractTask;

class SearchIndexTask extends AbstractTask
{
    public const TITLE = 'Search indexing';

    public function execute(array $params = []): \App\Domain\Models\Task
    {
        return parent::execute([]);
    }

    protected function action(array $args = []): void
    {
        /** @var \TeamTNT\TNTSearch\TNTSearch $tnt */
        $tnt = $this->container->get(\TeamTNT\TNTSearch\TNTSearch::class);

        $queries = [
            'page' => 'SELECT uuid, title, content, meta FROM page',
            'publication' => 'SELECT uuid, title, content, meta FROM publication',
            'catalog_product' => 'SELECT uuid, title, description, extra, vendorcode, barcode, country, manufacturer, tags, meta FROM catalog_product',
        ];
        $i = 0;

        // index tables
        foreach ($queries as $name => $query) {
            $indexer = $tnt->createIndex("{$name}.index", true);
            $indexer->setPrimaryKey('uuid');
            $indexer->query($query);
            $indexer->run();

            $this->setProgress(++$i, count($queries));
        }

        $this->container->get(\App\Application\PubSub::class)->publish('task:search:indexed');

        $this->setStatusDone();
    }
}
