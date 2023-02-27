<?php declare(strict_types=1);

namespace App\Domain\Service\Form\Exception;

use App\Domain\AbstractNotFoundException;

class FormDataNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_FORM_DATA_NOT_FOUND';
}
