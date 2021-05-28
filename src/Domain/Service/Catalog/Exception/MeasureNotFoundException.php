<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog\Exception;

use App\Domain\AbstractException;

class MeasureNotFoundException extends AbstractException
{
    protected $message = 'EXCEPTION_MEASURE_NOT_FOUND';
}
