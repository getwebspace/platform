<?php

namespace App\Application\Actions\Common;

use App\Application\Actions\Action;
use DateTime;
use Psr\Container\ContainerInterface;

class GuestBookAction extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $gbookRepository;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->gbookRepository = $this->entityManager->getRepository(\App\Domain\Entities\GuestBook::class);
    }

    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'name' => $this->request->getParam('name'),
                'email' => $this->request->getParam('email'),
                'message' => $this->request->getParam('message'),
            ];

            $check = \App\Domain\Filters\GuestBook::check($data);

            if ($this->isRecaptchaChecked()) {
                if ($check === true) {
                    $model = new \App\Domain\Entities\GuestBook($data);
                    $model->status = \App\Domain\Types\GuestBookStatusType::STATUS_MODERATE;
                    $this->entityManager->persist($model);

                    // create notify
                    $notify = new \App\Domain\Entities\Notification([
                        'title' => 'Добавлен отзыв',
                        'message' => 'Был добавлен отзыв в гостевой книге',
                        'date' => new DateTime(),
                    ]);
                    $this->entityManager->persist($notify);

                    // send push stream
                    $this->container->get('pushstream')->send([
                        'group' => \App\Domain\Types\UserLevelType::LEVEL_ADMIN,
                        'content' => $notify,
                    ]);

                    $this->entityManager->flush();

                    if (
                        (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') && !empty($_SERVER['HTTP_REFERER'])
                    ) {
                        $this->response = $this->response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(301);
                    }

                    return $this->respondWithData(['description' => 'Message added']);
                } else {
                    $this->addErrorFromCheck($check);
                }
            } else {
                $this->addError('grecaptcha', \App\Domain\References\Errors\Common::WRONG_GRECAPTCHA);
            }
        }

        // get list of comments and obfuscate email address
        $list = collect(
            $this->gbookRepository->findBy(
                ['status' => \App\Domain\Types\GuestBookStatusType::STATUS_WORK],
                [],
                $this->getParameter('guestbook_pagination', 10),
                (int)($this->args['page'] ?? 0)
            )
        )->map(
            function ($el) {
                if ($el->email) {
                    $em = explode('@', $el->email);
                    $name = implode(array_slice($em, 0, count($em) - 1), '@');
                    $len = floor(strlen($name) / 2);

                    $el->email = mb_substr($name, 0, $len) . str_repeat('*', $len) . '@' . end($em);
                }

                return $el;
            }
        );

        return $this->respondRender($this->getParameter('guestbook_template', 'guestbook.twig'), ['messages' => $list]);
    }
}
