<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog\Exception;

use App\Domain\AbstractException;

class CategoryNotFoundException extends AbstractException
{
    protected $message = 'EXCEPTION_CATEGORY_NOT_FOUND';
}
