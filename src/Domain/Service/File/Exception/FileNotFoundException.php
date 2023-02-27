<?php declare(strict_types=1);

namespace App\Domain\Service\File\Exception;

use App\Domain\AbstractNotFoundException;

class FileNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_FILE_NOT_FOUND';
}
