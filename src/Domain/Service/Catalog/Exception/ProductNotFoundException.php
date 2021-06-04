<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog\Exception;

use App\Domain\AbstractException;

class ProductNotFoundException extends AbstractException
{
    protected $message = 'EXCEPTION_PRODUCT_NOT_FOUND';
}
