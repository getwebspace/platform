<?php declare(strict_types=1);

namespace App\Domain\Service\Review\Exception;

use App\Domain\AbstractNotFoundException;

class ReviewNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_REVIEW_NOT_FOUND';
}
