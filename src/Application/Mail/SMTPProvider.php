<?php declare(strict_types=1);

namespace App\Application\Mail;

use App\Application\Mail\Exception\MailException;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP provider
 */
class SMTPProvider implements MailProviderInterface
{
    /**
     * @throws MailException
     */
    public static function send(array $data = []): void
    {
        $default = [
            'subject' => 'WebSpaceEngine | Default subject',
            'to' => '', // string|array(address=>name)
            'cc' => '', // string|array(address=>name)
            'bcc' => '', // string|array(address=>name)
            'body' => '',
            'isHtml' => false,
            'attachments' => [],

            'mail_from' => '',
            'mail_from_name' => '',
            'smtp_login' => '',
            'smtp_pass' => '',
            'smtp_secure' => '',
            'smtp_host' => '',
            'smtp_port' => '',
            'smtp_timeout' => 30,
            'smtp_options' => [],
        ];
        $data = array_merge($default, $data);

        // exceptions stay off: PHPMailer's own recipient/from validation
        // (an invalid address, say) fails cleanly into $mail->send() === false
        // + ErrorInfo this way instead of throwing from inside setup below,
        // where it wouldn't be wrapped into a MailException
        $mail = new PHPMailer(false);

        $mail->Debugoutput = 'error_log';
        $mail->Timeout = $data['smtp_timeout'];
        $mail->SMTPDebug = (int) ($_ENV['DEBUG'] ?? 0);
        $mail->SMTPOptions = $data['smtp_options'];
        $mail->isSMTP();
        $mail->set('CharSet', 'utf-8');

        $mail->set('SMTPSecure', $data['smtp_secure']);
        $mail->set('Host', $data['smtp_host']);
        $mail->set('Port', $data['smtp_port']);

        $mail->set('SMTPAuth', true);
        $mail->set('Username', $data['smtp_login']);
        $mail->set('Password', $data['smtp_pass']);

        $mail->setFrom(
            $data['mail_from'],
            $data['mail_from_name']
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

        try {
            $sent = $mail->send();
        } catch (Exception $e) {
            // shouldn't happen with exceptions disabled above, but a
            // dependency throwing anyway must not look like a silent success
            throw new MailException($e->getMessage(), 0, $e);
        }

        if (!$sent) {
            throw new MailException($mail->ErrorInfo !== '' ? $mail->ErrorInfo : 'PHPMailer reported failure without a reason');
        }
    }
}
