<?php declare(strict_types=1);

namespace App\Application\Mail;

interface MailProviderInterface
{
    public static function send(array $data = []);
}
