<?php

namespace Domain\Filters;

use AEngine\Validator\Filter;
use AEngine\Validator\Traits\FilterRules;
use \Domain\Filters\Traits\CommonFilterRules;
use \Domain\Filters\Traits\PageFilterRules;

class Page extends Filter
{
    use FilterRules;
    use CommonFilterRules;
    use PageFilterRules;

    /**
     * Check page model data
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
                ->addRule($filter->UniquePageAddress())
                ->addRule($filter->checkStrlenBetween(0, 255))
            ->attr('title')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 50))
            ->attr('date')
                ->addRule($filter->ValidDate())
            ->attr('content')
                ->addRule($filter->leadStr())
            ->attr('type')
                ->addRule($filter->checkInKeys(\Domain\Types\PageTypeType::LIST))
            ->attr('meta')
                ->addRule($filter->ValidMeta())
            ->attr('template')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 50));

        return $filter->run();
    }
}
