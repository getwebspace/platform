<?php declare(strict_types=1);

namespace App\Domain\Casts\Review;

use App\Domain\Casts\Enum;

class Type extends Enum
{
    public const REVIEW = 'review';
    public const QUESTION = 'question';

    public const LIST = [
        self::REVIEW,
        self::QUESTION,
    ];
}
