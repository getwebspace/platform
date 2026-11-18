<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User\NewsLetter;

use App\Application\Actions\Cup\User\UserAction;

class CreateAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            $subject = trim((string) $this->getParam('subject', ''));
            $body = trim((string) $this->getParam('body', ''));
            $type = (string) $this->getParam('type', '');

            // a letter without a subject, a text or a known audience would be "sent" to nobody, or to everybody empty
            if ($subject === '') {
                $this->addError('subject', 'EXCEPTION_TITLE_MISSING');
            }
            if ($body === '') {
                $this->addError('body', 'EXCEPTION_MESSAGE_MISSING');
            }
            if (!in_array($type, \App\Domain\References\User::NEWSLETTER_TYPE, true)) {
                $this->addError('type', 'EXCEPTION_WRONG_TYPE');
            }

            if (!$this->hasError()) {
                $task = new \App\Domain\Tasks\SendNewsLetterMailTask($this->container);
                $task->execute([
                    'subject' => $subject,
                    'body' => $body,
                    'type' => $type,
                ]);

                // run worker
                \App\Domain\AbstractTask::worker($task);

                return $this->respondWithRedirect('/cup/user/newsletter');
            }
        }

        return $this->respondWithTemplate('cup/user/newsletter/form.twig');
    }
}
