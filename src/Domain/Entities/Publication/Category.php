<?php

namespace App\Domain\Entities\Publication;

use Alksily\Entity\Collection;
use Alksily\Entity\Model;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="publication_category")
 */
class Category extends Model
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
     * @ORM\Column(type="string", unique=true)
     */
    public $address;

    /**
     * @ORM\Column(type="string")
     */
    public $title;

    /**
     * @ORM\Column(type="string")
     */
    public $description;

    /**
     * @ORM\Column(type="uuid", options={"default": NULL})
     */
    public $parent;

    /**
     * @ORM\Column(type="integer", options={"default": "10"})
     */
    public $pagination;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    public $children = false;

    /**
     * @ORM\Column(type="boolean", options={"default": true})
     */
    public $public = true;

    /**
     * @var array
     * @ORM\Column(type="array", nullable=true)
     */
    public $sort = [
        'by' => \App\Domain\References\Publication::ORDER_BY_DATE,
        'direction' => \App\Domain\References\Publication::ORDER_DIRECTION_ASC,
    ];

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
     * @var array
     * @ORM\Column(type="array")
     */
    public $template = [
        'list' => '',
        'short' => '',
        'full' => '',
    ];

    /**
     * @var array
     * @ORM\ManyToMany(targetEntity="App\Domain\Entities\File", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\JoinTable(name="publication_category_files",
     *  joinColumns={@ORM\JoinColumn(name="category_uuid", referencedColumnName="uuid")},
     *  inverseJoinColumns={@ORM\JoinColumn(name="file_uuid", referencedColumnName="uuid")}
     * )
     */
    protected $files = [];

    public function addFile(\App\Domain\Entities\File $file)
    {
        $this->files[] = $file;
    }

    public function addFiles(array $files)
    {
        foreach ($files as $file) {
            $this->addFile($file);
        }
    }

    public function removeFile(\App\Domain\Entities\File $file)
    {
        foreach ($this->files as $key => $value) {
            if ($file === $value) {
                unset($this->files[$key]);
                $value->unlink();
            }
        }
    }

    public function removeFiles(array $files)
    {
        foreach ($files as $file) {
            $this->removeFile($file);
        }
    }

    public function clearFiles()
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
    public static function getChildren(Collection $categories, \App\Domain\Entities\Publication\Category $parent)
    {
        $result = collect([$parent]);

        if ($parent->children) {
            /** @var \App\Domain\Entities\Publication\Category $category */
            foreach ($categories->where('parent', $parent->uuid) as $child) {
                $result = $result->merge(static::getChildren($categories, $child));
            }
        }

        return $result;
    }
}
