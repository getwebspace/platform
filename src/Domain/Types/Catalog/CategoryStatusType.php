<?php declare(strict_types=1);

namespace App\Domain\Types\Catalog;

use App\Application\Types\EnumType;

class CategoryStatusType extends EnumType
{
    public const NAME = 'CatalogCategoryStatusType';

    public const STATUS_WORK = 'work';
    public const STATUS_DELETE = 'delete';

    public const LIST          = [
        self::STATUS_WORK => 'Активный',
        self::STATUS_DELETE => 'Удаленный',
    ];
}
