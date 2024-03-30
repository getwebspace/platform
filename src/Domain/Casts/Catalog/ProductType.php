<?php declare(strict_types=1);

namespace App\Domain\Casts\Catalog;

use App\Domain\Casts\Enum;

class ProductType extends Enum
{
    public const PRODUCT = 'product';
    public const SERVICE = 'service';

    public const LIST = [
        self::PRODUCT,
        self::SERVICE,
    ];
}
