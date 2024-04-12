<?php declare(strict_types=1);

namespace App\Domain\Casts\Reference;

use App\Domain\Casts\Enum;

class Type extends Enum
{
    public const TEXT = 'text';
    public const STORE_LOCATION = 'store_location';
    public const CURRENCY = 'currency';
    public const ORDER_STATUS = 'order_status';
    public const STOCK_STATUS = 'stock_status';
    public const PAYMENT = 'payment';
    public const DELIVERY = 'delivery';
    public const COUNTRY = 'country';
    public const TAX_RATE = 'tax_rate';
    public const LENGTH_CLASS = 'length_class';
    public const WEIGHT_CLASS = 'weight_class';
    public const ADDRESS_FORMAT = 'address_format';
    public const SOCIAL_NETWORK = 'social_network';
    public const MANUFACTURER = 'manufacturer';

    public const LIST = [
        self::TEXT,
        self::STORE_LOCATION,
        self::CURRENCY,
        self::ORDER_STATUS,
        self::STOCK_STATUS,
        self::PAYMENT,
        self::DELIVERY,
        self::COUNTRY,
        self::TAX_RATE,
        self::LENGTH_CLASS,
        self::WEIGHT_CLASS,
        self::ADDRESS_FORMAT,
        self::SOCIAL_NETWORK,
        self::MANUFACTURER,
    ];
}
