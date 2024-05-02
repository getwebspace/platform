<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Domain\AbstractAction;
use App\Domain\Models\GuestBook;
use App\Domain\Service\GuestBook\Exception\MissingEmailValueException;
use App\Domain\Service\GuestBook\Exception\MissingMessageValueException;
use App\Domain\Service\GuestBook\Exception\MissingNameValueException;
use App\Domain\Service\GuestBook\Exception\WrongEmailValueException;
use App\Domain\Service\GuestBook\Exception\WrongNameValueException;
use App\Domain\Service\GuestBook\GuestBookService;

class GuestBookAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        /** @var GuestBookService $guestBookService */
        $guestBookService = $this->container->get(GuestBookService::class);

        if ($this->isPost()) {
            if ($this->isRecaptchaChecked()) {
                try {
                    $entry = $guestBookService->create([
                        'name' => $this->getParam('name'),
                        'email' => $this->getParam('email'),
                        'message' => $this->getParam('message'),
                    ]);
                    $entry = $this->processEntityFiles($entry);

                    if (
                        (
                            empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest'
                        ) && !empty($_SERVER['HTTP_REFERER'])
                    ) {
                        $this->response = $this->response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(301);
                    }

                    $this->container->get(\App\Application\PubSub::class)->publish('common:guestbook:create', $entry);

                    return $this->respondWithJson(['description' => 'Message added']);
                } catch (MissingNameValueException|WrongNameValueException $e) {
                    $this->addError('name', $e->getMessage());
                } catch (MissingEmailValueException|WrongEmailValueException $e) {
                    $this->addError('email', $e->getMessage());
                } catch (MissingMessageValueException $e) {
                    $this->addError('message', $e->getMessage());
                }
            } else {
                $this->addError('grecaptcha', 'EXCEPTION_WRONG_GRECAPTCHA');
            }
        }

        $pagination = $this->parameter('guestbook_pagination', 10);
        $offset = (int) ($this->args['page'] ?? 0);

        // fetch list and hide part of email
        $list = $guestBookService
            ->read([
                'status' => \App\Domain\Casts\GuestBook\Status::WORK,
                'order' => ['date' => 'desc'],
                'limit' => $pagination,
                'offset' => $pagination * $offset,
            ])
            ->map(function (GuestBook $model) {
                $model->email = str_mask_email($model->email);

                return $model;
            });

        $count = $guestBookService->count(['status' => \App\Domain\Casts\GuestBook\Status::WORK]);

        return $this->respond($this->parameter('guestbook_template', 'guestbook.twig'), [
            'messages' => $list,
            'pagination' => [
                'count' => $count,
                'page' => $pagination,
                'offset' => $offset,
            ],
        ]);
    }
}
