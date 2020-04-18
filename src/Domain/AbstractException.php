<?php declare(strict_types=1);

namespace App\Domain;

use Exception;
use Throwable;

abstract class AbstractException extends Exception
{
    /**
     * @var string
     */
    protected $title = '';

    /**
     * @var string
     */
    protected $description = '';

    /**
     * @param string                 $message
     * @param null|Throwable         $previous
     */
    public function __construct(?string $message = null, ?Throwable $previous = null)
    {
        if ($message !== null) {
            $this->message = $message;
        }

        parent::__construct($this->message, $this->code, $previous);
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     *
     * @return self
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

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
     * @param string $description
     *
     * @return self
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
