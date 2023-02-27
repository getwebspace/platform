<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog\Exception;

use App\Domain\AbstractNotFoundException;

class CategoryNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_CATEGORY_NOT_FOUND';
}
