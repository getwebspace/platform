<?php declare(strict_types=1);

namespace App\Domain\Service\Form\Exception;

use App\Domain\AbstractException;

class FormDataNotFoundException extends AbstractException
{
    protected $message = 'EXCEPTION_FORM_DATA_NOT_FOUND';
}
