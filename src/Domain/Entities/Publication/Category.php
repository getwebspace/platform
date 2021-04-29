<?php declare(strict_types=1);

namespace App\Domain\Entities\Publication;

use App\Domain\AbstractEntity;
use App\Domain\Traits\FileTrait;
use Doctrine\ORM\Mapping as ORM;
use Illuminate\Support\Collection;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Domain\Repository\Publication\CategoryRepository")
 * @ORM\Table(name="publication_category")
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
    protected $uuid;

    /**
     * @return Uuid
     */
    public function getUuid(): Uuid
    {
        return $this->uuid;
    }

    /**
     * @var string
     * @ORM\Column(type="string", length=1000, unique=true, options={"default": ""})
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
     * @return string
     */
    public function rss(): string
    {
        return implode('/', ['rss', $this->address]);
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
     * @var string|uuid
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
     * @return string
     */
    public function getParent(): string
    {
        return $this->parent;
    }

    /**
     * @ORM\Column(type="integer", options={"default": 10})
     */
    public int $pagination = 10;

    /**
     * @param int $value
     *
     * @return $this
     */
    public function setPagination(int $value)
    {
        $this->pagination = $value;

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
     * @ORM\Column(type="boolean", options={"default": true})
     */
    protected bool $public = true;

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function setPublic($value)
    {
        $this->public = $this->getBooleanByValue($value);

        return $this;
    }

    /**
     * @return bool
     */
    public function getPublic(): bool
    {
        return $this->public;
    }

    /**
     * @var array
     * @ORM\Column(type="array")
     */
    protected array $sort = [
        'by' => \App\Domain\References\Publication::ORDER_BY_DATE,
        'direction' => \App\Domain\References\Publication::ORDER_DIRECTION_ASC,
    ];

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setSort(array $data)
    {
        $default = [
            'by' => \App\Domain\References\Publication::ORDER_BY_DATE,
            'direction' => \App\Domain\References\Publication::ORDER_DIRECTION_ASC,
        ];
        $data = array_merge($default, $data);

        if (in_array($data['by'], \App\Domain\References\Publication::ORDER_BY, true)) {
            $this->sort['by'] = $data['by'];
        }
        if (in_array($data['direction'], \App\Domain\References\Publication::ORDER_DIRECTION, true)) {
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
     * @var array
     * @ORM\Column(type="array")
     */
    protected array $template = [
        'list' => '',
        'short' => '',
        'full' => '',
    ];

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setTemplate(array $data)
    {
        $default = [
            'list' => '',
            'short' => '',
            'full' => '',
        ];
        $data = array_merge($default, $data);

        $this->template = [
            'list' => $data['list'],
            'short' => $data['short'],
            'full' => $data['full'],
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
     * @var array
     * @ORM\OneToMany(targetEntity="\App\Domain\Entities\File\PublicationCategoryFileRelation", mappedBy="publication_category", orphanRemoval=true)
     * @ORM\OrderBy({"order": "ASC"})
     */
    protected $files = [];

    /**
     * @param Collection $categories
     *
     * @return Collection
     */
    public function getNested(Collection $categories)
    {
        $result = collect([$this]);

        if ($this->getChildren()) {
            // @var \App\Domain\Entities\Publication\Category $category
            foreach ($categories->where('parent', $this->getUuid()) as $child) {
                $result = $result->merge($child->getNested($categories));
            }
        }

        return $result;
    }
}
