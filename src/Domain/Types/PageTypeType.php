<?php declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\AbstractEnumType;

class PageTypeType extends AbstractEnumType
{
    public const NAME = 'PageTypeType';

    public const TYPE_HTML = 'html';
    public const TYPE_TEXT = 'text';

    public const LIST = [
        self::TYPE_HTML,
        self::TYPE_TEXT,
    ];
}
