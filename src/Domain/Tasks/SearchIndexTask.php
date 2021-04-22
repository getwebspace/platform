<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Application\Search;
use App\Domain\AbstractTask;
use App\Domain\Entities\Catalog\Product;
use App\Domain\Entities\Page;
use App\Domain\Entities\Publication;

class SearchIndexTask extends AbstractTask
{
    public const TITLE = 'Поисковая индексация';

    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        return parent::execute([]);
    }

    protected function action(array $args = []): void
    {
        $index = [];

        $pageService = \App\Domain\Service\Page\PageService::getWithContainer($this->container);
        foreach ($pageService->read() as $item) {
            /** @var Page $item */
            $index[] = $this->implode([
                'page',
                $item->getUuid(),
                Search::getIndexedText([
                    $item->getTitle(),
                    $item->getContent(),
                    $item->getMeta()['description'],
                ], true),
            ]);
        }

        $publicationService = \App\Domain\Service\Publication\PublicationService::getWithContainer($this->container);
        foreach ($publicationService->read() as $item) {
            /** @var Publication $item */
            $index[] = $this->implode([
                'publication',
                $item->getUuid(),
                Search::getIndexedText([
                    $item->getTitle(),
                    $item->getContent()['short'],
                    $item->getContent()['full'],
                    $item->getMeta()['description'],
                ], true),
            ]);
        }

        $productService = \App\Domain\Service\Catalog\ProductService::getWithContainer($this->container);
        foreach ($productService->read() as $item) {
            /** @var Product $item */
            $index[] = $this->implode([
                'catalog_product',
                $item->getUuid(),
                Search::getIndexedText([
                    $item->getTitle(),
                    $item->getDescription(),
                    $item->getExtra(),
                    $item->getMeta()['description'],
                ], true),
            ]);
        }

        file_put_contents(Search::CACHE_FILE, implode(PHP_EOL, $index));

        $this->setStatusDone((string) count($index));
    }

    protected function implode(array $array): string
    {
        return $array[0] . ':' . $array[1] . ': ' . $array[2];
    }
}
