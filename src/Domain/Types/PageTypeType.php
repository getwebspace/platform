<?php declare(strict_types=1);

namespace App\Domain\Types;

use App\Application\Types\EnumType;

class PageTypeType extends EnumType
{
    public const NAME = 'PageTypeType';

    public const TYPE_HTML = 'html';
    public const TYPE_TEXT = 'text';

    public const LIST = [
        self::TYPE_HTML  => 'Исходный текст HTML',
        self::TYPE_TEXT  => 'Простой текст',
    ];
}
