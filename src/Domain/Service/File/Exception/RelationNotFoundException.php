<?php declare(strict_types=1);

namespace App\Domain\Service\File\Exception;

use App\Domain\AbstractException;

class RelationNotFoundException extends AbstractException
{
    protected $message = 'EXCEPTION_RELATION_NOT_FOUND';
}
