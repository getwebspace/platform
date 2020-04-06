<?php

namespace App\Domain\Filters;

use Alksily\Validator\Filter;
use Alksily\Validator\Traits\FilterRules;
use App\Domain\Filters\Traits\CommonFilterRules;
use App\Domain\Filters\Traits\PageFilterRules;

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
            ->attr('address', fn () => $filter
                ->addRule($filter->ValidAddress())
                ->addRule($filter->UniquePageAddress())
                ->addRule($filter->checkStrlenBetween(0, 255))
            )
            ->attr('title', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 50))
            )
            ->attr('date', fn () => $filter
                ->addRule($filter->ValidDate())
            )
            ->attr('content', fn () => $filter
                ->addRule($filter->leadStr())
            )
            ->attr('type', fn () => $filter
                ->addRule($filter->checkInKeys(\App\Domain\Types\PageTypeType::LIST))
            )
            ->attr('meta', fn () => $filter
                ->addRule($filter->ValidMeta())
            )
            ->attr('template', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 50))
            );

        return $filter->run();
    }
}
