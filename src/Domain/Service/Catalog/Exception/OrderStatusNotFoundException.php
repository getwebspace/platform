<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog\Exception;

class OrderStatusNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_ORDER_STATUS_NOT_FOUND';
}
