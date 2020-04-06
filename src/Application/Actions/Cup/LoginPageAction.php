<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Application\Actions\Action;
use DateTime;
use Exception;
use Psr\Container\ContainerInterface;

class LoginPageAction extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $userRepository;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userRepository = $this->entityManager->getRepository(\App\Domain\Entities\User::class);
    }

    protected function action(): \Slim\Http\Response
    {
        $identifier = $this->getParameter('user_login_type', 'username');

        if ($this->request->isPost()) {
            $data = [
                'email' => $this->request->getParam('email'),
                'username' => $this->request->getParam('username'),
                'password' => $this->request->getParam('password'),
                'agent' => $this->request->getServerParam('HTTP_USER_AGENT'),
                'ip' => $this->request->getServerParam('REMOTE_ADDR'),

                'redirect' => $this->request->getParam('redirect'),
            ];

            $check = \App\Domain\Filters\User::login($data);

            if ($this->isRecaptchaChecked()) {
                if ($check === true) {
                    /** @var \App\Domain\Entities\User $user */
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

                                setcookie('uuid', $user->uuid, time() + \App\Domain\References\Date::YEAR, '/');
                                setcookie('session', $hash, time() + \App\Domain\References\Date::YEAR, '/');

                                return $this->response->withAddedHeader('Location', $data['redirect'] ? $data['redirect'] : '/cup')->withStatus(301);
                            } catch (Exception $e) {
                                $this->logger->warning('/login failure', $data);
                            }
                        } else {
                            $this->addError('password', \App\Domain\References\Errors\User::WRONG_PASSWORD);
                        }
                    } else {
                        $this->addError($identifier, \App\Domain\References\Errors\User::NOT_FOUND);
                    }
                } else {
                    $this->addErrorFromCheck($check);
                }
            } else {
                $this->addError('grecaptcha', \App\Domain\References\Errors\Common::WRONG_GRECAPTCHA);
            }
        }

        return $this->respondRender('cup/auth/login.twig', ['identifier' => $identifier]);
    }

    /**
     * Возвращает ключ сессии
     *
     * @param \App\Domain\Entities\User\Session $model
     *
     * @throws \Exception
     *
     * @return string
     */
    protected function session(\App\Domain\Entities\User\Session $model)
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
