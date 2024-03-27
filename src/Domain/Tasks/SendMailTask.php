<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Application\Mail;
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
     * @throws \PHPMailer\PHPMailer\Exception
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
                    'sendpulse_id', 'sendpulse_secret',
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

        $mail = Mail::send($params);

        $this->container->get(\App\Application\PubSub::class)->publish('task:mail:send');

        if ($mail !== false) {
            $this->logger->info('Mail is sent', ['mailto' => $args['to']]);
            $this->setStatusDone('ok');
        } else {
            $this->logger->warning('Mail will not sent', ['mailto' => $args['to']]);
            $this->setStatusFail();
        }
    }
}
