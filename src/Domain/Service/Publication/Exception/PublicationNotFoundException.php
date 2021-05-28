<?php declare(strict_types=1);

namespace App\Domain\Service\Publication\Exception;

use App\Domain\AbstractException;

class PublicationNotFoundException extends AbstractException
{
    protected $message = 'EXCEPTION_PUBLICATION_NOT_FOUND';
}
