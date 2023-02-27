<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog\Exception;

use App\Domain\AbstractNotFoundException;

class MeasureNotFoundException extends AbstractNotFoundException
{
    protected $message = 'EXCEPTION_MEASURE_NOT_FOUND';
}
