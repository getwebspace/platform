<?php

namespace Domain\Filters\Publication;

use AEngine\Validator\Filter;
use AEngine\Validator\Traits\FilterRules;
use \Domain\Filters\Traits\CommonFilterRules;
use \Domain\Filters\Traits\PublicationFilterRules;

class Category extends Filter
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
                ->addRule($filter->UniqueCategoryAddress())
                ->addRule($filter->checkStrlenBetween(0, 255))
            ->attr('title')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(3, 255))
            ->attr('description')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 255))
            ->attr('parent')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 36))
            ->attr('pagination')
                ->addRule($filter->leadInteger())
            ->attr('sort')
                ->addRule($filter->ValidCategorySort())
            ->attr('meta')
                ->addRule($filter->ValidMeta())
            ->attr('template')
                ->addRule($filter->ValidCategoryTemplate());

        return $filter->run();
    }
}
