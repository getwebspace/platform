<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog\Exception;

use App\Domain\AbstractNotFoundException;

class AttributeNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_ATTRIBUTE_NOT_FOUND';
}
