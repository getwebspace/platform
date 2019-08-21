<?php

namespace Domain\Filters\Catalog;

use AEngine\Validator\Filter;
use AEngine\Validator\Traits\FilterRules;
use \Domain\Filters\Traits\CommonFilterRules;
use \Domain\Filters\Traits\CatalogFilterRules;

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
            ->attr('user_uuid')
                ->addRule($filter->ValidUUID())
            ->option('delivery')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 500))
            ->attr('shipping')
                ->addRule($filter->ValidDate())
            ->attr('comment')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 500))
            ->attr('date')
                ->addRule($filter->ValidDate())
            ->attr('external_id')
                ->addRule($filter->leadStr());

        return $filter->run();
    }
}
