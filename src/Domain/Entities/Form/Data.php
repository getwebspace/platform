<?php declare(strict_types=1);

namespace App\Domain\Entities\Form;

use App\Domain\AbstractEntity;
use App\Domain\Traits\FileTrait;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Domain\Repository\Form\DataRepository")
 * @ORM\Table(name="form_data")
 */
class Data extends AbstractEntity
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
     * @ORM\OneToMany(targetEntity="\App\Domain\Entities\File\FormDataFileRelation", mappedBy="form_data")
     * @ORM\OrderBy({"order": "ASC"})
     */
    protected $files = [];
}
