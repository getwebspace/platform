<?php declare(strict_types=1);

namespace App\Domain\Types;

use App\Application\Types\EnumType;

class FileItemType extends EnumType
{
    public const NAME = 'FileItemType';

    public const ITEM_USER_UPLOAD = 'user_upload';
    public const ITEM_PAGE = 'page';
    public const ITEM_PUBLICATION = 'publication';
    public const ITEM_CATALOG_CATEGORY = 'catalog_category';
    public const ITEM_CATALOG_PRODUCT = 'catalog_product';
    public const ITEM_FORM_DATA = 'form_data';
    public const ITEM_THEME = 'theme';

    public const LIST                  = [
        self::ITEM_USER_UPLOAD => 'Файл пользователя',
        self::ITEM_PAGE => 'Файл страницы',
        self::ITEM_PUBLICATION => 'Файл публикации',
        self::ITEM_FORM_DATA => 'Файл из анкеты формы',
        self::ITEM_CATALOG_CATEGORY => 'Файл категории каталога',
        self::ITEM_CATALOG_PRODUCT => 'Файл продукта каталога',
        self::ITEM_THEME => 'Файл шаблона',
    ];
}
