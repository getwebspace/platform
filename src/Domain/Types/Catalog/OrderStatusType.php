<?php declare(strict_types=1);

namespace App\Domain\Types\Catalog;

use App\Application\Types\EnumType;

class OrderStatusType extends EnumType
{
    public const NAME = 'CatalogOrderStatusType';

    public const STATUS_NEW = 'new';
    public const STATUS_PROCESS = 'process';
    public const STATUS_PAYMENT = 'payment';
    public const STATUS_READY = 'ready';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_CANCEL = 'cancel';

    public const LIST          = [
        self::STATUS_NEW => 'Новый',
        self::STATUS_PROCESS => 'В работе',
        self::STATUS_PAYMENT => 'Ждет оплаты',
        self::STATUS_READY => 'Готов к выдаче',
        self::STATUS_COMPLETE => 'Завершен',
        self::STATUS_CANCEL => 'Отменен',
    ];
}
