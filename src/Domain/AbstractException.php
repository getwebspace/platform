<?php declare(strict_types=1);

namespace App\Domain;

use Exception;
use Throwable;

abstract class AbstractException extends Exception
{
    protected string $title = '';

    protected string $description = '';

    public function __construct(?string $message = null, ?Throwable $previous = null)
    {
        if ($message !== null) {
            $this->message = $message;
        }

        parent::__construct($this->message, $this->code, $previous);
    }

    public function getTitle(): string
    {
        if ($this->title) {
            return $this->title;
        }

        return $this->getDescription();
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        if ($this->description) {
            return $this->description;
        }

        return $this->getMessage();
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
