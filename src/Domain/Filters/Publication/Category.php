<?php declare(strict_types=1);

namespace App\Domain\Filters\Publication;

use Alksily\Validator\Filter;
use Alksily\Validator\Traits\FilterRules;
use App\Domain\Filters\Traits\CommonFilterRules;
use App\Domain\Filters\Traits\PublicationFilterRules;

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
            ->attr('address', fn () => $filter
                ->addRule($filter->ValidAddress())
                ->addRule($filter->InsertParentCategoryAddress())
                ->addRule($filter->UniqueCategoryAddress())
                ->addRule($filter->checkStrlenBetween(0, 255))
            )
            ->attr('title', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(3, 255))
            )
            ->attr('description', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 255))
            )
            ->attr('parent', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 36))
            )
            ->attr('public', fn () => $filter
                ->addRule($filter->leadBoolean())
            )
            ->attr('children', fn () => $filter
                ->addRule($filter->leadBoolean())
            )
            ->attr('pagination', fn () => $filter
                ->addRule($filter->leadInteger())
            )
            ->attr('sort', fn () => $filter
                ->addRule($filter->ValidCategorySort())
            )
            ->attr('meta', fn () => $filter
                ->addRule($filter->ValidMeta())
            )
            ->attr('template', fn () => $filter
                ->addRule($filter->ValidCategoryTemplate())
            );

        return $filter->run();
    }
}
