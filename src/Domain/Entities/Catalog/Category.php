<?php declare(strict_types=1);

namespace App\Domain\Entities\Catalog;

use App\Domain\AbstractEntity;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Tightenco\Collect\Support\Collection;

/**
 * @ORM\Entity
 * @ORM\Table(name="catalog_category", indexes={
 *     @ORM\Index(name="catalog_category_address_idx", columns={"address"}),
 *     @ORM\Index(name="catalog_category_parent_idx", columns={"parent"}),
 *     @ORM\Index(name="catalog_category_order_idx", columns={"order"})
 * })
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
    public $uuid;

    /**
     * @var Uuid
     * @ORM\Column(type="uuid", options={"default": \Ramsey\Uuid\Uuid::NIL})
     */
    public $parent = \Ramsey\Uuid\Uuid::NIL;

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    public $title = '';

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    public $description = '';

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    public $address = '';

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
     * @var array
     * @ORM\Column(type="array")
     */
    public $product = [
        'field_1' => '',
        'field_2' => '',
        'field_3' => '',
        'field_4' => '',
        'field_5' => '',
    ];

    /**
     * @ORM\Column(type="integer", options={"default": 10})
     */
    public $pagination = 10;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    public $children = false;

    /**
     * @ORM\Column(name="`order`", type="integer", options={"default": 1})
     */
    public $order = 1;

    /**
     * @var string
     *
     * @see \App\Domain\Types\Catalog\CategoryStatusType::LIST
     * @ORM\Column(type="CatalogCategoryStatusType")
     */
    public $status = \App\Domain\Types\Catalog\CategoryStatusType::STATUS_WORK;

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
     * @ORM\Column(type="array")
     */
    public $template = [
        'category' => '',
        'product' => '',
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
     * @ORM\JoinTable(name="catalog_category_files",
     *     joinColumns={@ORM\JoinColumn(name="category_uuid", referencedColumnName="uuid")},
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
     * @param Collection $categories
     * @param Category   $parent
     *
     * @return \Tightenco\Collect\Support\Collection
     */
    public static function getChildren(Collection $categories, self $parent)
    {
        $result = collect([$parent]);

        if ($parent->children) {
            // @var \App\Domain\Entities\Catalog\Category $category
            foreach ($categories->where('parent', $parent->uuid) as $child) {
                $result = $result->merge(static::getChildren($categories, $child));
            }
        }

        return $result;
    }
}
