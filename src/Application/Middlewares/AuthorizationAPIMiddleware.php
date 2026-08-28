<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Domain\AbstractMiddleware;
use App\Domain\Casts\ApiKey\Status as ApiKeyStatus;
use App\Domain\Models\ApiKey;
use App\Domain\Models\User;
use App\Domain\Service\ApiKey\ApiKeyService;
use App\Domain\Service\ApiKey\Exception\ApiKeyNotFoundException;
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

                    // allow access if key exist
                    // no break
                case 'key':
                    if (($apikey = $this->checkAPIKey($request)) !== false) {
                        $access = true;
                        $request = $request->withAttribute('apikey', $apikey);
                    }

                    // allow access if user exist
                    // no break
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

    /**
     * A key is now a signed, scoped token rather than a flat shared secret -
     * see App\Domain\Service\ApiKey\ApiKeyService. The lookup still happens
     * on every request (rather than trusting the token's own claims) so that
     * revoking a key or narrowing its scopes takes effect immediately
     */
    protected function checkAPIKey(Request $request): ApiKey|false
    {
        $token = $request->getQueryParams()['apikey'] ?? null;

        if (blank($token)) {
            $token = $request->getHeaderLine('key');
        }
        if (blank($token)) {
            $token = $request->getHeaderLine('apikey');
        }
        if (!is_string($token) || blank($token)) {
            return false;
        }

        try {
            $payload = $this->decodeJWT($token);
        } catch (\DomainException|ExpiredException|\RuntimeException|SignatureInvalidException|\UnexpectedValueException $e) {
            // the last one is UseSecurity's "Not exist PEM keys files" - on a
            // fresh install with no signing key yet, no token can be genuine
            return false;
        }

        if (($payload['sub'] ?? '') !== 'api-key' || blank($payload['uuid'] ?? null)) {
            return false;
        }

        try {
            /** @var ApiKeyService $apiKeyService */
            $apiKeyService = $this->container->get(ApiKeyService::class);

            return $apiKeyService->read([
                'uuid' => $payload['uuid'],
                'status' => ApiKeyStatus::WORK,
            ]);
        } catch (ApiKeyNotFoundException $e) {
            return false;
        }
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
            } catch (\DomainException|ExpiredException|\RuntimeException|SignatureInvalidException|\UnexpectedValueException $e) {
                // same reasoning as checkAPIKey() above - an unverifiable
                // token, for whatever reason, is simply not authenticated
            }
        }

        return false;
    }
}
