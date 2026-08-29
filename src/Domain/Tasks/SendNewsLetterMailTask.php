<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Application\Mail;
use App\Application\Mail\Exception\MailException;
use App\Domain\AbstractTask;
use App\Domain\Service\User\SubscriberService as UserSubscriberService;
use App\Domain\Service\User\UserService;

class SendNewsLetterMailTask extends AbstractTask
{
    public const TITLE = 'Mailing of letters';

    public function execute(array $params = []): \App\Domain\Models\Task
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
                    ->merge($userService->read(['status' => \App\Domain\Casts\User\Status::WORK, 'is_allow_mail' => true])->pluck('email')->all())
                    ->merge($userSubscriberService->read()->pluck('email')->all())
                    ->unique();

                break;

            case 'subscribers':
                $list = collect()
                    ->merge($userSubscriberService->read()->pluck('email')->all());

                break;

            case 'users':
                $list = collect()
                    ->merge($userService->read(['status' => \App\Domain\Casts\User\Status::WORK, 'is_allow_mail' => true])->pluck('email')->all());

                break;
        }

        if (isset($list)) {
            $perPage = 5;
            $count = ceil($list->count() / $perPage);
            $sent = 0;
            $failed = 0;
            $lastError = '';

            for ($i = 0; $i < $count; ++$i) {
                foreach ($list->forPage($i, $perPage) as $email) {
                    try {
                        // a single bad address must not stop the rest of
                        // the batch, so this is caught per-recipient rather
                        // than left to abort the whole task
                        Mail::send(array_merge($args, ['to' => $email]));

                        ++$sent;
                        $this->logger->info('Mail: newsletter sent', ['mailto' => $email]);
                    } catch (MailException $e) {
                        ++$failed;
                        $lastError = $e->getMessage();
                        $this->logger->error('Mail: newsletter send failed', ['mailto' => $email, 'reason' => $e->getMessage()]);
                    }
                }

                $this->setProgress($i, $count);
                sleep(10);
            }

            $this->container->get(\App\Application\PubSub::class)->publish('task:mail:send');

            $summary = "sent {$sent}, failed {$failed}";

            if ($failed > 0) {
                // done rather than fail - most of the run may have gone
                // through fine, the counts and last reason are what an
                // admin actually needs to see, not a blanket "it broke"
                $this->setStatusDone(mb_substr("{$summary} (last error: {$lastError})", 0, 900));
            } else {
                $this->setStatusDone($summary);
            }
        }
    }
}
