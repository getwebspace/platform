<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog\Exception;

use App\Domain\AbstractException;

class OrderStatusNotFoundException extends AbstractException
{
    protected $message = 'EXCEPTION_ORDER_STATUS_NOT_FOUND';
}
