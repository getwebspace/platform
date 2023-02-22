<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\AbstractHttpException;

class JWTExpiredException extends AbstractHttpException
{
    protected string $title = 'JWT Expired';

    protected string $description = 'Get new JWT';

    protected $message = 'EXCEPTION_EXPIRED_JWT';
}
