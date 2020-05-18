<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Application\Mail;
use App\Domain\AbstractTask;
use App\Domain\Service\User\UserService;
use App\Domain\Service\User\SubscriberService as UserSubscriberService;

class SendNewsLetterMailTask extends AbstractTask
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
            $userService = UserService::getFromContainer($this->container);
            $userSubscriberService = UserSubscriberService::getFromContainer($this->container);

            // список адресов
            switch ($args['type']) {
                case 'all':
                    $list = collect()
                        ->merge($userService->read(['allow_mail' => true])->pluck('email')->all())
                        ->merge($userSubscriberService->read()->pluck('email')->all())
                        ->unique();

                    break;

                case 'subscribers':
                    $list = collect()
                        ->merge($userSubscriberService->read()->pluck('email')->all());

                    break;

                case 'users':
                    $list = collect()
                        ->merge($userService->read(['allow_mail' => true])->pluck('email')->all());

                    break;
            }

            if (isset($list)) {
                $perPage = 10;
                $count = ceil($list->count() / $perPage);

                for ($i = 0; $i < $count; $i++) {
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
