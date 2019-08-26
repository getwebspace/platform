<?php

namespace App\Domain\Types\Catalog;

use App\Application\Types\EnumType;

class CategoryStatusType extends EnumType
{
    const NAME = 'CatalogCategoryStatusType';

    const STATUS_WORK = 'work',
          STATUS_DELETE = 'delete';

    const LIST          = [
        self::STATUS_WORK => 'Активный',
        self::STATUS_DELETE => 'Удаленный',
    ];
}
