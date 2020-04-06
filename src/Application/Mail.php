<?php declare(strict_types=1);

namespace App\Application;

use PHPMailer\PHPMailer\PHPMailer;

class Mail
{
    /**
     * Производит отправку письма
     *
     * @param array $data
     *
     * @throws \PHPMailer\PHPMailer\Exception
     *
     * @return PHPMailer
     */
    public static function send(array $data = [])
    {
        $default = [
            'smtp_from' => '',
            'smtp_from_name' => '',
            'smtp_login' => '',
            'smtp_pass' => '',
            'smtp_secure' => '',
            'smtp_host' => '',
            'smtp_port' => '',

            'subject' => 'WebSpaceEngine | Default subject',
            'to' => '', // string|array(address=>name)
            'cc' => '', // string|array(address=>name)
            'bcc' => '', // string|array(address=>name)
            'body' => '',
            'isHtml' => false,
            'attachments' => [],
            'auto_send' => true,
        ];
        $data = array_merge($default, $data);

        $mail = new PHPMailer(false);

        $mail->isSMTP();
        $mail->set('CharSet', 'utf-8');

        $mail->set('SMTPSecure', $data['smtp_secure']);
        $mail->set('Host', $data['smtp_host']);
        $mail->set('Port', $data['smtp_port']);

        $mail->set('SMTPAuth', true);
        $mail->set('Username', $data['smtp_login']);
        $mail->set('Password', $data['smtp_pass']);

        $mail->setFrom(
            $data['smtp_from'],
            $data['smtp_from_name']
        );

        // Кому
        if ($data['to']) {
            foreach ((array) $data['to'] as $address => $name) {
                if (is_numeric($address)) {
                    $address = $name;
                    $name = '';
                }

                $mail->addAddress($address, $name);
            }
        }

        // Копия
        if ($data['cc']) {
            foreach ((array) $data['cc'] as $address => $name) {
                if (is_numeric($address)) {
                    $address = $name;
                    $name = '';
                }

                $mail->addCC($address, $name);
            }
        }

        // Скрытая копия
        if ($data['bcc']) {
            foreach ((array) $data['bcc'] as $address => $name) {
                if (is_numeric($address)) {
                    $address = $name;
                    $name = '';
                }

                $mail->addBCC($address, $name);
            }
        }

        $mail->set('Subject', $data['subject']);
        $mail->set('Body', $data['body']);
        $mail->isHTML($data['isHtml']);

        foreach ((array) $data['attachments'] as $name => $file) {
            $mail->addAttachment($file, $name);
        }

        if ($data['auto_send']) {
            $mail->send();
        }

        return $mail;
    }
}
