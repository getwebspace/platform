<?php declare(strict_types=1);

namespace App\Domain\Casts\Review;

use App\Domain\Casts\Enum;

class EntityType extends Enum
{
    public const PUBLICATION = 'publication';
    public const CATALOG_PRODUCT = 'catalog_product';

    public const LIST = [
        self::PUBLICATION,
        self::CATALOG_PRODUCT,
    ];
}
