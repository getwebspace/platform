<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog\Exception;

use App\Domain\AbstractException;

class AttributeNotFoundException extends AbstractException
{
    protected $message = 'EXCEPTION_ATTRIBUTE_NOT_FOUND';
}
