<?php declare(strict_types=1);

namespace App\Domain\Entities;

use App\Domain\AbstractEntity;
use App\Domain\Entities\Publication\Category as PublicationCategory;
use App\Domain\Traits\FileTrait;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface as Uuid;

#[ORM\Table(name: 'publication')]
#[ORM\Entity(repositoryClass: 'App\Domain\Repository\PublicationRepository')]
class Publication extends AbstractEntity
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

    #[ORM\Column(type: 'uuid', nullable: true, options: ['default' => null])]
    protected ?\Ramsey\Uuid\UuidInterface $user_uuid;

    #[ORM\ManyToOne(targetEntity: 'App\Domain\Entities\User')]
    #[ORM\JoinColumn(name: 'user_uuid', referencedColumnName: 'uuid')]
    protected ?User $user;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    #[ORM\Column(type: 'string', length: 1000, unique: true, options: ['default' => ''])]
    protected string $address = '';

    /**
     * @return $this
     */
    public function setAddress(string $address)
    {
        if ($this->checkStrLenMax($address, 1000)) {
            $this->address = $this->getAddressByValue($address, str_replace('/', '-', $this->getTitle()));
        }

        return $this;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    #[ORM\Column(type: 'string', length: 255, options: ['default' => ''])]
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

    #[ORM\Column(type: 'uuid', nullable: true, options: ['default' => \Ramsey\Uuid\Uuid::NIL])]
    protected ?\Ramsey\Uuid\UuidInterface $category_uuid;

    #[ORM\ManyToOne(targetEntity: 'App\Domain\Entities\Publication\Category')]
    #[ORM\JoinColumn(name: 'category_uuid', referencedColumnName: 'uuid')]
    protected ?PublicationCategory $category;

    /**
     * @return $this
     */
    public function setCategory(?PublicationCategory $category)
    {
        if (is_a($category, PublicationCategory::class)) {
            $this->category_uuid = $category->getUuid();
            $this->category = $category;
        } else {
            $this->category_uuid = null;
            $this->category = null;
        }

        return $this;
    }

    public function getCategory(): ?PublicationCategory
    {
        return $this->category;
    }

    #[ORM\Column(type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    protected \DateTime $date;

    /**
     * @param mixed $timezone
     * @param mixed $date
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function setDate($date, $timezone = 'UTC')
    {
        $this->date = $this->getDateTimeByValue($date, $timezone);

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    protected array $content = [
        'short' => '',
        'full' => '',
    ];

    /**
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

    public function getContent(): array
    {
        return $this->content;
    }

    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    protected array $poll = [
        // 'question' => '',
        // 'answer' => '',
    ];

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

    #[ORM\Column(type: 'string', length: 255, options: ['default' => ''])]
    protected string $external_id = '';

    /**
     * @return $this
     */
    public function setExternalId(string $external_id): self
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

    /**
     * @var array
     */
    #[ORM\OneToMany(targetEntity: '\App\Domain\Entities\File\PublicationFileRelation', mappedBy: 'publication', orphanRemoval: true)]
    #[ORM\OrderBy(['order' => 'ASC'])]
    protected $files = [];

    /**
     * Return model as array
     */
    public function toArray(): array
    {
        return array_serialize([
            'uuid' => $this->uuid,
            'user' => $this->user_uuid ?: \Ramsey\Uuid\Uuid::NIL,
            'address' => $this->address,
            'title' => $this->title,
            'category' => $this->category,
            'date' => $this->date,
            'content' => $this->content,
            'files' => $this->files,
            'meta' => $this->meta,
        ]);
    }
}
