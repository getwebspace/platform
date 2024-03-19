<?php declare(strict_types=1);

namespace App\Domain\Casts\Reference;

use App\Domain\Casts\Enum;

class Type extends Enum
{
    public const TYPE_TEXT = 'text';
    public const TYPE_STORE_LOCATION = 'store_location';
    public const TYPE_CURRENCY = 'currency';
    public const TYPE_ORDER_STATUS = 'order_status';
    public const TYPE_STOCK_STATUS = 'stock_status';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_DELIVERY = 'delivery';
    public const TYPE_COUNTRY = 'country';
    public const TYPE_TAX_RATE = 'tax_rate';
    public const TYPE_LENGTH_CLASS = 'length_class';
    public const TYPE_WEIGHT_CLASS = 'weight_class';
    public const TYPE_ADDRESS_FORMAT = 'address_format';
    public const TYPE_SOCIAL_NETWORKS = 'social_network';

    public const LIST = [
        self::TYPE_TEXT,
        self::TYPE_STORE_LOCATION,
        self::TYPE_CURRENCY,
        self::TYPE_ORDER_STATUS,
        self::TYPE_STOCK_STATUS,
        self::TYPE_PAYMENT,
        self::TYPE_DELIVERY,
        self::TYPE_COUNTRY,
        self::TYPE_TAX_RATE,
        self::TYPE_LENGTH_CLASS,
        self::TYPE_WEIGHT_CLASS,
        self::TYPE_ADDRESS_FORMAT,
        self::TYPE_SOCIAL_NETWORKS,
    ];
}
