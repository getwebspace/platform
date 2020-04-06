<?php

namespace App\Domain\Filters;

use Alksily\Validator\Filter;
use Alksily\Validator\Traits\FilterRules;
use App\Domain\Filters\Traits\CommonFilterRules;
use App\Domain\Filters\Traits\PublicationFilterRules;

class Publication extends Filter
{
    use FilterRules;
    use CommonFilterRules;
    use PublicationFilterRules;

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
            ->attr('address', fn () => $filter
                ->addRule($filter->ValidAddress())
                ->addRule($filter->InsertParentCategoryAddress())
                ->addRule($filter->UniquePublicationAddress())
                ->addRule($filter->checkStrlenBetween(0, 255))
            )
            ->attr('title', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 255))
            )
            ->attr('category', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 36))
            )
            ->attr('date', fn () => $filter
                ->addRule($filter->ValidDate())
            )
            ->attr('content', fn () => $filter
                ->addRule($filter->ValidPublicationContent())
            )
            ->attr('poll', fn () => $filter
                ->addRule($filter->ValidPublicationPoll())
            )
            ->attr('meta', fn () => $filter
                ->addRule($filter->ValidMeta())
            );

        return $filter->run();
    }
}
