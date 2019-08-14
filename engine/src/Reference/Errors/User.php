<?php

namespace Reference\Errors;

class User
{
    const NOT_FOUND = 'Пользователь не найден';

    const WRONG_USERNAME = 'Логин указан с ошибкой';
    const WRONG_USERNAME_UNIQUE = 'Логин уже используется';

    const WRONG_EMAIL = 'E-Mail указан с ошибкой';
    const WRONG_EMAIL_UNIQUE = 'E-Mail уже используется';

    const WRONG_PASSWORD = 'Пароль указан с ошибкой';
    const WRONG_PASSWORD_LENGTH = 'Длинна от 3 до 20 символов';
}
