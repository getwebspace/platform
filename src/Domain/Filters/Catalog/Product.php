<?php

namespace App\Domain\Filters\Catalog;

use Alksily\Validator\Filter;
use Alksily\Validator\Traits\FilterRules;
use App\Domain\Filters\Traits\CatalogFilterRules;
use App\Domain\Filters\Traits\CommonFilterRules;

class Product extends Filter
{
    use FilterRules;
    use CommonFilterRules;
    use CatalogFilterRules;

    /**
     * Check product model data
     *
     * @param array $data
     *
     * @return array|bool
     */
    public static function check(array &$data)
    {
        $filter = new self($data);

        $filter
            ->attr('category', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 36))
                ->addRule($filter->CheckUUID()))
            ->attr('title', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 255)))
            ->attr('description', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 10000)))
            ->attr('extra', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 10000)))
            ->attr('address', fn () => $filter
                ->addRule($filter->ValidAddress())
                ->addRule($filter->InsertParentProductAddress())
                ->addRule($filter->UniqueProductAddress())
                ->addRule($filter->checkStrlenBetween(0, 255)))
            ->attr('vendorcode', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->leadTrim()))
            ->attr('barcode', fn () => $filter
                ->addRule($filter->leadInteger()))
            ->attr('priceFirst', fn () => $filter
                ->addRule($filter->leadDouble(2))
                ->addRule($filter->leadMin(0)))
            ->attr('price', fn () => $filter
                ->addRule($filter->leadDouble(2))
                ->addRule($filter->leadMin(0)))
            ->attr('priceWholesale', fn () => $filter
                ->addRule($filter->leadDouble(2))
                ->addRule($filter->leadMin(0)))
            ->attr('volume', fn () => $filter
                ->addRule($filter->leadDouble(2))
                ->addRule($filter->leadMin(1)))
            ->attr('unit', fn () => $filter
                ->addRule($filter->leadStr()))
            ->attr('stock', fn () => $filter
                ->addRule($filter->leadDouble(2))
                ->addRule($filter->leadMin(0)))
            ->attr('field1', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->leadTrim()))
            ->attr('field2', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->leadTrim()))
            ->attr('field3', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->leadTrim()))
            ->attr('field4', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->leadTrim()))
            ->attr('field5', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->leadTrim()))
            ->attr('country', fn () => $filter
                ->addRule($filter->leadStr()))
            ->attr('manufacturer', fn () => $filter
                ->addRule($filter->leadStr()))
            ->attr('tags', fn () => $filter
                ->addRule($filter->ValidTags())
                ->addRule($filter->leadTrim()))
            ->attr('order', fn () => $filter
                ->addRule($filter->leadInteger())
                ->addRule($filter->leadMin(1)))
            ->attr('meta', fn () => $filter
                ->addRule($filter->ValidMeta()))
            ->attr('date', fn () => $filter
                ->addRule($filter->ValidDate()))
            ->attr('external_id', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->leadTrim()));

        return $filter->run();
    }
}
