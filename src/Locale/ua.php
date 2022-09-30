<?php declare(strict_types=1);

return array_merge(
    require_once 'ua/cup.catalog.php',
    require_once 'ua/cup.category.php',
    require_once 'ua/cup.editor.php',
    require_once 'ua/cup.file.php',
    require_once 'ua/cup.form.php',
    require_once 'ua/cup.guestbook.php',
    require_once 'ua/cup.main.php',
    require_once 'ua/cup.navigation.php',
    require_once 'ua/cup.page.php',
    require_once 'ua/cup.parameter.php',
    require_once 'ua/cup.publication.php',
    require_once 'ua/cup.user.php',

    require_once 'ua/exception.php',
    require_once 'ua/other.php',
);
