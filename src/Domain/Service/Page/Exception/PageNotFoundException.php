<?php declare(strict_types=1);

namespace App\Domain\Service\Page\Exception;

use App\Domain\AbstractNotFoundException;

class PageNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_PAGE_NOT_FOUND';
}
