<?php declare(strict_types=1);

namespace App\Application;

use App\Application\Mail\SMTPProvider;
use App\Application\Mail\SPProvider;

class Mail
{
    public static function send(array $data = []): bool
    {
        $default = [
            // common section
            'mail_from' => '',
            'mail_from_name' => '',
            'subject' => 'WebSpaceEngine | Default subject',
            'to' => '', // string|array(address=>name)
            'cc' => '', // string|array(address=>name)
            'bcc' => '', // string|array(address=>name)
            'body' => '',
            'isHtml' => false,
            'attachments' => [],

            // sendpulse section
            'sendpulse_id' => '',
            'sendpulse_secret' => '',

            // smtp section
            'smtp_login' => '',
            'smtp_pass' => '',
            'smtp_secure' => '',
            'smtp_host' => '',
            'smtp_port' => '',
            'smtp_timeout' => 30,
            'smtp_options' => [],
        ];
        $data = array_merge($default, $data);

        return match (true) {
            $data['sendpulse_id'] && $data['sendpulse_secret'] => SPProvider::send($data),
            default => SMTPProvider::send($data),
        };
    }
}
