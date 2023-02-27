<?php declare(strict_types=1);

namespace App\Domain\Service\Form\Exception;

use App\Domain\AbstractNotFoundException;

class FormNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_FORM_NOT_FOUND';
}
