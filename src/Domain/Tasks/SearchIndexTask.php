<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Application\Search;
use App\Domain\AbstractTask;
use App\Domain\Entities\Catalog\Product;
use App\Domain\Entities\Page;
use App\Domain\Entities\Publication;

class SearchIndexTask extends AbstractTask
{
    public const TITLE = 'Search indexing';

    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        return parent::execute([]);
    }

    protected function action(array $args = []): void
    {
        $index = [];

        $pageService = $this->container->get(\App\Domain\Service\Page\PageService::class);
        foreach ($pageService->read() as $item) {
            /** @var Page $item */
            $index[] = $this->implode([
                'page',
                $item->getUuid(),
                Search::getIndexedText([
                    $item->getTitle(),
                    $item->getContent(),
                    $item->getMeta()['title'],
                    $item->getMeta()['description'],
                    $item->getMeta()['keywords'],
                ], true),
            ]);
        }

        $publicationService = $this->container->get(\App\Domain\Service\Publication\PublicationService::class);
        foreach ($publicationService->read() as $item) {
            /** @var Publication $item */
            $index[] = $this->implode([
                'publication',
                $item->getUuid(),
                Search::getIndexedText([
                    $item->getTitle(),
                    $item->getContent()['short'],
                    $item->getContent()['full'],
                    $item->getMeta()['title'],
                    $item->getMeta()['description'],
                    $item->getMeta()['keywords'],
                ], true),
            ]);
        }

        $productService = $this->container->get(\App\Domain\Service\Catalog\ProductService::class);
        foreach ($productService->read(['status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK]) as $item) {
            /** @var Product $item */
            $index[] = $this->implode([
                'catalog_product',
                $item->getUuid(),
                Search::getIndexedText([
                    $item->getTitle(),
                    $item->getDescription(),
                    $item->getExtra(),
                    $item->getField1(),
                    $item->getField2(),
                    $item->getField3(),
                    $item->getField4(),
                    $item->getField5(),
                    $item->getCountry(),
                    $item->getManufacturer(),
                    $item->getVendorCode(),
                    $item->getBarCode(),
                    $item->getTags(),
                    $item->getMeta()['title'],
                    $item->getMeta()['description'],
                    $item->getMeta()['keywords'],
                ], true),
            ]);
        }

        file_put_contents(Search::CACHE_FILE, implode(PHP_EOL, $index));

        $this->container->get(\App\Application\PubSub::class)->publish('task:search:indexed');

        $this->setStatusDone((string) count($index));
    }

    protected function implode(array $array): string
    {
        return $array[0] . ':' . $array[1] . ': ' . $array[2];
    }
}
