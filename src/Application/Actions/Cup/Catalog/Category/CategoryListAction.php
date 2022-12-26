<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Category;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

class CategoryListAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $categories = $this->catalogCategoryService->read([
            'status' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK,
            'order' => [
                'order' => 'ASC',
                'title' => 'ASC',
            ],
        ]);

        $generator = function (Collection $level) use ($categories, &$generator) {
            foreach ($level->sortBy('order') as $item) {
                yield $item;
                yield from $generator($categories->where('parent', $item->uuid->toString()));
            }
        };
        $results = LazyCollection::make(function () use ($categories, $generator) {
            yield from $generator($categories->where('parent', \Ramsey\Uuid\Uuid::NIL));
        });

        return $this->respondWithTemplate('cup/catalog/category/index.twig', [
            'categories' => $results->flatten()->collect(),
            'fields' => $this->parameter(['catalog_category_field_1', 'catalog_category_field_2', 'catalog_category_field_3']),
        ]);
    }
}
