<?php

namespace Core;

use PHPMailer\PHPMailer\PHPMailer;

class Mail
{
    /**
     * Подготавливает и возвращает объект PHPMailer
     *
     * @return PHPMailer
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public static function getMailer() {
        $default = [
            'smtp_from' => '',
            'smtp_from_name' => '',
            'smtp_login' => '',
            'smtp_pass' => '',
            'smtp_secure' => '',
            'smtp_host' => '',
            'smtp_port' => '',
        ];
        $params = array_merge($default, Common::get(array_keys($default), []));

        $mail = new PHPMailer(false);

        $mail->isSMTP();
        $mail->set('CharSet', 'utf-8');

        $mail->set('SMTPSecure', $params['smtp_secure']);
        $mail->set('Host', $params['smtp_host']);
        $mail->set('Port', $params['smtp_port']);

        $mail->set('SMTPAuth', true);
        $mail->set('Username', $params['smtp_login']);
        $mail->set('Password', $params['smtp_pass']);

        $mail->setFrom(
            $params['smtp_from'],
            $params['smtp_from_name']
        );

        return $mail;
    }

    /**
     * Производит отправку письма
     *
     * @param array $data
     *
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public static function send(array $data = [])
    {
        $default = [
            'subject' => Common::get('smtp_subject', '0x12f | Default subject'),
            'to' => '', // string|array(address=>name)
            'cc' => '', // string|array(address=>name)
            'bcc' => '', // string|array(address=>name)
            'body' => '',
            'isHtml' => false,
            'attachments' => [],
            'auto_send' => true,
        ];
        $data = array_merge($default, $data);

        $mail = static::getMailer();

        // Кому
        if ($data['to']) {
            foreach ((array)$data['to'] as $address => $name) {
                if (is_numeric($address)) {
                    $address = $name;
                    $name = '';
                }

                $mail->addAddress($address, $name);
            }
        }

        // Копия
        if ($data['cc']) {
            foreach ((array)$data['cc'] as $address => $name) {
                if (is_numeric($address)) {
                    $address = $name;
                    $name = '';
                }

                $mail->addCC($address, $name);
            }
        }

        // Скрытая копия
        if ($data['bcc']) {
            foreach ((array)$data['bcc'] as $address => $name) {
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

        foreach ((array)$data['attachments'] as $name => $file) {
            $mail->addAttachment($file, $name);
        }

        if ($data['auto_send']) { $mail->send(); }

        return $mail;
    }
}
