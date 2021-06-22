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
     * @param callable $next
     *
     * @throws \Exception
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
                $userService = UserService::getWithContainer($this->container);
                $user = $userService->read([
                    'uuid' => $data['uuid'],
                    'status' => \App\Domain\Types\UserStatusType::STATUS_WORK,
                ]);

                if ($user && $user->getSession()) {
                    $hash = $user->getSession()->getHash();

                    if ($data['session'] === $hash) {
                        $request = $request->withAttribute('user', $user);
                    }
                } else {
                    throw new \RuntimeException();
                }
            } catch (\RuntimeException | UserNotFoundException $e) {
                // clear cookie
                setcookie('uuid', '-1', time(), '/');
                setcookie('session', '-1', time(), '/');
            }
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('middleware:authorization');

        return $next($request, $response);
    }
}
