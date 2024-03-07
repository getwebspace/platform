<?php declare(strict_types=1);

namespace App\Domain\Enums;

enum UserStatus: string
{
    case WORK = 'work';
    case DELETE = 'delete';
    case BLOCK = 'block';
}
