<?php declare(strict_types=1);

namespace App\Domain\Entities;

use Alksily\Entity\Model;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

/**
 * @ORM\Entity
 * @ORM\Table(name="form")
 */
class Form extends Model
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
     * @ORM\Column(type="string", options={"default": ""})
     */
    public $title = '';

    /**
     * @ORM\Column(type="string", options={"default": ""})
     */
    public $address = '';

    /**
     * @ORM\Column(type="text", options={"default": ""})
     */
    public $template = '';

    /**
     * @ORM\Column(type="boolean", options={"default": true})
     */
    public $save_data = true;

    /**
     * @ORM\Column(type="boolean", options={"default": true})
     */
    public $recaptcha = true;

    /**
     * @ORM\Column(type="array")
     */
    public $origin = [];

    /**
     * @ORM\Column(type="array")
     */
    public $mailto = [];
}
