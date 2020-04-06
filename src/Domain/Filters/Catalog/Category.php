<?php declare(strict_types=1);

namespace App\Domain\Filters\Catalog;

use Alksily\Validator\Filter;
use Alksily\Validator\Traits\FilterRules;
use App\Domain\Filters\Traits\CatalogFilterRules;
use App\Domain\Filters\Traits\CommonFilterRules;

class Category extends Filter
{
    use FilterRules;
    use CommonFilterRules;
    use CatalogFilterRules;

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
            ->attr('parent', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 36))
                ->addRule($filter->CheckUUID()))
            ->attr('title', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 255)))
            ->attr('description', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 10000)))
            ->attr('address', fn () => $filter
                ->addRule($filter->ValidAddress())
                ->addRule($filter->InsertParentCategoryAddress())
                ->addRule($filter->UniqueCategoryAddress())
                ->addRule($filter->checkStrlenBetween(0, 255)))
            ->attr('field1', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->leadTrim()))
            ->attr('field2', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->leadTrim()))
            ->attr('field3', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->leadTrim()))
            ->attr('product', fn () => $filter
                ->addRule($filter->ValidProductFieldNames())
            )
            ->attr('pagination', fn () => $filter
                ->addRule($filter->leadInteger())
            )
            ->attr('children', fn () => $filter
                ->addRule($filter->leadBoolean())
            )
            ->attr('order', fn () => $filter
                ->addRule($filter->leadInteger())
                ->addRule($filter->leadMin(1))
            )
            ->attr('meta', fn () => $filter
                ->addRule($filter->ValidMeta())
            )
            ->attr('template', fn () => $filter
                ->addRule($filter->ValidTemplate())
            )
            ->attr('external_id', fn () => $filter
                ->addRule($filter->leadStr())
            );

        return $filter->run();
    }
}
