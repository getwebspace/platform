<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Application\Mail;
use App\Domain\AbstractTask;
use App\Domain\Service\User\SubscriberService as UserSubscriberService;
use App\Domain\Service\User\UserService;

class SendNewsLetterMailTask extends AbstractTask
{
    public const TITLE = 'Mailing of letters';

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

    /**
     * @throws \PHPMailer\PHPMailer\Exception
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \App\Domain\Service\Task\Exception\TaskNotFoundException
     */
    protected function action(array $args = []): void
    {
        $args = array_merge(
            $this->parameter(
                [
                    'mail_from', 'mail_from_name',
                    'sendpulse_id', 'sendpulse_secret',
                    'smtp_login', 'smtp_pass',
                    'smtp_host', 'smtp_port',
                    'smtp_secure',
                ]
            ),
            ['subject' => $this->parameter('mail_subject', 'WebSpaceEngine | Default subject')],
            $args
        );

        $userService = $this->container->get(UserService::class);
        $userSubscriberService = $this->container->get(UserSubscriberService::class);

        // address list select
        switch ($args['type']) {
            case 'all':
                $list = collect()
                    ->merge($userService->read(['status' => \App\Domain\Types\UserStatusType::STATUS_WORK, 'allow_mail' => true])->pluck('email')->all())
                    ->merge($userSubscriberService->read()->pluck('email')->all())
                    ->unique();

                break;

            case 'subscribers':
                $list = collect()
                    ->merge($userSubscriberService->read()->pluck('email')->all());

                break;

            case 'users':
                $list = collect()
                    ->merge($userService->read(['status' => \App\Domain\Types\UserStatusType::STATUS_WORK, 'allow_mail' => true])->pluck('email')->all());

                break;
        }

        if (isset($list)) {
            $perPage = 5;
            $count = ceil($list->count() / $perPage);

            for ($i = 0; $i < $count; ++$i) {
                foreach ($list->forPage($i, $perPage) as $email) {
                    $mail = Mail::send(array_merge($args, ['to' => $email]));

                    if ($mail !== false) {
                        $this->logger->info('Mail newsletter is sent', ['mailto' => $email]);
                    } else {
                        $this->logger->warning('Mail newsletter will not sent', ['mailto' => $email]);
                    }
                }

                $this->setProgress($i, $count);
                sleep(10);
            }

            $this->container->get(\App\Application\PubSub::class)->publish('task:mail:send');

            $this->setStatusDone();
        }
    }
}
