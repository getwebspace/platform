<?php

namespace App\Domain\Types;

use App\Application\Types\EnumType;

class PageTypeType extends EnumType
{
    const NAME = 'PageTypeType';

    const TYPE_HTML = 'html',
          TYPE_TEXT = 'text';

    const LIST = [
        self::TYPE_HTML  => 'Исходный текст HTML',
        self::TYPE_TEXT  => 'Простой текст',
    ];
}
