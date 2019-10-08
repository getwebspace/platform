<?php

namespace App\Domain\Types;

use App\Application\Types\EnumType;

class FileItemType extends EnumType
{
    const NAME = 'FileItemType';

    const ITEM_USER_UPLOAD = 'user_upload';
    const ITEM_CATALOG_CATEGORY = 'catalog_category';
    const ITEM_CATALOG_PRODUCT  = 'catalog_product';
    const ITEM_FORM_DATA        = 'form_data';

    const LIST                  = [
        self::ITEM_USER_UPLOAD  => 'Файл пользователя',
        self::ITEM_FORM_DATA  => 'Файл из анкеты формы',
        self::ITEM_CATALOG_CATEGORY => 'Файл категории каталога',
        self::ITEM_CATALOG_PRODUCT => 'Файл продукта каталога',
    ];
}
