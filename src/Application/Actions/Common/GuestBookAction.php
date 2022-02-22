<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Domain\AbstractAction;
use App\Domain\Entities\GuestBook;
use App\Domain\Service\GuestBook\Exception\MissingEmailValueException;
use App\Domain\Service\GuestBook\Exception\MissingMessageValueException;
use App\Domain\Service\GuestBook\Exception\MissingNameValueException;
use App\Domain\Service\GuestBook\Exception\WrongEmailValueException;
use App\Domain\Service\GuestBook\GuestBookService;
use App\Domain\Service\Notification\NotificationService;

class GuestBookAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $guestBookService = $this->container->get(GuestBookService::class);
        $notificationService = $this->container->get(NotificationService::class);

        if ($this->isPost()) {
            if ($this->isRecaptchaChecked()) {
                try {
                    $item = $guestBookService->create([
                        'name' => $this->getParam('name'),
                        'email' => $this->getParam('email'),
                        'message' => $this->getParam('message'),
                    ]);

                    // add notification
                    if ($this->parameter('notification_is_enabled', 'yes') === 'yes') {
                        $notificationService->create([
                            'title' => 'Новый отзыв в гостевой книге',
                            'params' => [
                                'guestbook_uuid' => $item->getUuid(),
                            ],
                        ]);
                    }

                    if (
                        (
                            empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest'
                        ) && !empty($_SERVER['HTTP_REFERER'])
                    ) {
                        $this->response = $this->response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(301);
                    }

                    return $this->respondWithJson(['description' => 'Message added']);
                } catch (MissingNameValueException $e) {
                    $this->addError('name', $e->getMessage());
                } catch (MissingEmailValueException|WrongEmailValueException $e) {
                    $this->addError('email', $e->getMessage());
                } catch (MissingMessageValueException $e) {
                    $this->addError('message', $e->getMessage());
                }
            } else {
                $this->addError('grecaptcha', \App\Domain\References\Errors\Common::WRONG_GRECAPTCHA);
            }
        }

        $pagination = $this->parameter('guestbook_pagination', 10);
        $offset = (int) ($this->args['page'] ?? 0);

        // fetch list and hide part of email
        $list = $guestBookService->read([
            'status' => \App\Domain\Types\GuestBookStatusType::STATUS_WORK,
            'limit' => $pagination,
            'offset' => $pagination * $offset,
        ])->map(function ($model) {
            /** @var GuestBook $model */
            $model->setEmail(str_mask_email($model->getEmail()));

            return $model;
        });

        return $this->respond($this->parameter('guestbook_template', 'guestbook.twig'), [
            'messages' => $list,
            'pagination' => [
                'count' => $guestBookService->count(['status' => \App\Domain\Types\GuestBookStatusType::STATUS_WORK]),
                'page' => $pagination,
                'offset' => $offset,
            ],
        ]);
    }
}
