<?php declare(strict_types=1);

namespace App\Domain\Exceptions;

use App\Domain\AbstractHttpException;

class NullPointException extends AbstractHttpException
{
    protected string $title = 'Null point';

    protected string $description = 'Check all values around.';
}
