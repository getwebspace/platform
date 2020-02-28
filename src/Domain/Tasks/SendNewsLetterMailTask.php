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
            'type' => 'all', // all, subscribers, users
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

            // список адресов
            switch ($args['type']) {
                case 'all':
                    $list = collect()
                        ->merge(collect($userRepository->findBy(['allow_mail' => true]))->pluck('email')->all())
                        ->merge(collect($subscriberRepository->findAll())->pluck('email')->all())
                        ->unique();
                    break;

                case 'subscribers':
                    $list = collect()
                        ->merge(collect($subscriberRepository->findAll())->pluck('email')->all());
                    break;

                case 'users':
                    $list = collect()
                        ->merge(collect($userRepository->findBy(['allow_mail' => true]))->pluck('email')->all());
                    break;
            }

            if (isset($list)) {
                $perPage = 10;
                $count = ceil($list->count() / $perPage);

                for($i = 0; $i < $count; $i++){
                    foreach ($list->forPage($i, $perPage) as $email) {
                        $mail = Mail::send(array_merge($args, ['to' => $email]));

                        if ($mail !== false) {
                            if (!$mail->isError()) {
                                $this->logger->info('Mail newsletter is sent', ['mailto' => $email]);
                            } else {
                                $this->logger->warning('Mail newsletter will not sent', ['mailto' => $email, 'error' => $mail->ErrorInfo]);
                            }
                        }
                    }

                    sleep(10);
                }

                return $this->setStatusDone();
            }
        }

        $this->setStatusFail();
    }
}
