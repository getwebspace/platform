<?php declare(strict_types=1);

namespace App\Application\Actions\Common\Catalog;

use App\Domain\Service\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Service\Catalog\Exception\ProductNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Slim\Psr7\Response;

class ListAction extends CatalogAction
{
    private array $attributes_filter = ['price', 'country', 'manufacturer', 'order', 'direction'];

    protected function action(): \Slim\Psr7\Response
    {
        $args = $this->parsePath();
        $categories = $this->catalogCategoryService->read([
            'status' => \App\Domain\Casts\Catalog\Status::WORK,
            'is_hidden' => false,
        ]);

        try {
            // Catalog main
            if ($buf = $this->prepareMain($args, $categories)) {
                return $buf;
            }

            // Category
            if ($buf = $this->prepareCategory($args, $categories)) {
                return $buf;
            }

            // Product
            if ($buf = $this->prepareProduct($args, $categories)) {
                return $buf;
            }
        } catch (CategoryNotFoundException|ProductNotFoundException $e) {
            // nothing
        }

        // 404
        return $this->respond('p404.twig')->withStatus(404);
    }

    private function parsePath(): array
    {
        $path = $this->request->getUri()->getPath();

        // remove starting prefix /catalog
        if (str_starts_with($path, '/catalog')) {
            $path = substr($path, strlen('/catalog'));
        }

        $parts = explode('/', ltrim($path, '/'));
        $offset = 0;

        if ($parts && ctype_digit(end($parts))) {
            $offset = (int) array_pop($parts);
        }

        return [
            'address' => implode('/', $parts),
            'offset' => $offset,
        ];
    }

    protected function prepareMain(array $args, Collection $categories): ?Response
    {
        if ($args['address'] === '') {
            // products
            $products = \App\Domain\Models\CatalogProduct::query();
            $products->select('cp.*');
            $products->from('catalog_product as cp');
            $products->leftJoin('catalog_category as cc', 'cp.category_uuid', '=', 'cc.uuid');
            $products->where('cc.status', \App\Domain\Casts\Catalog\Status::WORK);
            $products->where('cc.is_hidden', false);
            $products->where('cp.status', \App\Domain\Casts\Catalog\Status::WORK);

            $count = $products->count();
            $all = $products->get();

            // attribute filter
            if (($params = array_diff_key($this->getParams(), array_flip($this->attributes_filter)))) {
                $attributes = $this->db->query();
                $attributes->select('address');
                $attributes->from('catalog_attribute as ca');
                $attributes->where('ca.is_filter', true);
                $attributes->whereIn('ca.address', array_keys($this->getParams()));
                $attributes = $attributes->get();

                if ($attributes->count()) {
                    $products->leftJoin('catalog_attribute_product as cap', 'cp.uuid', '=', 'cap.product_uuid');
                    $products->leftJoin('catalog_attribute as ca', 'ca.uuid', '=', 'cap.attribute_uuid');
                    $products->where(function (Builder $query) use ($attributes, $params): void {
                        foreach ($attributes as $attribute) {
                            $address = $attribute->address;
                            $value = $params[$attribute->address];

                            $query->orWhere(function (Builder $q) use ($address, $value): void {
                                $q->where('ca.address', $address)->where('cap.value', $value);
                            });
                        }
                    });

                    // group by all columns
                    $products->groupBy('cp.uuid', ...array_map(fn ($col) => "cp.{$col}", array_keys((new \App\Domain\Models\CatalogProduct())->getAttributes())));
                    $products->havingRaw('COUNT(DISTINCT ca.address) = ' . $attributes->count());
                }
            }

            // price filter
            if (($price = $this->getParam('price', false)) !== false) {
                $price = array_merge(['min' => 0, 'max' => 0], $price);

                if ($price['min']) {
                    $params['price']['min'] = floatval($price['min']);
                    $products->where('cp.price', '>=', $params['price']['min']);
                }
                if ($price['max']) {
                    $params['price']['max'] = floatval($price['max']);
                    $products->where('cp.price', '<=', $params['price']['max']);
                }
            }

            // country filter
            if (($country = $this->getParam('country', false)) !== false) {
                $products->where('cp.country', str_escape($country));
            }

            // manufacturer filter
            if (($manufacturer = $this->getParam('manufacturer', false)) !== false) {
                $products->where('cp.manufacturer', str_escape($manufacturer));
            }

            // order
            if (($order = $this->getParam('order', false)) !== false) {
                $direction = mb_strtolower($this->getParam('direction', 'asc'));
                $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'ASC';

                if (in_array($order, ['title', 'price'], true)) {
                    $params['order'][$order] = $direction;
                    $products->orderBy('cp.' . $order, $direction);
                }
            } else {
                $sortBy = [
                    'by' => $this->parameter('catalog_sort_by', 'title'),
                    'direction' => $this->parameter('catalog_sort_direction', 'ASC'),
                ];

                $params['order'][$sortBy['by']] = mb_strtolower($sortBy['direction']);
                $products->orderBy('cp.' . $sortBy['by'], $sortBy['direction']);
            }

            $pagination = $this->parameter('catalog_category_pagination', 10);
            $filtered = $products->get()->forPage($args['offset'], $pagination);

            return $this->respond($this->parameter('catalog_category_template', 'catalog.category.twig'), [
                'categories' => $categories,
                'products' => [
                    'all' => $all,
                    'filtered' => $filtered,
                    'params' => $args,
                    'count' => $count,
                ],
                'pagination' => [
                    'count' => $count,
                    'page' => $pagination,
                    'offset' => $args['offset'],
                ],
            ]);
        }

        return null;
    }

