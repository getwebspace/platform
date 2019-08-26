<?php

namespace App\Domain\Filters\Catalog;

use AEngine\Validator\Filter;
use AEngine\Validator\Traits\FilterRules;
use App\Domain\Filters\Traits\CatalogFilterRules;
use App\Domain\Filters\Traits\CommonFilterRules;

class Order extends Filter
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
            ->attr('serial')
                ->addRule($filter->UniqueSerialID(6))
            ->attr('delivery')
                ->addRule($filter->ValidOrderDelivery())
                ->addRule($filter->CheckClient())
            ->attr('user_uuid')
                ->addRule($filter->CheckUUID(true))
            ->attr('list')
                ->addRule($filter->ValidOrderList())
            ->attr('phone')
                ->addRule($filter->checkPhone())
            ->attr('email')
                ->addRule($filter->checkEmail())
            ->attr('shipping')
                ->addRule($filter->ValidDate())
            ->attr('comment')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 500))
            ->attr('shipping')
                ->addRule($filter->ValidDate())
            ->attr('date')
                ->addRule($filter->ValidDate())
            ->attr('external_id')
                ->addRule($filter->leadStr());

        return $filter->run();
    }
}
