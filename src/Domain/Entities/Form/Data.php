<?php declare(strict_types=1);

namespace App\Domain\Entities\Form;

use App\Domain\AbstractEntity;
use App\Domain\Entities\File;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="form_data")
 */
class Data extends AbstractEntity
{
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
    protected $form_uuid = \Ramsey\Uuid\Uuid::NIL;

    /**
     * @param string|Uuid $uuid
     *
     * @return $this
     */
    public function setFormUuid($uuid)
    {
        $this->form_uuid = $this->getUuidByValue($uuid);

        return $this;
    }

    /**
     * @return Uuid
     */
    public function getFormUuid(): Uuid
    {
        return $this->form_uuid;
    }

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    protected string $message = '';

    /**
     * @param string $message
     *
     * @return $this
     */
    public function setMessage(string $message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
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
     * @ORM\ManyToMany(targetEntity="App\Domain\Entities\File", cascade={"persist", "remove"}, orphanRemoval=true)
     * @ORM\JoinTable(name="form_data_files",
     *     joinColumns={@ORM\JoinColumn(name="data_uuid", referencedColumnName="uuid")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="file_uuid", referencedColumnName="uuid")}
     * )
     */
    protected $files = [];

    /**
     * @param File $file
     *
     * @return $this
     */
    public function addFile(\App\Domain\Entities\File $file)
    {
        $this->files[] = $file;

        return $this;
    }

    /**
     * @param array $files
     *
     * @return $this
     */
    public function addFiles(array $files)
    {
        foreach ($files as $file) {
            $this->addFile($file);
        }

        return $this;
    }

    /**
     * @param File $file
     *
     * @return $this
     */
    public function removeFile(\App\Domain\Entities\File $file): void
    {
        foreach ($this->files as $key => $value) {
            if ($file === $value) {
                unset($this->files[$key]);
            }
        }
    }

    /**
     * @param array $files
     *
     * @return $this
     */
    public function removeFiles(array $files)
    {
        foreach ($files as $file) {
            $this->removeFile($file);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function clearFiles()
    {
        foreach ($this->files as $key => $file) {
            unset($this->files[$key]);
        }

        return $this;
    }

    /**
     * @param bool $raw
     *
     * @return array|\Tightenco\Collect\Support\Collection
     */
    public function getFiles($raw = false)
    {
        return $raw ? $this->files : collect($this->files);
    }

    /**
     * @return int
     */
    public function hasFiles()
    {
        return count($this->files);
    }
}
