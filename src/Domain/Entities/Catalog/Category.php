<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use App\Domain\Traits\FileTrait;
use Doctrine\ORM\Mapping as ORM;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Domain\Repository\Catalog\CategoryRepository")
 * @ORM\Table(name="catalog_category",
 *     indexes={
 *         @ORM\Index(name="catalog_category_address_idx", columns={"address"}),
 *         @ORM\Index(name="catalog_category_parent_idx", columns={"parent"}),
 *         @ORM\Index(name="catalog_category_order_idx", columns={"order"})
 *     },
 *     uniqueConstraints={
 *         @ORM\UniqueConstraint(name="catalog_category_unique", columns={"parent", "address"})
 *     }
 * )
 */
class Category extends AbstractEntity
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
     * @var Uuid
     * @ORM\Column(type="uuid", options={"default": \Ramsey\Uuid\Uuid::NIL})
     */
    protected $parent = \Ramsey\Uuid\Uuid::NIL;

    /**
     * @param string|Uuid $uuid
     *
     * @return $this
     */
    public function setParent($uuid)
    {
        $this->parent = $this->getUuidByValue($uuid);

        return $this;
    }

    /**
     * @return Uuid
     */
    public function getParent(): Uuid
    {
        return $this->parent;
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
    protected string $field1 = '';

    /**
     * @param string $field1
     */
    public function setField1(string $field1)
    {
        if ($this->checkStrLenMax($field1, 255)) {
            $this->field1 = $field1;
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
     * @param string $field2
     */
    public function setField2(string $field2)
    {
        if ($this->checkStrLenMax($field2, 255)) {
            $this->field2 = $field2;
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
     * @param string $field3
     */
    public function setField3(string $field3)
    {
        if ($this->checkStrLenMax($field3, 255)) {
            $this->field3 = $field3;
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
     * @var array
     * @ORM\Column(type="array")
     */
    protected array $product = [
        'field_1' => '',
        'field_2' => '',
        'field_3' => '',
        'field_4' => '',
        'field_5' => '',
    ];

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setProduct(array $data)
    {
        $default = [
            'field_1' => '',
            'field_2' => '',
            'field_3' => '',
            'field_4' => '',
            'field_5' => '',
        ];
        $data = array_merge($default, $data);

        $this->product = [
            'field_1' => $data['field_1'],
            'field_2' => $data['field_2'],
            'field_3' => $data['field_3'],
            'field_4' => $data['field_4'],
            'field_5' => $data['field_5'],
        ];

        return $this;
    }

    /**
     * @return array
     */
    public function getProduct(): array
    {
        return $this->product;
    }

    /**
     * @ORM\Column(type="integer", options={"default": 10})
     */
    protected int $pagination = 10;

    /**
     * @param int $pagination
     *
     * @return $this
     */
    public function setPagination(int $pagination)
    {
        $this->pagination = $pagination;

        return $this;
    }

    /**
     * @return int
     */
    public function getPagination(): int
    {
        return $this->pagination;
    }

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    protected bool $children = false;

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setChildren($value)
    {
        $this->children = $this->getBooleanByValue($value);

        return $this;
    }

    /**
     * @return bool
     */
    public function getChildren(): bool
    {
        return $this->children;
    }

    /**
     * @ORM\Column(name="`order`", type="integer", options={"default": 1})
     */
    protected int $order = 1;

    /**
     * @param int $order
     *
     * @return Category
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
     * @see \App\Domain\Types\UserStatusType::LIST
     * @ORM\Column(type="CatalogCategoryStatusType", options={"default": \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK})
     */
    protected string $status = \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK;

    /**
     * @param string $status
     *
     * @return $this
     */
    public function setStatus(string $status)
    {
        if (in_array($status, \App\Domain\Types\Catalog\CategoryStatusType::LIST, true)) {
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
     * @var array
     * @ORM\Column(type="array")
     */
    protected array $sort = [
        'by' => \App\Domain\References\Catalog::ORDER_BY_DATE,
        'direction' => \App\Domain\References\Catalog::ORDER_DIRECTION_ASC,
    ];

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setSort(array $data)
    {
        $default = [
            'by' => \App\Domain\References\Catalog::ORDER_BY_DATE,
            'direction' => \App\Domain\References\Catalog::ORDER_DIRECTION_ASC,
        ];
        $data = array_merge($default, $data);

        if (in_array($data['by'], \App\Domain\References\Catalog::ORDER_BY, true)) {
            $this->sort['by'] = $data['by'];
        }
        if (in_array($data['direction'], \App\Domain\References\Catalog::ORDER_DIRECTION, true)) {
            $this->sort['direction'] = $data['direction'];
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getSort(): array
    {
        return $this->sort;
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
     * @ORM\Column(type="array")
     */
    protected array $template = [
        'category' => '',
        'product' => '',
    ];

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setTemplate(array $data)
    {
        $default = [
            'category' => '',
            'product' => '',
        ];
        $data = array_merge($default, $data);

        $this->template = [
            'category' => $data['category'],
            'product' => $data['product'],
        ];

        return $this;
    }

    /**
     * @return array
     */
    public function getTemplate(): array
    {
        return $this->template;
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
     * @return Category
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
     * @var array
     * @ORM\ManyToMany(targetEntity="App\Domain\Entities\Catalog\Attribute", cascade={"remove"})
     * @ORM\JoinTable(name="catalog_category_attributes",
     *     joinColumns={@ORM\JoinColumn(name="category_uuid", referencedColumnName="uuid", onDelete="CASCADE")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="attribute_uuid", referencedColumnName="uuid", onDelete="CASCADE")}
     * )
     */
    protected $attributes = [];

    /**
     * @param array|Collection $attributes
     *
     * @return $this
     */
    public function setAttributes($attributes = [])
    {
        foreach ($this->attributes as $key => $attribute) {
            unset($this->attributes[$key]);
        }
        foreach ($attributes as $attribute) {
            $this->attributes[] = $attribute;
        }

        return $this;
    }

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
     * @return array|Collection
     */
    public function getAttributes($raw = false)
    {
        return $raw ? $this->attributes : collect($this->attributes);
    }

    /**
     * @var mixed буфурное поле для обработки интеграций
     */
    public $buf;

    /**
     * @var array
     * @ORM\OneToMany(targetEntity="\App\Domain\Entities\File\CatalogCategoryFileRelation", mappedBy="catalog_category", orphanRemoval=true)
     * @ORM\OrderBy({"order": "ASC"})
     */
    protected $files = [];

    /**
     * @param Collection $categories
     *
     * @return Collection
     */
    public function getNested(Collection &$categories)
    {
        $result = collect([$this]);

        if ($this->getChildren()) {
            // @var \App\Domain\Entities\Catalog\Category $child
            foreach ($categories->where('parent', $this->getUuid()) as $child) {
                $result = $result->merge($child->getNested($categories));
            }
        }

        return $result;
    }
}
