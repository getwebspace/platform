<?php

namespace Domain\Filters;

use AEngine\Validator\Filter;
use AEngine\Validator\Traits\FilterRules;
use Domain\Filters\Traits\CommonFilterRules;

class GuestBook extends Filter
{
    use FilterRules;
    use CommonFilterRules;

    /**
     * Check model data
     *
     * @param array $data
     *
     * @return array|bool
     */
    public static function check(array &$data)
    {
        $filter = new self($data);

        $filter
            ->addGlobalRule($filter->leadTrim())
            ->attr('name')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 50))
            ->attr('email')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkEmail())
                ->addRule($filter->checkStrlenBetween(0, 50))
            ->attr('message')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 10000))
            ->attr('date')
                ->addRule($filter->ValidDate());

        return $filter->run();
    }
}
