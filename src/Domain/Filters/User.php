<?php

namespace App\Domain\Filters;

use AEngine\Validator\Filter;
use AEngine\Validator\Traits\FilterRules;
use App\Domain\Filters\Traits\CommonFilterRules;
use App\Domain\Filters\Traits\UserFilterRules;

class User extends Filter
{
    use FilterRules;
    use CommonFilterRules;
    use UserFilterRules;

    /**
     * Login check
     *
     * @param array $data
     *
     * @return array|bool
     */
    public static function login(array &$data)
    {
        $filter = new self($data);

        $filter
            ->addGlobalRule($filter->leadTrim())
            ->option('username')
                ->addRule($filter->leadStr(), \App\Domain\References\Errors\User::WRONG_USERNAME)
            ->option('email')
                ->addRule($filter->checkEmail(), \App\Domain\References\Errors\User::WRONG_EMAIL)
            ->attr('password')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(3, 20), \App\Domain\References\Errors\User::WRONG_PASSWORD_LENGTH)
            ->attr('agent')
                ->addRule($filter->leadStr())
            ->attr('ip')
                ->addRule($filter->checkIp());

        return $filter->run();
    }

    /**
     * Register check
     *
     * @param array $data
     *
     * @return array|bool
     */
    public static function register(array &$data)
    {
        $filter = new self($data);

        $filter
            ->addGlobalRule($filter->leadTrim())
            ->attr('username')
                ->addRule($filter->leadStr(), \Reference\Errors\User::WRONG_USERNAME)
                ->addRule($filter->UniqueUserUsername(), \Reference\Errors\User::WRONG_USERNAME_UNIQUE)
            ->attr('email')
                ->addRule($filter->checkEmail(), \Reference\Errors\User::WRONG_EMAIL)
                ->addRule($filter->UniqueUserEmail(), \Reference\Errors\User::WRONG_EMAIL_UNIQUE)
            ->attr('password')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(3, 20), \Reference\Errors\User::WRONG_PASSWORD_LENGTH)
                ->addRule($filter->ValidPassword())
            ->option('password_again')
                ->addRule($filter->checkEqualToField('password'));

        return $filter->run();
    }

    /**
     * Проверка модели при изменении данных
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
            ->attr('username')
            ->addRule($filter->leadStr(), \App\Domain\References\Errors\User::WRONG_USERNAME)
            ->addRule($filter->UniqueUserUsername(), \App\Domain\References\Errors\User::WRONG_USERNAME_UNIQUE)
            ->attr('email')
            ->addRule($filter->checkEmail(), \App\Domain\References\Errors\User::WRONG_EMAIL)
            ->addRule($filter->UniqueUserEmail(), \App\Domain\References\Errors\User::WRONG_EMAIL_UNIQUE)
            ->option('firstname')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 20))
            ->option('lastname')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 20))
            ->option('status')
            ->addRule($filter->checkInKeys(\App\Domain\Types\UserStatusType::LIST))
            ->option('level')
            ->addRule($filter->checkInKeys(\App\Domain\Types\UserLevelType::LIST));

        // если длинна пароля не 140 символов - значит пароль
        // был изменен и его следует хешировать
        if ($data['password'] && strlen($data['password']) !== 140) {
            $filter
                ->attr('password')
                    ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(3, 20), \App\Domain\References\Errors\User::WRONG_PASSWORD_LENGTH)
                    ->addRule($filter->ValidPassword())
                ->option('password_again')
                    ->addRule($filter->checkEqualToField('password'));
        }

        return $filter->run();
    }
}
