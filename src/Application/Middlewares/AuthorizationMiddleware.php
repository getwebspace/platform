<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\Repository\UserRepository;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\UserService;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;
use Slim\Http\Request;
use Slim\Http\Response;

class AuthorizationMiddleware extends AbstractMiddleware
{
    /**
     * @var UserRepository
     */
    protected $users;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->users = $this->entityManager->getRepository(\App\Domain\Entities\User::class);
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
                $userService = UserService::getFromContainer($this->container);
                $user = $userService->read(['uuid' => $data['uuid']]);

                if ($user) {
                    $hash = sha1(
                        'salt:' . ($this->container->get('secret')['salt'] ?? '') . ';' .
                        'uuid:' . $user->getUuid()->toString() . ';' .
                        'ip:' . md5($user->getSession()->getIp()) . ';' .
                        'agent:' . md5($user->getSession()->getAgent()) . ';' .
                        'date:' . $user->getSession()->getDate()->getTimestamp()
                    );

                    if ($data['session'] === $hash) {
                        $request = $request->withAttribute('user', $user);
                    }
                }
            } catch (UserNotFoundException $e) {
                // clear cookie
                setcookie('uuid', '-1', time() - 10, '/');
                setcookie('session', '-1', time() - 10, '/');
            }
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('middleware:authorization');

        return $next($request, $response);
    }
}
