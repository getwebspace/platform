<?php declare(strict_types=1);

namespace App\Domain\Service\Reference\Exception;

use App\Domain\AbstractException;

class MissingTitleValueException extends AbstractException
{
    protected $message = 'EXCEPTION_TITLE_MISSING';
}
