<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Application\Search;
use App\Domain\AbstractTask;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\Page;
use App\Domain\Models\Publication;
use Ramsey\Uuid\UuidInterface;

class SearchIndexTask extends AbstractTask
{
    public const TITLE = 'Search indexing';

    public function execute(array $params = []): \App\Domain\Models\Task
    {
        return parent::execute([]);
    }

    protected function action(array $args = []): void
    {
        $index = [];

        $pageService = $this->container->get(\App\Domain\Service\Page\PageService::class);
        foreach ($pageService->read() as $item) {
            /** @var Page $item */
            $index[] = $this->implode('page', $item->uuid, Search::getIndexedText([
                $item->title,
                $item->content,
                $item->meta['title'] ?? '',
                $item->meta['description'] ?? '',
                $item->meta['keywords'] ?? '',
            ], true));
        }

        $publicationService = $this->container->get(\App\Domain\Service\Publication\PublicationService::class);
        foreach ($publicationService->read() as $item) {
            /** @var Publication $item */
            $index[] = $this->implode('publication', $item->uuid, Search::getIndexedText([
                $item->title,
                $item->content['short'] ?? '',
                $item->content['full'] ?? '',
                $item->meta['title'] ?? '',
                $item->meta['description'] ?? '',
                $item->meta['keywords'] ?? '',
            ], true));
        }

//        $productService = $this->container->get(\App\Domain\Service\Catalog\ProductService::class);
//        foreach ($productService->read(['status' => \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK]) as $item) {
//            /** @var CatalogProduct $item */
//            $index[] = $this->implode('catalog_product', $item->getUuid(), Search::getIndexedText([
//                $item->getTitle(),
//                $item->getDescription(),
//                $item->getExtra(),
//                $item->getCountry(),
//                $item->getManufacturer(),
//                $item->getVendorCode(),
//                $item->getBarCode(),
//                $item->meta['title'] ?? '',
//                $item->meta['description'] ?? '',
//                $item->meta['keywords'] ?? '',
//            ], true));
//        }

        file_put_contents(Search::CACHE_FILE, implode(PHP_EOL, $index));

        $this->container->get(\App\Application\PubSub::class)->publish('task:search:indexed');

        $this->setStatusDone((string) count($index));
    }

    protected function implode(string $type, string $uuid, array $data): string
    {
        return $type . ':' . $uuid . ': ' . implode(' ', $data);
    }
}
