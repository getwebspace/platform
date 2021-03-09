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
        'category',
        'title', 'description', 'extra',
        'address',
        'barcode', 'vendorcode',
        'priceFirst', 'price', 'priceWholesale',
        'volume', 'unit', 'stock',
        'field1', 'field2', 'field3', 'field4', 'field5',
        'country', 'manufacturer',
        'order',
    ];

    public const EXPORT_FIELDS = [
        'uuid', 'external_id',
        'category',
        'title', 'description', 'extra',
        'address',
        'barcode', 'vendorcode',
        'priceFirst', 'price', 'priceWholesale',
        'volume', 'unit', 'stock',
        'field1', 'field2', 'field3', 'field4', 'field5',
        'country', 'manufacturer',
        'order', 'date',
    ];
}
