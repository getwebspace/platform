<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\Entities\User;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\UserService;
use App\Domain\Traits\SecurityTrait;
use Firebase\JWT\ExpiredException;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class AuthorizationAPIMiddleware extends AbstractMiddleware
{
    use SecurityTrait;

    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        if ($request->getMethod() !== 'OPTIONS') {
            $access = false;

            switch ($this->parameter('entity_access', 'user')) {
                // allow access for all
                case 'all':
                    $access = true;
                // no break

                // allow access if user exist
                case 'user':
                    if (($user = $this->findUser($request)) !== false) {
                        $access = true;
                        $request = $request->withAttribute('user', $user);
                    }
                // no break

                // allow access if key exist
                case 'key':
                    if (($apikey = $this->checkAPIKey($request)) !== false) {
                        $access = true;
                        $request = $request->withAttribute('apikey', $apikey);
                    }
            }

            if ($access) {
                return $handler->handle($request);
            }

            return (new Response())
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withStatus(401);
        }

        return (new Response())->withStatus(200);
    }

    protected function checkAPIKey(Request $request): bool
    {
        $key = $request->getQueryParams()['key'] ?? null;

        if ($key === null) {
            $key = $request->getHeaderLine('key');
        }
        if ($key === null) {
            $key = $request->getHeaderLine('apikey');
        }

        return $key && str_contains($this->parameter('entity_keys', ''), $key);
    }

    protected function findUser(Request $request): false|User
    {
        $access_token = $request->getCookieParams()['access_token'] ?? null;

        if ($access_token) {
            try {
                $uuid = $this->decodeJWT($access_token)['uuid'] ?? null;

                if ($uuid && \Ramsey\Uuid\Uuid::isValid($uuid)) {
                    try {
                        /** @var UserService $userService */
                        $userService = $this->container->get(UserService::class);

                        return $userService->read([
                            'uuid' => $uuid,
                            'status' => \App\Domain\Types\UserStatusType::STATUS_WORK,
                        ]);
                    } catch (UserNotFoundException $e) {
                        // nothing
                    }
                }
            } catch (ExpiredException $e) {
                // nothing
            }
        }

        return false;
    }
}
