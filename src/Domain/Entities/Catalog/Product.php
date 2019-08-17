<?php

namespace Domain\Entities\Catalog;

use AEngine\Entity\Model;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Entity
 * @ORM\Table(name="catalog_product", indexes={
 *     @ORM\Index(name="category_idx", columns={"category"}),
 *     @ORM\Index(name="price_idx", columns={"price", "priceFirst", "priceWholesale"}),
 *     @ORM\Index(name="volume_idx", columns={"volume", "unit"}),
 *     @ORM\Index(name="stock_idx", columns={"stock"}),
 *     @ORM\Index(name="manufacturer_idx", columns={"manufacturer"}),
 *     @ORM\Index(name="country_idx", columns={"country"}),
 *     @ORM\Index(name="order_idx", columns={"order"})
 * })
 */
class Product extends Model
{
    /**
     * @var UuidInterface
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    public $uuid;

    /**
     * @ORM\Column(type="uuid")
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
    public $unit = 'kg'; // TODO: Reference

    /**
     * @ORM\Column(type="float", scale=2, precision=10)
     */
    public $stock;

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
     * Вернет габариты товара
     *
     * @return string
     */
    public function getVolume()
    {
        return ($this->volume ?? .0) . ($this->unit != 'null' ? $this->unit : '');
    }
}
