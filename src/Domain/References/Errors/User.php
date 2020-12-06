<?php declare(strict_types=1);

namespace App\Domain\References\Errors;

class User
{
    public const NOT_FOUND = 'Пользователь не найден';

    public const WRONG_USERNAME = 'Логин указан с ошибкой';
    public const WRONG_USERNAME_UNIQUE = 'Логин уже используется';

    public const WRONG_EMAIL = 'E-Mail указан с ошибкой';
    public const WRONG_EMAIL_UNIQUE = 'E-Mail уже используется';

    public const WRONG_PASSWORD = 'Пароль указан с ошибкой';
    public const WRONG_PASSWORD_LENGTH = 'Длинна от 3 до 20 символов';
}
