<?php declare(strict_types=1);

namespace App\Domain\Entities\Publication;

use Alksily\Entity\Collection;
use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="publication_category")
 */
class Category extends AbstractEntity
{
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
     * @ORM\Column(type="string", unique=true, options={"default": ""})
     */
    protected string $address = '';

    /**
     * @param string $address
     *
     * @return $this
     */
    public function setAddress(string $address)
    {
        if ($this->checkStrLenMax($address, 255)) {
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
     * @var string
     * @ORM\Column(type="string", options={"default": ""})
     */
    protected string $title = '';

    /**
     * @param string $title
     *
     * @return $this
     */
    public function setTitle(string $title)
    {
        if ($this->checkStrLenMax($title, 50)) {
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
     * @ORM\Column(type="string", options={"default": ""})
     */
    protected string $description;

    /**
     * @param string $description
     *
     * @return $this
     */
    public function setDescription(string $description)
    {
        if ($this->checkStrLenMax($description, 255)) {
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
        if ($this->checkUuidByValue($uuid)) {
            $this->parent = $uuid;
        }

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

        $this->sort = [
            'by' => $data['by'],
            'direction' => $data['direction'],
        ];

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
     * @ORM\ManyToMany(targetEntity="App\Domain\Entities\File", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\JoinTable(name="publication_category_files",
     *     joinColumns={@ORM\JoinColumn(name="category_uuid", referencedColumnName="uuid")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="file_uuid", referencedColumnName="uuid")}
     * )
     */
    protected $files;

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
     * @param Collection $categories
     * @param Category   $parent
     *
     * @return Collection
     */
    public static function getChildrenCategories(Collection $categories, self $parent)
    {
        $result = collect();

        if ($parent->children) {
            // @var \App\Domain\Entities\Publication\Category $category
            foreach ($categories->where('parent', $parent->uuid) as $child) {
                $result = $result->merge(static::getChildrenCategories($categories, $child));
            }
        }

        return $result;
    }
}
