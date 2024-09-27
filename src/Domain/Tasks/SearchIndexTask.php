<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Domain\AbstractService;
use App\Domain\AbstractTask;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\PublicationService;

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

        $services = [
            'catalog_product' => $this->container->get(CatalogProductService::class),
            'publication' => $this->container->get(PublicationService::class),
            'page' => $this->container->get(PageService::class),
        ];

        /**
         * @var string $name
         * @var AbstractService $service
         */
        foreach ($services as $name => $service) {
            $indexer = $tnt->createIndex("{$name}.index", true);
            $indexer->setPrimaryKey('uuid');
            $indexer->setInMemory(false);
            $indexer->setTokenizer(new \TeamTNT\TNTSearch\Support\Tokenizer());
            $indexer->setStemmer(new \TeamTNT\TNTSearch\Stemmer\NoStemmer());
            $indexer->setStopWords([]); // todo

            $offset = 0;
            $limit = 1000;

            do {
                $rows = $service->read([
                    'status' => \App\Domain\Casts\Catalog\Status::WORK,
                    'limit' => $limit,
                    'offset' => $limit * $offset,
                ]);

                foreach ($rows as $row) {
                    $fields = ['uuid', 'title', 'meta', 'description', 'extra', 'vendorcode', 'barcode', 'country', 'manufacturer', 'content'];
                    $columns = array_intersect_key($row->toArray(), array_flip($fields));

                    foreach ($columns as &$column) {
                        if (is_array($column)) {
                            $column = implode(' ', $column);
                        }

                        $column = strip_tags($column);
                        $column = trim($column);
                    }

                    $indexer->insert($columns);
                }

                ++$offset;
                $go = $rows->count() === $limit;
            } while ($go === true);

            $this->logger->info('SearchIndex: result', [
                'name' => $name,
                'total' => $indexer->totalDocumentsInCollection(),
            ]);
        }

        $this->container->get(\App\Application\PubSub::class)->publish('task:search:indexed');

        $this->setStatusDone();
    }
}
