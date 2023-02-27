<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog\Exception;

use App\Domain\AbstractNotFoundException;

class ProductNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_PRODUCT_NOT_FOUND';
}
