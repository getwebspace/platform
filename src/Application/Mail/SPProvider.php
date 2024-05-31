<?php declare(strict_types=1);

namespace App\Application\Mail;

use Sendpulse\RestApi\ApiClient;
use Sendpulse\RestApi\Storage\FileStorage;

/**
 * SendPulse provider
 */
class SPProvider implements MailProviderInterface
{
    public static function send(array $data = []): bool
    {
        $default = [
            'sendpulse_id' => '',
            'sendpulse_secret' => '',

            'mail_from' => '',
            'mail_from_name' => '',
            'subject' => '',
            'to' => '', // string|array(address=>name)
            'cc' => '', // string|array(address=>name)
            'bcc' => '', // string|array(address=>name)
            'body' => '',
            'isHtml' => false,
            'attachments' => [],
        ];
        $data = array_merge($default, $data);

        $email = [];
        $email['subject'] = $data['subject'];
        $email[$data['isHtml'] ? 'html' : 'text'] = $data['body'];
        $email['from'] = [
            'name' => $data['mail_from_name'],
            'email' => $data['mail_from'],
        ];

        foreach (['to', 'cc', 'bcc'] as $type) {
            if ($data[$type]) {
                $email[$type] = [];

                foreach ((array) $data[$type] as $address => $name) {
                    if (is_numeric($address)) {
                        $address = $name;
                        $name = '';
                    }
                    $email[$type][] = [
                        'name' => $name,
                        'email' => $address,
                    ];
                }
            }
        }

        foreach ((array) $data['attachments'] as $name => $file) {
            $email['attachments'] ??= [];
            $email['attachments'][$name] = $file;
        }

        $SPApiClient = new ApiClient($data['sendpulse_id'], $data['sendpulse_secret'], new FileStorage(CACHE_DIR . '/'));
        $response = $SPApiClient->smtpSendMail($email);

        return $response['result'];
    }
}
