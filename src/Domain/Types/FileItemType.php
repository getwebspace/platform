<?php

namespace App\Domain\Types;

use App\Application\Types\EnumType;

class FileItemType extends EnumType
{
    const NAME = 'FileItemType';

    const ITEM_CATALOG_CATEGORY = 'catalog_category';
    const ITEM_CATALOG_PRODUCT  = 'catalog_product';
    const ITEM_FORM_DATA        = 'form_data';

    const LIST                  = [
        self::ITEM_FORM_DATA  => 'Файл из анкеты формы',
        self::ITEM_CATALOG_CATEGORY => 'Файл категории каталога',
        self::ITEM_CATALOG_PRODUCT => 'Файл продукта каталога',
    ];
}
