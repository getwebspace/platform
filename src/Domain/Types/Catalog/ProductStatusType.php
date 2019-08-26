<?php

namespace Domain\Types\Catalog;

use Application\Types\EnumType;

class ProductStatusType extends EnumType
{
    const NAME = 'CatalogProductStatusType';

    const STATUS_WORK = 'work',
          STATUS_DELETE = 'delete';

    const LIST          = [
        self::STATUS_WORK => 'Активный',
        self::STATUS_DELETE => 'Удаленный',
    ];
}
