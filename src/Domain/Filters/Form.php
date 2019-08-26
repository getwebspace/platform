<?php

namespace App\Domain\Filters;

use AEngine\Validator\Filter;
use AEngine\Validator\Traits\FilterRules;
use App\Domain\Filters\Traits\CommonFilterRules;
use App\Domain\Filters\Traits\FormFilterRules;

class Form extends Filter
{
    use FilterRules;
    use CommonFilterRules;
    use FormFilterRules;

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
            ->attr('address')
                ->addRule($filter->ValidAddress())
                ->addRule($filter->UniqueFormAddress())
                ->addRule($filter->checkStrlenBetween(0, 255))
            ->attr('title')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 255))
            ->attr('mailto')
                ->addRule($filter->leadStr())
                ->addRule($filter->StrSplit('/\r\n/'))
            ->attr('origin')
                ->addRule($filter->leadStr())
                ->addRule($filter->StrSplit('/\r\n/'))
                ->addRule($filter->ValidFormOrigin())
        ;

        return $filter->run();
    }
}
