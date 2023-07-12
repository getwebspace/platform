<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog\Exception;

use App\Domain\AbstractNotFoundException;

class OrderShippingLimitException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_ORDER_SHIPPING_LIMIT';
}
