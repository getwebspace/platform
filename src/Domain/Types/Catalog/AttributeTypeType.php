<?php declare(strict_types=1);

namespace App\Domain\Types\Catalog;

use App\Domain\AbstractEnumType;

class AttributeTypeType extends AbstractEnumType
{
    public const NAME = 'CatalogAttributeTypeType';

    public const TYPE_STRING = 'string';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_FLOAT = 'float';

    public const LIST = [
        self::TYPE_STRING,
        self::TYPE_INTEGER,
        self::TYPE_FLOAT,
    ];
}
