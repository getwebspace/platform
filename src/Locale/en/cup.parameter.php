<?php declare(strict_types=1);

return [
    // ***
    // Parameters
    // ***

    // tabs
    'Общие' => 'Common',
    'Плагины' => 'Plugins',
    'Переменные' => 'Variables',

    // subtabs
    'Основные' => 'Main',
    'Почта' => 'Mail',
    'Файлы и изображения' => 'Files and images',
    'Уведомления' => 'Notifications',
    'Формы' => 'Forms',
    'Поиск' => 'Search',
    'Гостевая книга' => 'Guestbook',
    'API' => 'API',
    'Шаблоны' => 'Templates',
    'Группа и права доступа' => 'Group and access rights',
    'Параметры входа' => 'Login options',
    'Импорт и Экспорт' => 'Import and Export',
    'Дополнительно' => 'Additionally',

    // common
    'Сайт доступен посетителям' => 'The site is available to visitors',
    'Можно использовать во время технических работ и/или инвентаризации' => 'Can be used during maintenance and / or inventory',
    'Название сайта' => 'Site title',
    'например: "Моя домашняя страница"' => 'for example: "My home page"',
    'Домашняя страница сайта' => 'Home page address',
    'Укажите адрес главной страницы вашего сайта, включая слеш в конце. Например: http://yoursite.com/' => 'Enter the address of the home page of your site, including a slash at the end. For example: http://yoursite.com/',
    'Описание (Description) сайта' => 'Description (Description) of the site',
    'Краткое описание, не более 200 символов' => 'Short description, no more than 200 characters',
    'Ключевые (Keywords) слова' => 'Key words',
    'Через запятую' => 'Comma separated',
    'Тема оформления сайта' => 'Theme name',
    'По-умолчанию: "default"' => 'Default: "default"',
    'Шаблон главной страницы' => 'Home page template',
    'Часовой пояс' => 'Timezone',
    'Формат времени' => 'Time format',
    'Код языка сайта' => 'Site language (code)',
    'Используется для значения атрибута <code>&lt;html lang="ru"&gt;</code><br>Например: <code>ru</code>, <code>ua</code>, <code>en</code>' => 'Used for attribute value <code>&lt;html lang="ru"&gt;</code><br>For example: <code>ru</code>, <code>ua</code>, <code>en</code>',
    'Автоматически генерировать адреса' => 'Automatically generate addresses',
    'Адреса некоторых сущностей будут автоматически включать адрес категории' => 'Some entity addresses will automatically include the category address',
    'Системный E-Mail адрес' => 'System E-Mail Address',
    'От данного адреса будут отправляться сообщения сайта, например уведомления пользователей, рассылки, подтверждения и т.д. Примечание: некоторые бесплатные почтовые сервисы, например yandex.ru, требуют, чтобы в качестве E-Mail адреса отправителя был указан именно адрес, зарегистрированный на их почтовом сервисе' => 'From this address, site messages will be sent, for example, user notifications, mailings, confirmations, etc. Note: some free mail services, for example yandex.ru, require that the address registered on their mail service be specified as the sender E-Mail address.',
    'Имя отправителя' => 'Sender name',
    'Данное имя будет прикреплено к письму в качестве имени отправителя' => 'This name will be attached to the letter as the name of the sender',
    'Заголовок при отправке писем' => 'Email subject',
    'При отправке писем с сайта вы можете указать заголовок, который будет добавляться к почте отправителя. Например, вы можете там указать краткое название вашего сайта' => 'When sending letters from the site, you can specify a header that will be added to the sender mail. For example, you can specify the short name of your site there.',
    'Хост SMTP сервера' => 'SMTP server host',
    'Обычно — localhost' => 'Usually - localhost',
    'Порт SMTP сервера' => 'SMTP server port',
    'Обычно — 465' => 'Usually — 465',
    'Использовать защищенный протокол для отправки писем через SMTP сервер' => 'Use secure protocol to send emails through SMTP server',
    'Выберите протокол шифрования при отправке писем с использованием SMTP сервера' => 'Select the encryption protocol when sending emails using an SMTP server',
    'SMTP имя пользователя' => 'SMTP username',
    'SMTP пароль' => 'SMTP password',
    'Не требуется в большинстве случаев, когда используется "localhost"' => 'Not required in most cases when using "localhost"',

    // api access
    'key' => 'Only keys',
    'user' => 'Users and Keys',

    // user auth by
    'username' => 'Login',
    'email' => 'E-Mail',
    'phone' => 'Telephone',

    // plugins
    'Включение и выключение reCAPTCHA' => 'Turning reCAPTCHA on and off',
    'Защита от роботов' => 'Robot protection',
    'Публичный ключ сервиса reCAPTCHA' => 'Public key of the reCAPTCHA service',
    'Этот ключ будет использован в HTML-коде сайта на устройства пользователей' => 'This key will be used in the HTML code of the site for users devices',
    'Секретный ключ сервиса reCAPTCHA' => 'This key will be used in the HTML code of the site for users devices',
    'Этот ключ нужен для связи между сайтом и сервисом reCAPTCHA. Никому его не сообщайте' => 'This key is needed for communication between the site and the reCAPTCHA service. Do not tell it to anyone',

    // variables
    'В этом разделе настроек можно задать дополнительные глобальные переменные, которые можно легко использовать в шаблоне.' => 'In this section of settings, you can set additional global variables that can be easily used in the template.',
    'переменную с именем' => 'variable named',
    'можно вывести на странице с помощью следующего кода:' => 'can be displayed on the page using the following code:',
    'Имя новой переменной' => 'New variable name',
    'Допустимы только латинские буквы: <code>[A-Za-z]</code>' => 'Only latin letters are allowed: <code>[A-Za-z]</code>',
];
