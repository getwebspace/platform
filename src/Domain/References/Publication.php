<?php

namespace App\Domain\References;

class Publication
{
    // possible order by
    const ORDER_BY_TITLE = 'title',
          ORDER_BY_DATE = 'date';

    // list of order by
    const ORDER_BY = [
        self::ORDER_BY_DATE => 'По дате',
        self::ORDER_BY_TITLE => 'По названию',
    ];

    // possible order directions
    const ORDER_DIRECTION_DESC = 'DESC',
          ORDER_DIRECTION_ASC = 'ASC';

    // list of order directions
    const ORDER_DIRECTION = [
        self::ORDER_DIRECTION_DESC => 'По убыванию',
        self::ORDER_DIRECTION_ASC => 'По возрастанию',
    ];
}
