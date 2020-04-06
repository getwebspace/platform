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
            ->option('username', fn () => $filter
                ->addRule($filter->leadStr(), \App\Domain\References\Errors\User::WRONG_USERNAME)
            )
            ->option('email', fn () => $filter
                ->addRule($filter->checkEmail(), \App\Domain\References\Errors\User::WRONG_EMAIL)
            )
            ->attr('password', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(3, 20), \App\Domain\References\Errors\User::WRONG_PASSWORD_LENGTH)
            )
            ->attr('agent', fn () => $filter
                ->addRule($filter->leadStr())
            )
            ->attr('ip', fn () => $filter
                ->addRule($filter->checkIp())
            );

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
            ->attr('username', fn () => $filter
                ->addRule($filter->leadStr(), \App\Domain\References\Errors\User::WRONG_USERNAME)
                ->addRule($filter->UniqueUserUsername(), \App\Domain\References\Errors\User::WRONG_USERNAME_UNIQUE)
            )
            ->attr('email', fn () => $filter
                ->addRule($filter->checkEmail(), \App\Domain\References\Errors\User::WRONG_EMAIL)
                ->addRule($filter->UniqueUserEmail(), \App\Domain\References\Errors\User::WRONG_EMAIL_UNIQUE)
            )
            ->attr('password', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(3, 20), \App\Domain\References\Errors\User::WRONG_PASSWORD_LENGTH)
                ->addRule($filter->ValidPassword())
            )
            ->option('password_again', fn () => $filter
                ->addRule($filter->checkEqualToField('password'))
            );

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
            ->option('username', fn () => $filter
                ->addRule($filter->leadStr(), \App\Domain\References\Errors\User::WRONG_USERNAME)
                ->addRule($filter->UniqueUserUsername(), \App\Domain\References\Errors\User::WRONG_USERNAME_UNIQUE)
            )
            ->attr('email', fn () => $filter
                ->addRule($filter->checkEmail(), \App\Domain\References\Errors\User::WRONG_EMAIL)
                ->addRule($filter->UniqueUserEmail(), \App\Domain\References\Errors\User::WRONG_EMAIL_UNIQUE)
            )
            ->option('allow_mail', fn () => $filter
                ->addRule($filter->leadBoolean())
            )
            ->option('phone', fn () => $filter
                ->addRule(
                    isset($_ENV['SIMPLE_PHONE_CHECK']) && $_ENV['SIMPLE_PHONE_CHECK']
                        ? $filter->checkPhone()
                        : $filter->leadStrReplace([' ', '+', '-', '(', ')'], '')
                ))
            ->option('firstname', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 20))
            )
            ->option('lastname', fn () => $filter
                ->addRule($filter->leadStr())
                ->addRule($filter->checkStrlenBetween(0, 20))
            )
            ->option('status', fn () => $filter
                ->addRule($filter->checkInKeys(\App\Domain\Types\UserStatusType::LIST))
            )
            ->option('level', fn () => $filter
                ->addRule($filter->checkInKeys(\App\Domain\Types\UserLevelType::LIST))
            );

        // если длинна пароля не 140 символов - значит пароль
        // был изменен и его следует хешировать
        if ($data['password']) {
            if (mb_strlen($data['password']) !== 140) {
                $filter
                    ->option('password_again', fn () => $filter
                        ->addRule($filter->checkEqualToField('password'))
                        ->addRule($filter->leadRemove())
                    )
                    ->attr('password', fn () => $filter
                        ->addRule($filter->leadStr())
                        ->addRule($filter->checkStrlenBetween(3, 20), \App\Domain\References\Errors\User::WRONG_PASSWORD_LENGTH)
                        ->addRule($filter->ValidPassword())
                    );
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
