<?php declare(strict_types=1);

namespace App\Domain\Types\Catalog;

use App\Domain\AbstractEnumType;

class OrderStatusType extends AbstractEnumType
{
    public const NAME = 'CatalogOrderStatusType';

    public const STATUS_NEW = 'new';
    public const STATUS_PROCESS = 'process';
    public const STATUS_PAYMENT = 'payment';
    public const STATUS_READY = 'ready';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_CANCEL = 'cancel';

    public const LIST          = [
        self::STATUS_NEW,
        self::STATUS_PROCESS,
        self::STATUS_PAYMENT,
        self::STATUS_READY,
        self::STATUS_COMPLETE,
        self::STATUS_CANCEL,
    ];
}
