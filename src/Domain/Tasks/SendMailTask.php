<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Application\Mail;
use App\Application\Mail\Exception\MailException;
use App\Domain\AbstractTask;

class SendMailTask extends AbstractTask
{
    public const TITLE = 'Sending mail';

    public function execute(array $params = []): \App\Domain\Models\Task
    {
        $default = [
            'subject' => '',
            'to' => '', // string|array(address=>name)
            'cc' => '', // string|array(address=>name)
            'bcc' => '', // string|array(address=>name)
            'body' => '',
            'isHtml' => true,
            'template' => '',
            'data' => [],
            'attachments' => [],
        ];
        $params = array_merge($default, $params);

        return parent::execute($params);
    }

    /**
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \App\Domain\Service\Task\Exception\TaskNotFoundException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function action(array $args = []): void
    {
        $params = array_merge(
            $this->parameter(
                [
                    'mail_from', 'mail_from_name',
                    'smtp_login', 'smtp_pass',
                    'smtp_host', 'smtp_port',
                    'smtp_secure',
                ]
            ),
            [
                'subject' => $args['subject'] ?: $this->parameter('mail_subject', 'WebSpaceEngine | Default subject'),
                'to' => $args['to'],
                'cc' => $args['cc'],
                'bcc' => $args['bcc'],
                'body' => $args['body'],
                'isHtml' => (bool) $args['isHtml'],
                'attachments' => (array) $args['attachments'],
                'auto_send' => true,
            ]
        );

        // extension part
        if ($args['template'] || $args['data']) {
            if (str_ends_with($args['template'], '.twig') || str_ends_with($args['template'], '.html')) {
                $params['body'] = $this->render($args['template'], $args['data']);
                $params['isHtml'] = true;
            } else {
                if ($args['template']) {
                    $params['body'] = $this->renderFromString($args['template'], $args['data']);
                    $params['isHtml'] = true;
                } elseif (is_array($args['data'])) {
                    $params['body'] = json_encode(str_escape($args['data']), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    $params['isHtml'] = false;
                } else {
                    $params['body'] = $args['data'];
                    $params['isHtml'] = false;
                }
            }
        }

        $mailto = static::describeRecipients($args['to']);

        try {
            Mail::send($params);
        } catch (MailException $e) {
            // the real reason ("SMTP connect() failed...", "Could not
            // authenticate.", ...) goes both to the log and into the task's
            // own output, so a failed send is never mistaken for a sent one
            $this->logger->error('Mail: send failed', ['mailto' => $mailto, 'reason' => $e->getMessage()]);
            $this->setStatusFail(mb_substr($e->getMessage(), 0, 900));

            $this->container->get(\App\Application\PubSub::class)->publish('task:mail:send');

            return;
        }

        $this->logger->info('Mail: sent', ['mailto' => $mailto]);
        $this->setStatusDone('sent to ' . $mailto);

        $this->container->get(\App\Application\PubSub::class)->publish('task:mail:send');
    }

    /**
     * @param array<int|string, string>|string $to
     */
    private static function describeRecipients(array|string $to): string
    {
        if (is_string($to)) {
            return $to;
        }

        return implode(', ', array_map(
            static fn ($address, $name): string => is_numeric($address) ? (string) $name : (string) $address,
            array_keys($to),
            $to
        )) ?: '(none)';
    }
}
