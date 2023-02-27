<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog\Exception;

use App\Domain\AbstractExtFoundException;

class OrderStatusNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_ORDER_STATUS_NOT_FOUND';
}
