<?php

namespace Application\Actions\Cup;

use Application\Actions\Action;
use DateTime;
use Exception;
use Psr\Container\ContainerInterface;

class LoginAction extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $userRepository;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userRepository = $this->entityManager->getRepository(\Domain\Entities\User::class);
    }

    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'email' => $this->request->getParam('email'),
                'username' => $this->request->getParam('username'),
                'password' => $this->request->getParam('password'),
                'agent' => $this->request->getServerParam('HTTP_USER_AGENT'),
                'ip' => $this->request->getServerParam('REMOTE_ADDR'),

                'redirect' => $this->request->getParam('redirect'),
            ];

            $check = \Domain\Filters\User::login($data);

            if ($check === true) {
                $identifier = $this->getParameter('user_login_type', 'username');

                /** @var \Domain\Entities\User $user */
                $user = $this->userRepository->findOneBy([$identifier => $data[$identifier]]);

                if ($user) {
                    if (crypta_hash_check($data['password'], $user->password)) {
                        try {
                            $session = $user->session->replace([
                                'uuid' => $user->uuid,
                                'agent' => $data['agent'],
                                'ip' => $data['ip'],
                                'date' => new DateTime(),
                            ]);
                            $this->entityManager->persist($user);
                            $this->entityManager->persist($session);
                            $this->entityManager->flush();

                            $hash = $this->session($session);

                            setcookie('uuid', $user->uuid, time() + \Domain\References\Date::YEAR, '/');
                            setcookie('session', $hash, time() + \Domain\References\Date::YEAR, '/');

                            return $this->response->withAddedHeader('Location', $data['redirect'] ? $data['redirect'] : '/cup');
                        } catch (Exception $e) {
                            $this->logger->warning('/login failure', $data);
                        }
                    } else {
                        \AEngine\Support\Form::$globalError['password'] = \Domain\References\Errors\User::WRONG_PASSWORD;
                    }
                } else {
                    \AEngine\Support\Form::$globalError[$identifier] = \Domain\References\Errors\User::NOT_FOUND;
                }
            } else {
                \AEngine\Support\Form::$globalError = $check;
            }
        }

        return $this->respondRender('cup/auth/login.twig');
    }

    /**
     * Возвращает ключ сессии
     *
     * @param \Domain\Entities\User\Session $model
     *
     * @return string
     * @throws \Exception
     */
    protected function session(\Domain\Entities\User\Session $model)
    {
        if (!$model->isEmpty()) {
            $default = [
                'uuid' => null,
                'ip' => null,
                'agent' => null,
                'date' => new DateTime(),
            ];
            $data = array_merge($default, $model->toArray());

            return sha1(
                'salt:' . ($this->container->get('secret')['salt'] ?? '') . ';' .
                'uuid:' . $data['uuid'] . ';' .
                'ip:' . md5($data['ip']) . ';' .
                'agent:' . md5($data['agent']) . ';' .
                'date:' . $data['date']->getTimestamp()
            );
        }

        return null;
    }
}
