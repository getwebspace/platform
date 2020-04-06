<?php declare(strict_types=1);

namespace App\Domain\Filters;

use Alksily\Validator\Filter;
use Alksily\Validator\Traits\FilterRules;
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
            ->attr('address', fn () => $filter
                ->addRule($filter->ValidAddress())
                ->addRule($filter->UniqueFormAddress())
                ->addRule($filter->checkStrlenBetween(0, 255))
            )
            ->attr('title', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 255))
            )
            ->attr('template', fn () => $filter
                ->addRule($filter->leadStr())
            )
            ->attr('save_data', fn () => $filter
                ->addRule($filter->leadBoolean())
            )
            ->attr('recaptcha', fn () => $filter
                ->addRule($filter->leadBoolean())
            )
            ->attr('origin', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->StrSplit('/\r\n/'))
                ->addRule($filter->ValidFormOrigin())
            )
            ->attr('mailto', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->StrSplit('/\r\n/'))
            );

        return $filter->run();
    }
}
