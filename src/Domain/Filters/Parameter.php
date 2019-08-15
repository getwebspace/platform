<?php

namespace Domain\Filters;

use AEngine\Validator\Filter;
use AEngine\Validator\Traits\FilterRules;
use Domain\Filters\Traits\CommonFilterRules;

class Parameter extends Filter
{
    use FilterRules;
    use CommonFilterRules;

    /**
     * Login check
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
            ->attr('key')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(3, 50), \Domain\References\Errors\Parameter::WRONG_KEY)
            ->attr('value')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 1024), \Domain\References\Errors\Parameter::WRONG_VALUE);

        return $filter->run();
    }
}
