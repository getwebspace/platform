<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use DateTime;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Slim\Http\Request;
use Slim\Http\Response;

class AuthorizationMiddleware extends Middleware
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $userRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $userSessionRepository;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userRepository = $this->entityManager->getRepository(\App\Domain\Entities\User::class);
        $this->userSessionRepository = $this->entityManager->getRepository(\App\Domain\Entities\User\Session::class);
    }

    /**
     * @param Request  $request
     * @param Response $response
     * @param callable $next
     *
     * @throws \Exception
     *
     * @return Response
     */
    public function __invoke(Request $request, Response $response, $next): \Slim\Http\Response
    {
        \RunTracy\Helpers\Profiler\Profiler::start('middleware:authorization');

        $data = [
            'uuid' => $request->getCookieParam('uuid', null),
            'session' => $request->getCookieParam('session', null),
        ];

        if ($data['uuid'] && Uuid::isValid($data['uuid']) && $data['session']) {
            try {
                /** @var \App\Domain\Entities\User\Session $session */
                $session = $this->userSessionRepository->findOneBy(['uuid' => $data['uuid']]);

                if ($session && $data['session'] === $this->session($session)) {
                    $user = $this->userRepository->findOneBy([
                        'uuid' => $session->uuid,
                        'status' => \App\Domain\Types\UserStatusType::STATUS_WORK,
                    ]);

                    if ($user) {
                        $request = $request->withAttribute('user', $user);
                    }
                }
            } catch (\Doctrine\DBAL\Exception\TableNotFoundException $e) {
            }
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('middleware:authorization');

        return $next($request, $response);
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
