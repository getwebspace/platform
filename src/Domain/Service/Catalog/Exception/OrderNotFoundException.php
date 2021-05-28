<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog\Exception;

use App\Domain\AbstractException;

class OrderNotFoundException extends AbstractException
{
    protected $message = 'EXCEPTION_ORDER_NOT_FOUND';
}
