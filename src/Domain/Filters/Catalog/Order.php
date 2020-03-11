<?php

namespace App\Domain\Filters\Catalog;

use Alksily\Validator\Filter;
use Alksily\Validator\Traits\FilterRules;
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
                ->addRule($filter->leadStrReplace([' ', '+',  '-', '(', ')'], ''))
                //->addRule($filter->checkPhone(), 'Телефон должен быть в международном формате')
            ->attr('email')
                ->addRule($filter->checkEmail(), 'E-Mail указан с ошибкой')
            ->option('comment')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 500))
            ->attr('shipping')
                ->addRule($filter->ValidDate())
            ->attr('date')
                ->addRule($filter->ValidDate())
            ->option('external_id')
                ->addRule($filter->leadStr());

        return $filter->run();
    }
}
