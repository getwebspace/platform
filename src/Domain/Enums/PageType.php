<?php declare(strict_types=1);

namespace App\Domain\Enums;

enum PageType: string
{
    case HTML = 'html';
    case TEXT = 'text';
}
