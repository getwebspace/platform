<?php declare(strict_types=1);

namespace App\Application;

use App\Application\Mail\Exception\MailException;
use App\Application\Mail\SMTPProvider;

class Mail
{
    /**
     * @throws MailException
     */
    public static function send(array $data = []): void
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

        SMTPProvider::send($data);
    }
}
