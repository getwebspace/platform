<?php declare(strict_types=1);

namespace App\Domain\Filters\Catalog;

use Alksily\Validator\Filter;
use Alksily\Validator\Traits\FilterRules;
use App\Domain\Filters\Traits\CatalogFilterRules;
use App\Domain\Filters\Traits\CommonFilterRules;

class Order extends Filter
{
    use FilterRules;
    use CommonFilterRules;
    use CatalogFilterRules;

    /**
     * Check product model data
     *
     * @param array $data
     *
     * @return array|bool
     */
    public static function check(array &$data)
    {
        $filter = new self($data);

        $filter
            ->attr('serial', fn () => $filter
                ->addRule($filter->UniqueSerialID(6)))
            ->attr('delivery', fn () => $filter
                ->addRule($filter->ValidOrderDelivery())
                ->addRule($filter->CheckClient()))
            ->attr('user_uuid', fn () => $filter
                ->addRule($filter->CheckUUID(true)))
            ->attr('list', fn () => $filter
                ->addRule($filter->ValidOrderList()))
            ->attr('phone', fn () => $filter
                ->addRule(
                    isset($_ENV['SIMPLE_PHONE_CHECK']) && $_ENV['SIMPLE_PHONE_CHECK'] !== 1
                        ? $filter->checkPhone()
                        : $filter->leadStrReplace([' ', '+', '-', '(', ')'], '')
                ))
            ->attr('email', fn () => $filter
                ->addRule($filter->checkEmail(), 'E-Mail указан с ошибкой'))
            ->option('comment', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 500)))
            ->attr('shipping', fn () => $filter
                ->addRule($filter->ValidDate()))
            ->attr('date', fn () => $filter
                ->addRule($filter->ValidDate()))
            ->option('external_id', fn () => $filter
                ->addRule($filter->leadStr()));

        return $filter->run();
    }
}
