<?php declare(strict_types=1);

namespace App\Domain\Service\Publication\Exception;

use App\Domain\AbstractNotFoundException;

class PublicationNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_PUBLICATION_NOT_FOUND';
}
