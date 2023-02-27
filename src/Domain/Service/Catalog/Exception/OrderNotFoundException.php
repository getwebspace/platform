<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog\Exception;

use App\Domain\AbstractNotFoundException;

class OrderNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_ORDER_NOT_FOUND';
}