    protected function prepareCategory(array $args, Collection $categories): ?Response
    {
        /**
         * @var \App\Domain\Models\CatalogCategory $category
         */
        $category = $categories->firstWhere('address', $args['address']);

        if ($category) {
            // products
            $products = \App\Domain\Models\CatalogProduct::query();
            $products->select('cp.*');
            $products->from('catalog_product as cp');
            $products->leftJoin('catalog_category as cc', 'cp.category_uuid', '=', 'cc.uuid');
            $products->where('cc.status', \App\Domain\Casts\Catalog\Status::WORK);
            $products->where('cc.is_hidden', false);
            $products->where('cp.status', \App\Domain\Casts\Catalog\Status::WORK);
            $products->whereIn('cp.category_uuid', $category->nested()->pluck('uuid'));

            $count = $products->count();
            $all = $products->get();

            // attribute filter
            if (($params = array_diff_key($this->getParams(), array_flip($this->attributes_filter)))) {
                $attributes = $this->db->query();
                $attributes->select('address');
                $attributes->from('catalog_attribute as ca');
                $attributes->where('ca.is_filter', true);
                $attributes->whereIn('ca.address', array_keys($this->getParams()));
                $attributes = $attributes->get();

                if ($attributes->count()) {
                    $products->leftJoin('catalog_attribute_product as cap', 'cp.uuid', '=', 'cap.product_uuid');
                    $products->leftJoin('catalog_attribute as ca', 'ca.uuid', '=', 'cap.attribute_uuid');
                    $products->where(function (Builder $query) use ($attributes, $params): void {
                        foreach ($attributes as $attribute) {
                            $address = $attribute->address;
                            $value = $params[$attribute->address];

                            $query->orWhere(function (Builder $q) use ($address, $value): void {
                                $q->where('ca.address', $address)->where('cap.value', $value);
                            });
                        }
                    });

                    // group by all columns
                    $products->groupBy('cp.uuid', ...array_map(fn ($col) => "cp.{$col}", array_keys((new \App\Domain\Models\CatalogProduct())->getAttributes())));
                    $products->havingRaw('COUNT(DISTINCT ca.address) = ' . $attributes->count());
                }
            }

            // price filter
            if (($price = $this->getParam('price', false)) !== false) {
                $price = array_merge(['min' => 0, 'max' => 0], $price);

                if ($price['min']) {
                    $params['price']['min'] = floatval($price['min']);
                    $products->where('cp.price', '>=', $params['price']['min']);
                }
                if ($price['max']) {
                    $params['price']['max'] = floatval($price['max']);
                    $products->where('cp.price', '<=', $params['price']['max']);
                }
            }

            // country filter
            if (($country = $this->getParam('country', false)) !== false) {
                $products->where('cp.country', str_escape($country));
            }

            // manufacturer filter
            if (($manufacturer = $this->getParam('manufacturer', false)) !== false) {
                $products->where('cp.manufacturer', str_escape($manufacturer));
            }

            // order
            if (($order = $this->getParam('order', false)) !== false) {
                $direction = mb_strtolower($this->getParam('direction', 'asc'));
                $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'ASC';

                if (in_array($order, ['title', 'price'], true)) {
                    $params['order'][$order] = $direction;
                    $products->orderBy('cp.' . $order, $direction);
                }
            } else {
                $sortBy = $category->sort;

                $params['order'][$sortBy['by']] = mb_strtolower($sortBy['direction']);
                $products->orderBy('cp.' . $sortBy['by'], $sortBy['direction']);
            }

            $filtered = $products->get()->forPage($args['offset'], $category->pagination);

            return $this->respond($category->template['category'], [
                'categories' => $categories,
                'category' => $category,
                'products' => [
                    'all' => $all,
                    'filtered' => $filtered,
                    'params' => $args,
                    'count' => $count,
                ],
                'pagination' => [
                    'count' => $count,
                    'page' => $category->pagination,
                    'offset' => $args['offset'],
                ],
            ]);
        }

        return null;
    }

    protected function prepareProduct(array $args, Collection $categories): ?Response
    {
        $products = $this->catalogProductService->read([
            'address' => [$args['address']],
            'status' => \App\Domain\Casts\Catalog\Status::WORK,
        ]);

        if ($products->count()) {
            /**
             * @var \App\Domain\Models\CatalogProduct $product
             */
            $product = $products->first();

            return $this->respond($product->category->template['product'] ?? '', [
                'categories' => $categories,
                'category' => $product->category,
                'products' => $products,
                'product' => $product,
                'params' => $args,
            ]);
        }

        return null;
    }
}
