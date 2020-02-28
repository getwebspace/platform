<?php

namespace App\Domain\Entities\Form;

use Alksily\Entity\Model;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="form_data")
 */
class Data extends Model
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
     * @ORM\Column(type="uuid", options={"default": NULL})
     */
    public $form_uuid;

    /**
     * @ORM\Column(type="text")
     */
    public $message;

    /**
     * @var DateTime
     * @ORM\Column(type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    public $date;

    /**
     * @var array
     * @ORM\ManyToMany(targetEntity="App\Domain\Entities\File", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\JoinTable(name="form_data_files",
     *  joinColumns={@ORM\JoinColumn(name="data_uuid", referencedColumnName="uuid")},
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
                $file->unlink();
            }
        }
    }

    public function removeFiles(array $files)
    {
        foreach ($files as $file) {
            $this->removeFile($file);
        }
    }

    public function removeFilesAll()
    {
        $this->files = [];
    }

    public function getFiles()
    {
        return collect($this->files);
    }

    public function hasFiles()
    {
        return count($this->files);
    }
}
