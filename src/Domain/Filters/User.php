<?php declare(strict_types=1);

namespace App\Domain\Filters;

use Alksily\Validator\Filter;
use Alksily\Validator\Traits\FilterRules;
use App\Domain\Filters\Traits\CommonFilterRules;
use App\Domain\Filters\Traits\UserFilterRules;

class User extends Filter
{
    use FilterRules;
    use CommonFilterRules;
    use UserFilterRules;

    /**
     * Проверка полей подписчика
     *
     * @param array $data
     *
     * @return array|bool
     */
    public static function subscribeCreate(array &$data)
    {
        $filter = new self($data);

        $filter
            ->addGlobalRule($filter->leadTrim())
            ->attr('email', fn () => $filter
                ->addRule($filter->leadStr())
            )
            ->attr('date', fn () => $filter
                ->addRule($filter->ValidDate(true))
            );

        return $filter->run();
    }

    /**
     * Проверка полей письма
     *
     * @param array $data
     *
     * @return array|bool
     */
    public static function newsletter(array &$data)
    {
        $filter = new self($data);

        $filter
            ->addGlobalRule($filter->leadTrim())
            ->attr('subject', fn () => $filter
                ->addRule($filter->leadStr())
            )
            ->attr('body', fn () => $filter
                ->addRule($filter->leadStr())
            )
            ->attr('type', fn () => $filter
                ->addRule($filter->checkInKeys(\App\Domain\References\User::NEWSLETTER_TYPE))
            );

        return $filter->run();
    }
}
