<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use App\Domain\Service\Catalog\Exception\WrongTitleValueException;
use App\Domain\Traits\FileTrait;
use Doctrine\ORM\Mapping as ORM;
use Illuminate\Support\Collection;

#[ORM\Table(name: 'catalog_category')]
#[ORM\Index(name: 'catalog_category_address_idx', columns: ['address'])]
#[ORM\Index(name: 'catalog_category_parent_idx', columns: ['parent_uuid'])]
#[ORM\Index(name: 'catalog_category_order_idx', columns: ['order'])]
#[ORM\UniqueConstraint(name: 'catalog_category_unique', columns: ['parent_uuid', 'address', 'external_id'])]
#[ORM\Entity(repositoryClass: 'App\Domain\Repository\Catalog\CategoryRepository')]
class Category extends AbstractEntity
{
    use FileTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'Ramsey\Uuid\Doctrine\UuidGenerator')]
    protected \Ramsey\Uuid\UuidInterface $uuid;

    public function getUuid(): \Ramsey\Uuid\UuidInterface
    {
        return $this->uuid;
    }

    #[ORM\Column(type: 'uuid', nullable: true)]
    protected ?\Ramsey\Uuid\UuidInterface $parent_uuid = null;

    #[ORM\ManyToOne(targetEntity: 'App\Domain\Entities\Catalog\Category', cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'parent_uuid', referencedColumnName: 'uuid', onDelete: 'CASCADE')]
    protected ?Category $parent;

    /**
     * @return $this
     */
    public function setParent(mixed $category)
    {
        if (is_a($category, self::class)) {
            $this->parent_uuid = $category->getUuid();
            $this->parent = $category;
        } else {
            $this->parent_uuid = null;
            $this->parent = null;
        }

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    #[ORM\Column(type: 'string', length: 255, options: ['default' => ''])]
    protected string $title = '';

    /**
     * @return $this
     */
    public function setTitle(string $title)
    {
        if ($this->checkStrLenMax($title, 255)) {
            if ($this->validName($title)) {
                $this->title = $title;
            } else {
                throw new WrongTitleValueException();
            }
        }

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    #[ORM\Column(type: 'text', length: 100000, options: ['default' => ''])]
    protected string $description = '';

    /**
     * @return $this
     */
    public function setDescription(string $description)
    {
        if ($this->checkStrLenMax($description, 100000)) {
            $this->description = $description;
        }

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    #[ORM\Column(type: 'string', length: 1000, options: ['default' => ''])]
    protected string $address = '';

    /**
     * @return $this
     */
    public function setAddress(string $address)
    {
        if ($this->checkStrLenMax($address, 1000) && $this->validText($address)) {
            $this->address = $this->getAddressByValue($address, str_replace('/', '-', $this->getTitle()));
        } else {
            $this->address = $this->getAddressByValue(str_replace('/', '-', $this->getTitle()));
        }

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    #[ORM\Column(type: 'integer', options: ['default' => 10])]
    protected int $pagination = 10;

    /**
     * @return $this
     */
    public function setPagination(int $pagination)
    {
        $this->pagination = $pagination;

        return $this;
    }

    public function getPagination(): int
    {
        return $this->pagination;
    }

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
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

    public function getChildren(): bool
    {
        return $this->children;
    }

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    protected bool $hidden = false;

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setHidden($value)
    {
        $this->hidden = $this->getBooleanByValue($value);

        return $this;
    }

    public function getHidden(): bool
    {
        return $this->hidden;
    }

    #[ORM\Column(name: '`order`', type: 'integer', options: ['default' => 1])]
    protected int $order = 1;

    /**
     * @return Category
     */
    public function setOrder(int $order)
    {
        $this->order = $order;

        return $this;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    /**
     * @see \App\Domain\Types\UserStatusType::LIST
     */
    #[ORM\Column(type: 'CatalogCategoryStatusType', options: ['default' => \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK])]
    protected string $status = \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK;

    /**
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

    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    protected array $sort = [
        'by' => \App\Domain\References\Catalog::ORDER_BY_DATE,
        'direction' => \App\Domain\References\Catalog::ORDER_DIRECTION_ASC,
    ];

    /**
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

    public function getSort(): array
    {
        return $this->sort;
    }

    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    protected array $meta = [
        'title' => '',
        'description' => '',
        'keywords' => '',
    ];

    /**
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

    public function getMeta(): array
    {
        return $this->meta;
    }

    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    protected array $template = [
        'category' => '',
        'product' => '',
    ];

    /**
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

    public function getTemplate(): array
    {
        return $this->template;
    }

    #[ORM\Column(type: 'string', length: 255, options: ['default' => ''])]
    protected string $external_id = '';

    /**
     * @return $this
     */
    public function setExternalId(string $external_id)
    {
        if ($this->checkStrLenMax($external_id, 255)) {
            $this->external_id = $external_id;
        }

        return $this;
    }

    public function getExternalId(): string
    {
        return $this->external_id;
    }

    #[ORM\Column(type: 'string', length: 64, options: ['default' => 'manual'])]
    protected string $export = 'manual';

    /**
     * @return Category
     */
    public function setExport(string $export)
    {
        if ($this->checkStrLenMax($export, 64)) {
            $this->export = $export;
        }

        return $this;
    }

    public function getExport(): string
    {
        return $this->export;
    }

    #[ORM\Column(type: 'string', length: 512, options: ['default' => ''])]
    protected string $system = '';

    /**
     * @return $this
     */
    public function setSystem(string $system)
    {
        if ($this->checkStrLenMax($system, 512) && $this->validText($system)) {
            $this->system = $system;
        }

        return $this;
    }

    public function getSystem(): string
    {
        return $this->system;
    }

    /**
     * @var array
     */
    #[ORM\JoinTable(name: 'catalog_category_attributes')]
    #[ORM\JoinColumn(name: 'category_uuid', referencedColumnName: 'uuid', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'attribute_uuid', referencedColumnName: 'uuid', onDelete: 'CASCADE')]
    #[ORM\ManyToMany(targetEntity: 'App\Domain\Entities\Catalog\Attribute', cascade: ['remove'])]
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

    public function hasAttributes(): int
    {
        return count($this->attributes);
    }

    public function getAttributes(): Collection
    {
        return collect($this->attributes);
    }

    /**
     * @var mixed temp variable
     */
    public $buf;

    public function getParents(): Collection
    {
        $collect = collect([$this]);

        // @var \App\Domain\Entities\Catalog\Category $parent
        $parent = $this->parent;
        while ($parent !== null) {
            $collect[] = $parent;
            $parent = $parent->parent;
        }

        return $collect->reverse();
    }

    public function getNested(Collection &$categories, bool $force = false): Collection
    {
        $result = collect([$this]);

        if ($this->getChildren() || $force) {
            // @var \App\Domain\Entities\Catalog\Category $child
            foreach ($categories->where('parent_uuid', $this->getUuid()) as $child) {
                $result = $result->merge($child->getNested($categories, $force));
            }
        }

        return $result;
    }

    public function toArray(): array
    {
        $parent = [];

        if ($this->parent_uuid) {
            $parent = [
                'uuid' => $this->parent->uuid,
                'parent_uuid' => $this->parent->parent_uuid,
                'title' => $this->parent->title,
                'external_id' => $this->parent->external_id,
            ];
        }

        return array_serialize([
            'uuid' => $this->uuid,
            'parent_uuid' => $this->parent_uuid,
            'parent' => $parent,
            'title' => $this->title,
            'description' => $this->description,
            'address' => $this->address,
            'pagination' => $this->pagination,
            'children' => $this->children,
            'hidden' => $this->hidden,
            'order' => $this->order,
            'status' => $this->status,
            'sort' => $this->sort,
            'meta' => $this->meta,
            'template' => $this->template,
            'external_id' => $this->external_id,
            'export' => $this->export,
            'system' => $this->system,
            'attributes' => $this->attributes,
            'files' => $this->files,
        ]);
    }
}
