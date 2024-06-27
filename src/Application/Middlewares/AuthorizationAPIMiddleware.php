<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\Models\User;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\UserService;
use App\Domain\Traits\UseSecurity;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

class AuthorizationAPIMiddleware extends AbstractMiddleware
{
    use UseSecurity;

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

                // allow access if key exist
                case 'key':
                    if (($apikey = $this->checkAPIKey($request)) !== false) {
                        $access = true;
                        $request = $request->withAttribute('apikey', $apikey);
                    }
                // no break

                // allow access if user exist
                case 'user':
                    if (($user = $this->findUser($request)) !== false) {
                        $access = true;
                        $request = $request->withAttribute('user', $user);
                    }
            }

            if ($access) {
                return $handler->handle($request);
            }

            $response = new Response();
            $response->getBody()->write('Client must authenticate itself');

            return $response
                ->withHeader('Content-Type', 'text/plain; charset=utf-8')
                ->withStatus(401);
        }

        return (new Response())->withStatus(200);
    }

    protected function checkAPIKey(Request $request): bool
    {
        $key = $request->getQueryParams()['apikey'] ?? null;

        if (blank($key)) {
            $key = $request->getHeaderLine('key') ?? null;
        }
        if (blank($key)) {
            $key = $request->getHeaderLine('apikey') ?? null;
        }

        return $key && str_contains($this->parameter('entity_keys', ''), $key);
    }

    protected function findUser(Request $request): false|User
    {
        $access_token = $request->getQueryParams()['access_token'] ?? null;

        if (blank($access_token)) {
            $access_token = $request->getHeaderLine('access_token') ?? null;
        }
        if (blank($access_token)) {
            $access_token = $request->getCookieParams()['access_token'] ?? null;
        }

        if ($access_token) {
            try {
                $uuid = $this->decodeJWT($access_token)['uuid'] ?? null;

                if ($uuid && \Ramsey\Uuid\Uuid::isValid($uuid)) {
                    try {
                        /** @var UserService $userService */
                        $userService = $this->container->get(UserService::class);

                        return $userService->read([
                            'uuid' => $uuid,
                            'status' => \App\Domain\Casts\User\Status::WORK,
                        ]);
                    } catch (UserNotFoundException $e) {
                        // nothing
                    }
                }
            } catch (ExpiredException|SignatureInvalidException $e) {
                // nothing
            }
        }

        return false;
    }
}
