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
            ->attr('address')
                ->addRule($filter->ValidAddress())
                ->addRule($filter->UniquePublicationAddress())
                ->addRule($filter->checkStrlenBetween(0, 255))
            ->attr('title')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 255))
            ->attr('category')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 36))
            ->attr('date')
                ->addRule($filter->ValidDate())
            ->attr('content')
                ->addRule($filter->ValidPublicationContent())
            ->attr('poll')
                ->addRule($filter->ValidPublicationPoll())
            ->attr('meta')
                ->addRule($filter->ValidMeta());

        return $filter->run();
    }
}
