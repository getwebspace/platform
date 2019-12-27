<?php

namespace App\Domain\Tasks;

use App\Application\Mail;

class SendNewsLetterMailTask extends Task
{
    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        $default = [
            'subject' => 'WebSpaceEngine | Default subject',
            'body' => '',
            'isHtml' => true,
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
            $userRepository = $this->entityManager->getRepository(\App\Domain\Entities\User::class);
            $subscriberRepository = $this->entityManager->getRepository(\App\Domain\Entities\User\Subscriber::class);

            // список email адресов
            $list = collect()
                ->merge(collect($userRepository->findBy(['allow_mail' => true]))->pluck('email')->all())
                ->merge(collect($subscriberRepository->findAll())->pluck('email')->all())
                ->unique();

            // todo добавить разбивку на партии для отправки по 10 писем за раз
            foreach ($list as $email) {
                $mail = Mail::send(array_merge($args, ['to' => $email]));

                if ($mail !== false) {
                    if (!$mail->isError()) {
                        $this->logger->info('Mail newsletter is sent', ['mailto' => $email]);
                    } else {
                        $this->logger->warn('Mail newsletter will not sent', ['mailto' => $email, 'error' => $mail->ErrorInfo]);
                    }
                }
            }

            return $this->status_done();
        }

        $this->status_fail();
    }
}
