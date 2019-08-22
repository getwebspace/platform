<?php

namespace Domain\Filters\Catalog;

use AEngine\Validator\Filter;
use AEngine\Validator\Traits\FilterRules;
use \Domain\Filters\Traits\CommonFilterRules;
use \Domain\Filters\Traits\CatalogFilterRules;

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
            ->attr('category')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 36))
                ->addRule($filter->ValidUUID())
            ->attr('title')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 255))
            ->attr('description')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 10000))
            ->attr('extra')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 10000))
            ->attr('address')
                ->addRule($filter->ValidAddress())
                ->addRule($filter->UniqueProductAddress())
                ->addRule($filter->checkStrlenBetween(0, 255))
            ->attr('vendorcode')
                ->addRule($filter->leadStr())
            ->attr('barcode')
                ->addRule($filter->leadInteger())
            ->attr('priceFirst')
                ->addRule($filter->leadDouble(2))
                ->addRule($filter->leadMin(1))
            ->attr('price')
                ->addRule($filter->leadDouble(2))
                ->addRule($filter->leadMin(1))
            ->attr('priceWholesale')
                ->addRule($filter->leadDouble(2))
                ->addRule($filter->leadMin(1))
            ->attr('volume')
                ->addRule($filter->leadDouble(2))
                ->addRule($filter->leadMin(1))
            ->attr('unit')
                ->addRule($filter->leadStr())
            ->attr('stock')
                ->addRule($filter->leadDouble(2))
                ->addRule($filter->leadMin(0))
            ->attr('field1')
                ->addRule($filter->leadStr())
            ->attr('field2')
                ->addRule($filter->leadStr())
            ->attr('field3')
                ->addRule($filter->leadStr())
            ->attr('field4')
                ->addRule($filter->leadStr())
            ->attr('field5')
                ->addRule($filter->leadStr())
            ->attr('country')
                ->addRule($filter->leadStr())
            ->attr('manufacturer')
                ->addRule($filter->leadStr())
            ->attr('order')
                ->addRule($filter->leadInteger())
            ->attr('meta')
                ->addRule($filter->ValidMeta())
            ->attr('external_id')
                ->addRule($filter->leadStr());

        return $filter->run();
    }
}
