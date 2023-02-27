<?php declare(strict_types=1);

namespace App\Domain\Service\File\Exception;

use App\Domain\AbstractNotFoundException;

class RelationNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_RELATION_NOT_FOUND';
}
