<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use App\Domain\Traits\FileTrait;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface as Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Domain\Repository\PageRepository")
 * @ORM\Table(name="page")
 */
class Page extends AbstractEntity
{
    use FileTrait;

    /**
     * @ORM\Id
     * @ORM\Column(type="uuid")
     * @ORM\GeneratedValue(strategy="CUSTOM")
     * @ORM\CustomIdGenerator(class="Ramsey\Uuid\Doctrine\UuidGenerator")
     */
    protected \Ramsey\Uuid\UuidInterface $uuid;

    public function getUuid(): \Ramsey\Uuid\UuidInterface
    {
        return $this->uuid;
    }

    /**
     * @ORM\Column(type="string", length=255, options={"default": ""})
     */
    protected string $title = '';

    /**
     * @return $this
     */
    public function setTitle(string $title)
    {
        if ($this->checkStrLenMax($title, 255)) {
            $this->title = $title;
        }

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @ORM\Column(type="string", length=1000, unique=true, options={"default": ""})
     */
    protected string $address = '';

    /**
     * @return $this
     */
    public function setAddress(string $address)
    {
        if ($this->checkStrLenMax($address, 1000)) {
            $this->address = $this->getAddressByValue($address, $this->getTitle());
        }

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    /**
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
     * @ORM\Column(type="text", options={"default": ""})
     */
    protected string $content = '';

    /**
     * @return $this
     */
    public function setContent(string $content)
    {
        $this->content = $content;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @see \App\Domain\Types\PageTypeType::LIST
     * @ORM\Column(type="PageTypeType")
     */
    protected string $type = \App\Domain\Types\PageTypeType::TYPE_HTML;

    /**
     * @return $this
     */
    public function setType(string $type)
    {
        if (in_array($type, \App\Domain\Types\PageTypeType::LIST, true)) {
            $this->type = $type;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @ORM\Column(type="array")
     */
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

    /**
     * @ORM\Column(type="string", length=50, options={"default": ""})
     */
    protected string $template = '';

    /**
     * @return $this
     */
    public function setTemplate(string $template)
    {
        if ($this->checkStrLenMax($template, 50)) {
            $this->template = $template;
        }

        return $this;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @var array
     * @ORM\OneToMany(targetEntity="\App\Domain\Entities\File\PageFileRelation", mappedBy="page", orphanRemoval=true)
     * @ORM\OrderBy({"order": "ASC"})
     */
    protected $files = [];
}
