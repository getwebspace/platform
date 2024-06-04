<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\AbstractHttpException;

class HttpRedirectException extends AbstractHttpException
{
    protected $code = 302;

    protected $message = 'Found';

    protected string $title = '302 Found';

    protected string $description = 'The request will be redirected to this location.';

    protected string $url;

    public function __construct(string $url = '', ?\Throwable $previous = null)
    {
        $this->url = $url;

        parent::__construct($this->message, $previous);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }
}
