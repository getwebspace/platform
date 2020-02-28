<?php

namespace App\Domain\Tasks;

use App\Application\Mail;

class SendMailTask extends Task
{
    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        $default = [
            'subject' => 'WebSpaceEngine | Default subject',
            'to' => '', // string|array(address=>name)
            'cc' => '', // string|array(address=>name)
            'bcc' => '', // string|array(address=>name)
            'body' => '',
            'isHtml' => false,
            'attachments' => [],
        ];
        $params = array_merge($default, $params);

        return parent::execute($params);
    }

    protected function action(array $args = [])
    {
        $args = array_merge(
            $this->getParameter(
                [
                    'smtp_from', 'smtp_from_name',
                    'smtp_login', 'smtp_pass',
                    'smtp_host', 'smtp_port',
                    'smtp_secure',
                    'subject',
                ]
            ),
            $args,
            ['auto_send' => true]
        );

        if ($args['smtp_host'] && $args['smtp_login'] && $args['smtp_pass']) {
            $mail = Mail::send($args);

            if ($mail !== false) {
                if (!$mail->isError()) {
                    $this->logger->info('Mail is sent', ['mailto' => $args['to']]);
                    $this->setStatusDone();
                } else {
                    $this->logger->warning('Mail will not sent', ['mailto' => $args['to'], 'error' => $mail->ErrorInfo]);
                    $this->setStatusFail();
                }

                return;
            }
        }

        $this->setStatusFail();
    }
}
