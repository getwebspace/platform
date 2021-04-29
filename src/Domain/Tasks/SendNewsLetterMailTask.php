<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Application\Mail;
use App\Domain\AbstractTask;
use App\Domain\Service\User\SubscriberService as UserSubscriberService;
use App\Domain\Service\User\UserService;

class SendNewsLetterMailTask extends AbstractTask
{
    public const TITLE = 'Рассылка писем';

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

    protected function action(array $args = []): void
    {
        $args = array_merge(
            $this->parameter(
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
            $userService = UserService::getWithContainer($this->container);
            $userSubscriberService = UserSubscriberService::getWithContainer($this->container);

            // address list select
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
                $perPage = 5;
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

                    if ($i < $count) {
                        $this->setProgress($i, $count);
                        sleep(10);
                    }
                }

                $this->setStatusDone();
            }
        } else {
            $this->setStatusFail();
        }
    }
}
