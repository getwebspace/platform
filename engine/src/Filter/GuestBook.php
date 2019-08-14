<?php

namespace Filter;

use AEngine\Validator\Filter;
use AEngine\Validator\Traits\FilterRules;
use Filter\Traits\CommonFilterRules;
use Filter\Traits\PageFilterRules;

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
            ->attr('message')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 10000))
            ->attr('date')
                ->addRule($filter->ValidDate());

        return $filter->run();
    }
}
