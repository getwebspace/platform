<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use App\Domain\Traits\FileTrait;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Domain\Repository\Catalog\ProductRepository")
 * @ORM\Table(name="catalog_product",
 *     indexes={
 *         @ORM\Index(name="catalog_product_address_idx", columns={"address"}),
 *         @ORM\Index(name="catalog_product_category_idx", columns={"category"}),
 *         @ORM\Index(name="catalog_product_price_idx", columns={"price", "priceFirst", "priceWholesale"}),
 *         @ORM\Index(name="catalog_product_volume_idx", columns={"volume", "unit"}),
 *         @ORM\Index(name="catalog_product_manufacturer_idx", columns={"manufacturer"}),
 *         @ORM\Index(name="catalog_product_country_idx", columns={"country"}),
 *         @ORM\Index(name="catalog_product_order_idx", columns={"order"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="catalog_product_unique", columns={"category", "address"})
 *     }
 * )
 */
class Product extends AbstractEntity
{
    use FileTrait;

    /**
     * @var Uuid
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    protected Uuid $uuid;

    /**
     * @return Uuid
     */
    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    /**
     * @var string|Uuid
     * @ORM\Column(type="uuid", options={"default": \Ramsey\Uuid\Uuid::NIL})
     */
    protected $category = \Ramsey\Uuid\Uuid::NIL;

    /**
     * @param string|Uuid $uuid
     *
     * @return $this
     */
    public function setCategory($uuid)
    {
        $this->category = $this->getUuidByValue($uuid);

        return $this;
    }

    /**
     * @return Uuid
     */
    public function getCategory(): Uuid
    {
        return $this->category;
    }

    /**
     * @ORM\Column(type="string", length=255, options={"default": ""})
     */
    protected string $title = '';

    /**
     * @param string $title
     *
     * @return $this
     */
    public function setTitle(string $title)
    {
        if ($this->checkStrLenMax($title, 255)) {
            $this->title = $title;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @see \App\Domain\Types\ProductTypeType::LIST
     * @ORM\Column(type="CatalogProductTypeType", options={"default": \App\Domain\Types\Catalog\ProductTypeType::TYPE_PRODUCT})
     */
    protected string $type = \App\Domain\Types\Catalog\ProductTypeType::TYPE_PRODUCT;

    /**
     * @param string $type
     *
     * @return $this
     */
    public function setType(string $type)
    {
        if (in_array($type, \App\Domain\Types\Catalog\ProductTypeType::LIST, true)) {
            $this->type = $type;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @var string
     * @ORM\Column(type="text", length=10000, options={"default": ""})
     */
    protected string $description = '';

    /**
     * @param string $description
     *
     * @return $this
     */
    public function setDescription(string $description)
    {
        if ($this->checkStrLenMax($description, 10000)) {
            $this->description = $description;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @ORM\Column(type="text", length=10000, options={"default": ""})
     */
    protected string $extra = '';

    /**
     * @param string $extra
     *
     * @return $this
     */
    public function setExtra(string $extra)
    {
        if ($this->checkStrLenMax($extra, 10000)) {
            $this->extra = $extra;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getExtra(): string
    {
        return $this->extra;
    }

    /**
     * @ORM\Column(type="string", length=1000, options={"default": ""})
     */
    protected string $address = '';

    /**
     * @param string $address
     *
     * @return $this
     */
    public function setAddress(string $address)
    {
        if ($this->checkStrLenMax($address, 1000)) {
            $this->address = $this->getAddressByValue($address, $this->getTitle());
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getAddress(): string
    {
        return $this->address;
    }

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    protected string $vendorcode = '';

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setVendorCode(string $value)
    {
        if ($this->checkStrLenMax($value, 255)) {
            $this->vendorcode = $value;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getVendorCode()
    {
        return $this->vendorcode;
    }

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    protected string $barcode = '';

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setBarCode(string $value)
    {
        if ($this->checkStrLenMax($value, 255)) {
            $this->barcode = $value;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getBarCode()
    {
        return $this->barcode;
    }

    /**
     * // себестоимость
     *
     * @ORM\Column(type="float", scale=2, precision=10, options={"default": 0})
     */
    protected float $priceFirst = .00;

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setPriceFirst(float $value)
    {
        $this->priceFirst = $value;

        return $this;
    }

    /**
     * @return float
     */
    public function getPriceFirst(): float
    {
        return $this->priceFirst;
    }

    /**
     * @ORM\Column(type="float", scale=2, precision=10, options={"default": 0})
     */
    protected float $price = .00;

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setPrice(float $value)
    {
        $this->price = $value;

        return $this;
    }

    /**
     * @return float
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * // оптовая цена
     *
     * @ORM\Column(type="float", scale=2, precision=10, options={"default": 0})
     */
    protected float $priceWholesale = .00;

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setPriceWholesale(float $value)
    {
        $this->priceWholesale = $value;

        return $this;
    }

    /**
     * @return float
     */
    public function getPriceWholesale(): float
    {
        return $this->priceWholesale;
    }

    /**
     * @ORM\Column(type="float", scale=2, precision=10, options={"default": 1.0})
     */
    protected float $volume = 1.00;

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setVolume(float $value)
    {
        $this->volume = $value;

        return $this;
    }

    /**
     * @return float
     */
    public function getVolume(): float
    {
        return $this->volume;
    }

    /**
     * @ORM\Column(type="string", options={"default": "kg"})
     */
    protected string $unit = 'kg';

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setUnit(string $value)
    {
        if ($this->checkStrLenMax($value, 255)) {
            $this->unit = $value;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getUnit(): string
    {
        return $this->unit;
    }

    /**
     * @ORM\Column(type="float", scale=2, precision=10, options={"default": 0})
     */
    protected float $stock = .00;

    /**
     * @param float $value
     *
     * @return $this
     */
    public function setStock(float $value)
    {
        $this->stock = $value;

        return $this;
    }

    /**
     * @return float
     */
    public function getStock(): float
    {
        return $this->stock;
    }

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    protected string $field1 = '';

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setField1(string $value)
    {
        if ($this->checkStrLenMax($value, 512)) {
            $this->field1 = $value;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getField1(): string
    {
        return $this->field1;
    }

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    protected string $field2 = '';

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setField2(string $value)
    {
        if ($this->checkStrLenMax($value, 512)) {
            $this->field2 = $value;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getField2(): string
    {
        return $this->field2;
    }

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    protected string $field3 = '';

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setField3(string $value)
    {
        if ($this->checkStrLenMax($value, 512)) {
            $this->field3 = $value;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getField3(): string
    {
        return $this->field3;
    }

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    protected string $field4 = '';

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setField4(string $value)
    {
        if ($this->checkStrLenMax($value, 512)) {
            $this->field4 = $value;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getField4(): string
    {
        return $this->field4;
    }

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    protected string $field5 = '';

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setField5(string $value)
    {
        if ($this->checkStrLenMax($value, 512)) {
            $this->field5 = $value;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getField5(): string
    {
        return $this->field5;
    }

    /**
     * @var array
     * @ORM\OneToMany(targetEntity="App\Domain\Entities\Catalog\ProductAttribute", mappedBy="product", orphanRemoval=true)
     */
    protected $attributes = [];

    /**
     * @return int
     */
    public function hasAttributes()
    {
        return count($this->attributes);
    }

    /**
     * @param false $raw
     *
     * @return array|\Illuminate\Support\Collection
     */
    public function getAttributes($raw = false)
    {
        return $raw ? $this->attributes : collect($this->attributes);
    }

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    protected string $country = '';

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setCountry(string $value)
    {
        if ($this->checkStrLenMax($value, 255)) {
            $this->country = $value;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getCountry(): string
    {
        return $this->country;
    }

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    protected string $manufacturer = '';

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setManufacturer(string $value)
    {
        if ($this->checkStrLenMax($value, 255)) {
            $this->manufacturer = $value;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getManufacturer(): string
    {
        return $this->manufacturer;
    }

    /**
     * @ORM\Column(type="array")
     */
    protected $tags = []; // todo set array

    /**
     * @param array|string $tags
     *
     * @return $this
     */
    public function setTags($tags)
    {
        if (is_string($tags)) {
            $tags = explode(';', $tags);
        }
        $this->tags = array_map('trim', $tags);

        return $this;
    }

    /**
     * @return array
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @var array
     * @ORM\OneToMany(targetEntity="App\Domain\Entities\Catalog\ProductRelation", mappedBy="product", orphanRemoval=true)
     */
    protected $relation = [];

    public function getRelations($raw = false)
    {
        return $raw ? $this->relation : collect($this->relation);
    }

    public function hasRelations()
    {
        return count($this->relation);
    }

    /**
     * @var array
     * @ORM\OneToMany(targetEntity="App\Domain\Entities\Catalog\ProductRelation", mappedBy="related", orphanRemoval=true)
     */
    protected $related = [];

    public function getRelated($raw = false)
    {
        return $raw ? $this->related : collect($this->related);
    }

    public function hasRelated()
    {
        return count($this->related);
    }

    /**
     * @ORM\Column(name="`order`", type="integer", options={"default": 1})
     */
    protected int $order = 1;

    /**
     * @param int $order
     *
     * @return $this
     */
    public function setOrder(int $order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @return int
     */
    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * @var string
     *
     * @see \App\Domain\Types\ProductStatusType::LIST
     * @ORM\Column(type="CatalogProductStatusType", options={"default": \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK})
     */
    protected string $status = \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK;

    /**
     * @param string $status
     *
     * @return $this
     */
    public function setStatus(string $status)
    {
        if (in_array($status, \App\Domain\Types\Catalog\ProductStatusType::LIST, true)) {
            $this->status = $status;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected DateTime $date;

    /**
     * @param $date
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setDate($date)
    {
        $this->date = $this->getDateTimeByValue($date);

        return $this;
    }

    /**
     * @return DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @var array
     * @ORM\Column(type="array")
     */
    protected array $meta = [
        'title' => '',
        'description' => '',
        'keywords' => '',
    ];

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setMeta(array $data)
    {
        $default = [
            'title' => '',
            'description' => '',
            'keywords' => '',
        ];
        $data = array_merge($default, $data);

        $this->meta = [
            'title' => $data['title'],
            'description' => $data['description'],
            'keywords' => $data['keywords'],
        ];

        return $this;
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    protected string $external_id = '';

    /**
     * @param string $external_id
     *
     * @return $this
     */
    public function setExternalId(string $external_id)
    {
        if ($this->checkStrLenMax($external_id, 255)) {
            $this->external_id = $external_id;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getExternalId(): string
    {
        return $this->external_id;
    }

    /**
     * @ORM\Column(type="string", length=50, options={"default": "manual"})
     */
    protected string $export = 'manual';

    /**
     * @param string $export
     *
     * @return Product
     */
    public function setExport(string $export)
    {
        $this->export = $export;

        return $this;
    }

    /**
     * @return string
     */
    public function getExport(): string
    {
        return $this->export;
    }

    /**
     * @var mixed буфурное поле для обработки интеграций
     */
    public $buf;

    /**
     * @var array
     * @ORM\OneToMany(targetEntity="\App\Domain\Entities\File\CatalogProductFileRelation", mappedBy="catalog_product", orphanRemoval=true)
     * @ORM\OrderBy({"order": "ASC"})
     */
    protected $files = [];

    /**
     * @return string
     */
    public function getVolumeWithUnit()
    {
        return ($this->volume ?? .0) . ($this->unit !== 'null' ? $this->unit : '');
    }
}
