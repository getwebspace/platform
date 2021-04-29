<?php declare(strict_types=1);

namespace App\Domain\Types\Catalog;

use App\Domain\AbstractEnumType;

class ProductTypeType extends AbstractEnumType
{
    public const NAME = 'CatalogProductTypeType';

    public const TYPE_PRODUCT = 'product';
    public const TYPE_SERVICE = 'service';

    public const LIST = [
        self::TYPE_PRODUCT,
        self::TYPE_SERVICE,
    ];
}
