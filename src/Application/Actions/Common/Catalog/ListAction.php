<?php declare(strict_types=1);

namespace App\Application\Actions\Common\Catalog;

use App\Domain\Service\Catalog\Exception\CategoryNotFoundException;
use App\Domain\Service\Catalog\Exception\ProductNotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Slim\Psr7\Response;

class ListAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $args = $this->parsePath();
        $categories = $this->catalogCategoryService->read([
            'status' => \App\Domain\Casts\Catalog\Status::WORK,
            'hidden' => false,
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
        $parts = explode('/', ltrim(str_replace('/catalog', '', $this->request->getUri()->getPath()), '/'));
        $offset = 0;

        if (($buf = $parts[count($parts) - 1]) && ctype_digit($buf)) {
            $offset = +$buf;
            unset($parts[count($parts) - 1]);
        }

        return ['address' => implode('/', $parts), 'offset' => $offset];
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
            $products->where('cc.hidden', false);
            $products->where('cp.status', \App\Domain\Casts\Catalog\Status::WORK);

            // attribute filter
            if (($params = $this->getParams())) {
                $attributes = $this->db->query();
                $attributes->select('uuid', 'address');
                $attributes->from('catalog_attribute as ca');
                $attributes->leftJoin('catalog_attribute_product as cap', 'ca.uuid', '=', 'cap.attribute_uuid');
                $attributes->where('ca.is_filter', true);
                $attributes->whereIn('ca.address', array_keys($this->getParams()));
                $attributes = $attributes->get();

                $products->leftJoin('catalog_attribute_product as cap', 'cp.uuid', '=', 'cap.product_uuid');
                $products->where(function (Builder $query) use ($attributes, $params) {
                    foreach ($attributes as $attr) {
                        $query->orWhere(function (Builder $q) use ($attr, $params) {
                            $q->where('cap.attribute_uuid', $attr->uuid)->where('cap.value', $params[$attr->address]);
                        });
                    }
                });
            }

            // price filter
            if (($price = $this->getParam('price', false)) !== false) {
                $price = array_merge(['min' => 0, 'max' => 0], (array) $price);

                if ($price['min']) {
                    $params['price']['min'] = (float) $price['min'];
                    $products->where('cp.price', '>=', $params['price']['min']);
                }
                if ($price['max']) {
                    $params['price']['max'] = (float) $price['max'];
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
            $count = $products->count();
            $all = $products->get();
            $filtered = $all->forPage($args['offset'], $pagination);

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
            $products->where('cc.hidden', false);
            $products->where('cp.status', \App\Domain\Casts\Catalog\Status::WORK);
            $products->whereIn('cp.category_uuid', $category->nested()->pluck('uuid'));

            // attribute filter
            if (($params = $this->getParams())) {
                $attributes = $this->db->query();
                $attributes->select('uuid', 'address');
                $attributes->from('catalog_attribute as ca');
                $attributes->leftJoin('catalog_attribute_product as cap', 'ca.uuid', '=', 'cap.attribute_uuid');
                $attributes->where('ca.is_filter', true);
                $attributes->whereIn('ca.address', array_keys($this->getParams()));
                $attributes = $attributes->get();

                $products->leftJoin('catalog_attribute_product as cap', 'cp.uuid', '=', 'cap.product_uuid');
                $products->where(function (Builder $query) use ($attributes, $params) {
                    foreach ($attributes as $attr) {
                        $query->orWhere(function (Builder $q) use ($attr, $params) {
                            $q->where('cap.attribute_uuid', $attr->uuid)->where('cap.value', $params[$attr->address]);
                        });
                    }
                });
            }

            // price filter
            if (($price = $this->getParam('price', false)) !== false) {
                $price = array_merge(['min' => 0, 'max' => 0], (array) $price);

                if ($price['min']) {
                    $params['price']['min'] = (float) $price['min'];
                    $products->where('cp.price', '>=', $params['price']['min']);
                }
                if ($price['max']) {
                    $params['price']['max'] = (float) $price['max'];
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

            $pagination = $category->pagination;
            $count = $products->count();
            $all = $products->get();
            $filtered = $all->forPage($args['offset'], $pagination);

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
