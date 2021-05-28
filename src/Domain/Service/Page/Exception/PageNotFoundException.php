<?php declare(strict_types=1);

namespace App\Domain\Service\Page\Exception;

use App\Domain\AbstractException;

class PageNotFoundException extends AbstractException
{
    protected $message = 'EXCEPTION_PAGE_NOT_FOUND';
}
