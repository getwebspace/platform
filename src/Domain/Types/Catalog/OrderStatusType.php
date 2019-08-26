<?php

namespace App\Domain\Types\Catalog;

use App\Application\Types\EnumType;

class OrderStatusType extends EnumType
{
    const NAME = 'CatalogOrderStatusType';

    const STATUS_NEW = 'new',
          STATUS_PROCESS = 'process',
          STATUS_PAYMENT = 'payment',
          STATUS_READY = 'ready',
          STATUS_COMPLETE = 'complete',
          STATUS_CANCEL = 'cancel';

    const LIST          = [
        self::STATUS_NEW => 'Новый',
        self::STATUS_PROCESS => 'В работе',
        self::STATUS_PAYMENT => 'Ждет оплаты',
        self::STATUS_READY => 'Готов к выдаче',
        self::STATUS_COMPLETE => 'Завершен',
        self::STATUS_CANCEL => 'Отменен',
    ];
}
