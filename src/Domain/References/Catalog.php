<?php declare(strict_types=1);

namespace App\Domain\References;

class Catalog
{
    public const IMPORT_EXPORT_FIELDS_DEFAULT = [
        'uuid',
        'title',
        'vendorcode',
        'price',
        'country', 'manufacturer',
        'order',
    ];

    public const IMPORT_FIELDS = [
        'uuid', 'external_id',
        'title', 'description', 'extra',
        'address', 'type',
        'barcode', 'vendorcode',
        'priceFirst', 'price', 'priceWholesale', 'priceWholesaleFrom', 'tax', 'special',
        'volume', 'unit', 'stock',
        'field1', 'field2', 'field3', 'field4', 'field5',
        'country', 'manufacturer',
        'order',
    ];

    public const EXPORT_FIELDS = [
        'uuid', 'external_id',
        'title', 'description', 'extra',
        'address', 'type',
        'barcode', 'vendorcode',
        'priceFirst', 'price', 'priceWholesale', 'priceWholesaleFrom', 'tax', 'special',
        'volume', 'unit', 'stock',
        'field1', 'field2', 'field3', 'field4', 'field5',
        'country', 'manufacturer',
        'order', 'date',
    ];

    // possible order by
    public const ORDER_BY_TITLE = 'title';
    public const ORDER_BY_PRICE = 'price';
    public const ORDER_BY_STOCK = 'stock';
    public const ORDER_BY_DATE = 'date';
    public const ORDER_BY_ORDER = 'order';

    // list of order by
    public const ORDER_BY = [
        self::ORDER_BY_TITLE,
        self::ORDER_BY_PRICE,
        self::ORDER_BY_STOCK,
        self::ORDER_BY_DATE,
        self::ORDER_BY_ORDER,
    ];

    // possible order directions
    public const ORDER_DIRECTION_DESC = 'DESC';
    public const ORDER_DIRECTION_ASC = 'ASC';

    // list of order directions
    public const ORDER_DIRECTION = [
        self::ORDER_DIRECTION_ASC,
        self::ORDER_DIRECTION_DESC,
    ];

    // possible product price types
    public const PRODUCT_PRICE_TYPE_PRICE = 'price';
    public const PRODUCT_PRICE_TYPE_PRICE_WHOLESALE = 'price_wholesale';

    // list of product price types
    public const PRODUCT_PRICE_TYPE = [
        self::PRODUCT_PRICE_TYPE_PRICE,
        self::PRODUCT_PRICE_TYPE_PRICE_WHOLESALE,
    ];
}
