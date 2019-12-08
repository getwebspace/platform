<?php

namespace App\Domain\Entities\Catalog;

use Alksily\Entity\Model;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="catalog_product", indexes={
 *     @ORM\Index(name="catalog_product_address_idx", columns={"address"}),
 *     @ORM\Index(name="catalog_product_category_idx", columns={"category"}),
 *     @ORM\Index(name="catalog_product_price_idx", columns={"price", "priceFirst", "priceWholesale"}),
 *     @ORM\Index(name="catalog_product_volume_idx", columns={"volume", "unit"}),
 *     @ORM\Index(name="catalog_product_stock_idx", columns={"stock"}),
 *     @ORM\Index(name="catalog_product_manufacturer_idx", columns={"manufacturer"}),
 *     @ORM\Index(name="catalog_product_country_idx", columns={"country"}),
 *     @ORM\Index(name="catalog_product_order_idx", columns={"order"})
 * })
 */
class Product extends Model
{
    /**
     * @var Uuid
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    public $uuid;

    /**
     * @var Uuid
     * @ORM\Column(type="uuid", options={"default": NULL})
     */
    public $category;

    /**
     * @ORM\Column(type="string")
     */
    public $title;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $description;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $extra;

    /**
     * @ORM\Column(type="string")
     */
    public $address;

    /**
     * @ORM\Column(type="text")
     */
    public $vendorcode = '';

    /**
     * @ORM\Column(type="text")
     */
    public $barcode = '';

    /**
     * // себестоимость
     * @ORM\Column(type="decimal", scale=2, precision=10)
     */
    public $priceFirst = .0;

    /**
     * @ORM\Column(type="decimal", scale=2, precision=10)
     */
    public $price = .0;

    /**
     * // оптовая цена
     * @ORM\Column(type="decimal", scale=2, precision=10)
     */
    public $priceWholesale = .0;

    /**
     * @ORM\Column(type="decimal", scale=2, precision=10)
     */
    public $volume = 1.0;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    public $unit = 'kg';

    /**
     * @ORM\Column(type="float", scale=2, precision=10)
     */
    public $stock = 0;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $field1;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $field2;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $field3;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $field4;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    public $field5;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    public $country;

    /**
     * @ORM\Column(type="string", nullable=true)
     */
    public $manufacturer;

    /**
     * @ORM\Column(type="array")
     */
    public $tags = [];

    /**
     * @ORM\Column(name="`order`", type="integer")
     */
    public $order = 1;

    /**
     * @var string
     * @see \App\Domain\Types\Catalog\ProductStatusType::LIST
     * @ORM\Column(type="CatalogProductStatusType")
     */
    public $status = \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $date;

    /**
     * @var array
     * @ORM\Column(type="array")
     */
    public $meta = [
        'title' => '',
        'description' => '',
        'keywords' => '',
    ];

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    public $external_id;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    public $export = 'manual';

    /**
     * Вернет габариты товара
     *
     * @return string
     */
    public function getVolume()
    {
        return ($this->volume ?? .0) . ($this->unit != 'null' ? $this->unit : '');
    }
}
