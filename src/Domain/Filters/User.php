<?php

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
                ->addRule($filter->leadStr(), \App\Domain\References\Errors\User::WRONG_USERNAME)
                ->addRule($filter->UniqueUserUsername(), \App\Domain\References\Errors\User::WRONG_USERNAME_UNIQUE)
            ->attr('email')
                ->addRule($filter->checkEmail(), \App\Domain\References\Errors\User::WRONG_EMAIL)
                ->addRule($filter->UniqueUserEmail(), \App\Domain\References\Errors\User::WRONG_EMAIL_UNIQUE)
            ->attr('password')
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(3, 20), \App\Domain\References\Errors\User::WRONG_PASSWORD_LENGTH)
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
            ->option('username')
                ->addRule($filter->leadStr(), \App\Domain\References\Errors\User::WRONG_USERNAME)
                ->addRule($filter->UniqueUserUsername(), \App\Domain\References\Errors\User::WRONG_USERNAME_UNIQUE)
            ->attr('email')
                ->addRule($filter->checkEmail(), \App\Domain\References\Errors\User::WRONG_EMAIL)
                ->addRule($filter->UniqueUserEmail(), \App\Domain\References\Errors\User::WRONG_EMAIL_UNIQUE)
            ->option('allow_mail')
                ->addRule($filter->leadBoolean())
            ->option('phone')
                ->addRule($filter->leadStrReplace([' ', '+',  '-', '(', ')'], ''))
                //->addRule($filter->checkPhone())
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
        if ($data['password']) {
            if (strlen($data['password']) !== 140) {
                $filter
                    ->option('password_again')
                        ->addRule($filter->checkEqualToField('password'))
                        ->addRule($filter->leadRemove())
                    ->attr('password')
                        ->addRule($filter->leadStr())
                        ->addRule($filter->checkStrlenBetween(3, 20), \App\Domain\References\Errors\User::WRONG_PASSWORD_LENGTH)
                        ->addRule($filter->ValidPassword());
            }
        } else {
            // пароль не был изменен убираем поле $data['password']
            unset($data['password']);
        }

        return $filter->run();
    }

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
            ->attr('email')
                ->addRule($filter->leadStr())
            ->attr('date')
                ->addRule($filter->ValidDate(true));

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
            ->attr('subject')
                ->addRule($filter->leadStr())
            ->attr('body')
                ->addRule($filter->leadStr())
            ->attr('type')
                ->addRule($filter->checkInKeys(\App\Domain\References\User::NEWSLETTER_TYPE));

        return $filter->run();
    }
}
