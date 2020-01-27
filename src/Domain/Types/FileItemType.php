<?php

namespace App\Domain\Types;

use App\Application\Types\EnumType;

class FileItemType extends EnumType
{
    const NAME = 'FileItemType';

    const ITEM_USER_UPLOAD = 'user_upload',
          ITEM_PAGE = 'page',
          ITEM_PUBLICATION = 'publication',
          ITEM_CATALOG_CATEGORY = 'catalog_category',
          ITEM_CATALOG_PRODUCT = 'catalog_product',
          ITEM_FORM_DATA = 'form_data',
          ITEM_THEME = 'theme';

    const LIST                  = [
        self::ITEM_USER_UPLOAD => 'Файл пользователя',
        self::ITEM_PAGE => 'Файл страницы',
        self::ITEM_PUBLICATION => 'Файл публикации',
        self::ITEM_FORM_DATA => 'Файл из анкеты формы',
        self::ITEM_CATALOG_CATEGORY => 'Файл категории каталога',
        self::ITEM_CATALOG_PRODUCT => 'Файл продукта каталога',
        self::ITEM_THEME => 'Файл шаблона',
    ];
}
