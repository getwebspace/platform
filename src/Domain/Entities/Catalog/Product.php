<?php declare(strict_types=1);

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
     * @ORM\Column(type="uuid", options={"default": \Ramsey\Uuid\Uuid::NIL})
     */
    public $category = \Ramsey\Uuid\Uuid::NIL;

    /**
     * @ORM\Column(type="string", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $title = '';

    /**
     * @ORM\Column(type="text", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $description = '';

    /**
     * @ORM\Column(type="text", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $extra = '';

    /**
     * @ORM\Column(type="string", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $address = '';

    /**
     * @ORM\Column(type="text", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $vendorcode = '';

    /**
     * @ORM\Column(type="text", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $barcode = '';

    /**
     * // себестоимость
     *
     * @ORM\Column(type="decimal", scale=2, precision=10, options={"default": 0})
     */
    public $priceFirst = .0;

    /**
     * @ORM\Column(type="decimal", scale=2, precision=10, options={"default": 0})
     */
    public $price = .0;

    /**
     * // оптовая цена
     *
     * @ORM\Column(type="decimal", scale=2, precision=10, options={"default": 0})
     */
    public $priceWholesale = .0;

    /**
     * @ORM\Column(type="decimal", scale=2, precision=10, options={"default": 1})
     */
    public $volume = 1.0;

    /**
     * @ORM\Column(type="string", options={"default": "kg"})
     */
    public $unit = 'kg';

    /**
     * @ORM\Column(type="float", scale=2, precision=10, options={"default": 0})
     */
    public $stock = 0;

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    public $field1 = '';

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    public $field2 = '';

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    public $field3 = '';

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    public $field4 = '';

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    public $field5 = '';

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    public $country = '';

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    public $manufacturer = '';

    /**
     * @ORM\Column(type="array")
     */
    public $tags = [];

    /**
     * @ORM\Column(name="`order`", type="integer", options={"default": 1})
     */
    public $order = 1;

    /**
     * @var string
     *
     * @see \App\Domain\Types\Catalog\ProductStatusType::LIST
     * @ORM\Column(type="CatalogProductStatusType", options={"default": \App\Domain\Types\Catalog\ProductStatusType::STATUS_WORK})
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
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    public $external_id = '';

    /**
     * @ORM\Column(type="string", length=50, options={"default": "manual"})
     */
    public $export = 'manual';

    /**
     * @var mixed буфурное поле для обработки интеграций
     */
    public $buf;

    /**
     * @var array
     * @ORM\ManyToMany(targetEntity="App\Domain\Entities\File", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\JoinTable(name="catalog_product_files",
     *     joinColumns={@ORM\JoinColumn(name="product_uuid", referencedColumnName="uuid")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="file_uuid", referencedColumnName="uuid")}
     * )
     */
    protected $files = [];

    public function addFile(\App\Domain\Entities\File $file): void
    {
        $this->files[] = $file;
    }

    public function addFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->addFile($file);
        }
    }

    public function removeFile(\App\Domain\Entities\File $file): void
    {
        foreach ($this->files as $key => $value) {
            if ($file === $value) {
                unset($this->files[$key]);
                $value->unlink();
            }
        }
    }

    public function removeFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->removeFile($file);
        }
    }

    public function clearFiles(): void
    {
        foreach ($this->files as $key => $file) {
            unset($this->files[$key]);
            $file->unlink();
        }
    }

    public function getFiles($raw = false)
    {
        return $raw ? $this->files : collect($this->files);
    }

    public function hasFiles()
    {
        return count($this->files);
    }

    /**
     * Вернет габариты товара
     *
     * @return string
     */
    public function getVolume()
    {
        return ($this->volume ?? .0) . ($this->unit !== 'null' ? $this->unit : '');
    }
}
