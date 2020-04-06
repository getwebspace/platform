<?php declare(strict_types=1);

namespace App\Domain\Types\Catalog;

use App\Application\Types\EnumType;

class ProductStatusType extends EnumType
{
    public const NAME = 'CatalogProductStatusType';

    public const STATUS_WORK = 'work';
    public const STATUS_DELETE = 'delete';

    public const LIST          = [
        self::STATUS_WORK => 'Активный',
        self::STATUS_DELETE => 'Удаленный',
    ];
}
