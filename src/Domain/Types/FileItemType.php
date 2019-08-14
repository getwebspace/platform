<?php

namespace Domain\Types;

use Application\Types\EnumType;

class FileItemType extends EnumType
{
    const NAME = 'FileItemType';

    const ITEM_FORM_DATA = 'form_data';

    const LIST = [
        self::ITEM_FORM_DATA  => 'Файл из анкеты формы',
    ];
}
