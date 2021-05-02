<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use App\Domain\Traits\FileTrait;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity(repositoryClass="App\Domain\Repository\PublicationRepository")
 * @ORM\Table(name="publication")
 */
class Publication extends AbstractEntity
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
     * @var null|Uuid
     * @ORM\Column(type="uuid", nullable=true, options={"default": \Ramsey\Uuid\Uuid::NIL})
     */
    protected ?Uuid $user_uuid;

    /**
     * @var null|User
     * @ORM\ManyToOne(targetEntity="App\Domain\Entities\User")
     * @ORM\JoinColumn(name="user_uuid", referencedColumnName="uuid")
     */
    protected ?User $user = null;

    /**
     * @param string|User $user
     *
     * @return $this
     */
    public function setUser($user)
    {
        if (is_a($user, User::class)) {
            $this->user_uuid = $user->getUuid();
            $this->user = $user;
        } else {
            $this->user_uuid = null;
            $this->user = null;
        }

        return $this;
    }

    /**
     * @return null|User
     */
    public function getUser(): ?User
    {
        return $this->user;
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
     * @var string|uuid
     * @ORM\Column(type="uuid", options={"default": \Ramsey\Uuid\Uuid::NIL})
     */
    protected $category = \Ramsey\Uuid\Uuid::NIL;

    /**
     * @param string|Uuid $uuid
     *
     * @return $this
     */
    public function setCategory($uuid)
    {
        $this->category = $this->getUuidByValue($uuid);

        return $this;
    }

    /**
     * @return string|uuid
     */
    public function getCategory()
    {
        return $this->category;
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
     * @ORM\Column(type="array")
     */
    protected array $content = [
        'short' => '',
        'full' => '',
    ];

    /**
     * @param array $data
     *
     * @return $this
     */
    public function setContent(array $data)
    {
        $default = [
            'short' => '',
            'full' => '',
        ];
        $data = array_merge($default, $data);

        $this->content = [
            'short' => $data['short'],
            'full' => $data['full'],
        ];

        return $this;
    }

    /**
     * @return array
     */
    public function getContent(): array
    {
        return $this->content;
    }

    /**
     * @var array
     * @ORM\Column(type="array")
     */
    protected array $poll = [
        // 'question' => '',
        // 'answer' => '',
    ];

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
     * @ORM\OneToMany(targetEntity="\App\Domain\Entities\File\PublicationFileRelation", mappedBy="publication", orphanRemoval=true)
     * @ORM\OrderBy({"order": "ASC"})
     */
    protected $files = [];
}
