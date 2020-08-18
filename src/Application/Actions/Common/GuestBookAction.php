<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Domain\AbstractAction;
use App\Domain\Entities\GuestBook;
use App\Domain\Service\GuestBook\Exception\MissingEmailValueException;
use App\Domain\Service\GuestBook\Exception\MissingMessageValueException;
use App\Domain\Service\GuestBook\Exception\MissingNameValueException;
use App\Domain\Service\GuestBook\GuestBookService;

class GuestBookAction extends AbstractAction
{
    protected function action(): \Slim\Http\Response
    {
        $guestBookService = GuestBookService::getWithContainer($this->container);

        if ($this->request->isPost()) {
            if ($this->isRecaptchaChecked()) {
                try {
                    $guestBookService->create([
                        'name' => $this->request->getParam('name'),
                        'email' => $this->request->getParam('email'),
                        'message' => $this->request->getParam('message'),
                    ]);

                    // todo add admin notify

                    if (
                        (
                            empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest'
                        ) && !empty($_SERVER['HTTP_REFERER'])
                    ) {
                        $this->response = $this->response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(301);
                    }

                    return $this->respondWithJson(['description' => 'Message added']);
                } catch (MissingEmailValueException $e) {
                    $this->addError('name', $e->getMessage());
                } catch (MissingMessageValueException $e) {
                    $this->addError('email', $e->getMessage());
                } catch (MissingNameValueException $e) {
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
            /** @var $model GuestBook */
            $em = explode('@', $model->getEmail());
            $name = implode('@', array_slice($em, 0, count($em) - 1));
            $len = (int) floor(mb_strlen($name) / 2);

            return array_merge($model->toArray(), ['email' => mb_substr($name, 0, $len) . str_repeat('*', $len) . '@' . end($em)]);
        });

        return $this->respondWithTemplate($this->parameter('guestbook_template', 'guestbook.twig'), [
            'messages' => $list,
            'pagination' => [
                'count' => $guestBookService->count(['status' => \App\Domain\Types\GuestBookStatusType::STATUS_WORK]),
                'page' => $pagination,
                'offset' => $offset,
            ],
        ]);
    }
}
