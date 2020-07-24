<?php declare(strict_types=1);

namespace App\Domain\Types;

use App\Domain\AbstractEnumType;

class FileItemType extends AbstractEnumType
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
        self::ITEM_USER_UPLOAD,
        self::ITEM_PAGE,
        self::ITEM_PUBLICATION,
        self::ITEM_FORM_DATA,
        self::ITEM_CATALOG_CATEGORY,
        self::ITEM_CATALOG_PRODUCT,
        self::ITEM_THEME,
    ];
}
