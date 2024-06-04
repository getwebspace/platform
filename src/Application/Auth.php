<?php declare(strict_types=1);

namespace App\Application;

use App\Application\Auth\AbstractAuthProvider;
use App\Application\Auth\BasicAuthProvider;
use App\Application\Auth\OpenAuthProvider;
use App\Domain\Exceptions\HttpRedirectException;
use App\Domain\Exceptions\WrongAuthProviderException;
use App\Domain\Models\User;
use App\Domain\Models\UserToken;
use App\Domain\Plugin\AbstractOAuthPlugin;
use App\Domain\Service\User\Exception\TokenNotFoundException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Traits\UseSecurity;
use Psr\Container\ContainerInterface;

class Auth
{
    use UseSecurity;

    protected ContainerInterface $container;

    /** @var AbstractAuthProvider[] $providers */
    protected array $providers = [];

    final public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        // username/email/phone and password
        $this->addProvider('BasicAuthProvider', new BasicAuthProvider($container));

        // search OAuth plugins
        foreach ($this->container->get('plugin')->get() as $plugin) {
            if (is_a($plugin, AbstractOAuthPlugin::class)) {
                $this->addProvider(class_basename($plugin), new OpenAuthProvider($container, $plugin));
            }
        }
    }

    public function addProvider(string $name, AbstractAuthProvider $provider)
    {
        $this->providers[$name] = $provider;
    }

    /**
     * @throws WrongPasswordException
     * @throws UserNotFoundException
     * @throws HttpRedirectException
     * @throws WrongAuthProviderException
     */
    public function login(string $provider, array $credentials, array $params = []): ?array
    {
        if (!isset($this->providers[$provider])) {
            throw new WrongAuthProviderException;
        }

        $default = [
            'redirect' => null,
            'agent' => '',
            'ip' => '',
            'comment' => '',
        ];
        $params = array_merge($default, $params);
        $user = $this->providers[$provider]->login($credentials, $params);

        if ($user) {
            $tokens = $this->getTokenPairs($user, null, $params['agent'], $params['ip'], $params['comment']);
            $this->container->get(\App\Application\PubSub::class)->publish('auth:user:login', $user);

            return [
                'user' => $user,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
            ];
        }

        return null;
    }

    /**
     * @throws WrongAuthProviderException
     */
    public function register(string $provider, array $data): ?array
    {
        if (!isset($this->providers[$provider])) {
            throw new WrongAuthProviderException;
        }

        $user = $this->providers[$provider]->register($data);

        if ($user) {
            $this->container->get(\App\Application\PubSub::class)->publish('common:user:register', $user);

            return [
                'user' => $user,
            ];
        }

        return null;
    }

    /**
     * @throws WrongAuthProviderException
     */
    public function logout(string $provider, string $token): void
    {
        if (!isset($this->providers[$provider])) {
            throw new WrongAuthProviderException;
        }

        $this->providers[$provider]->logout($token);
    }

    /**
     * @throws TokenNotFoundException
     * @throws WrongAuthProviderException
     */
    public function refresh(string $provider, string $token, array $params = []): ?array
    {
        if (!isset($this->providers[$provider])) {
            throw new WrongAuthProviderException;
        }

        $default = [
            'agent' => '',
        ];
        $params = array_merge($default, $params);

        $token = $this->providers[$provider]->refresh($token, $params);

        if ($token) {
            /** @var UserToken $token */
            $user = $token->user;

            $tokens = $this->getTokenPairs($user, $token, $token->agent, $token->ip, $token->comment);

            $this->container->get(\App\Application\PubSub::class)->publish('auth:user:refresh-token', $user);

            return [
                'user' => $user,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'],
            ];
        }

        return null;
    }

    /**
     * @throws WrongAuthProviderException
     */
    public function revoke(string $provider, string $token, ?string $uuid): void
    {
        if (!isset($this->providers[$provider])) {
            throw new WrongAuthProviderException;
        }

        $this->providers[$provider]->revoke($token, $uuid);
    }

    private const MAX_COUNT_ACTIVE_SESSIONS = 5;

    protected function getTokenPairs(User $user, ?UserToken $token, string $agent, string $ip, string $comment): array
    {
        switch ($token) {
            case null:
                // auto remove all active sessions if count of active session more than const
                if ($user->tokens->count() >= $this::MAX_COUNT_ACTIVE_SESSIONS) {
                    $user->tokens()->delete();
                }

                $token = $user->tokens()->create([
                    'unique' => $this->getRefreshToken($user->uuid, $ip, $agent),
                    'agent' => $agent,
                    'ip' => $ip,
                    'comment' => $comment,
                ]);
            default:
                $token->update([
                    'unique' => $this->getRefreshToken($user->uuid, $ip, $agent),
                    'agent' => $agent,
                    'ip' => $ip,
                    'comment' => $comment,
                ]);
        }

        return [
            'access_token' => $this->getAccessToken($user),
            'refresh_token' => $token->unique,
        ];
    }

    protected function getAccessToken(User $user): string
    {
        return $this->encodeJWT('user', $user->uuid, [
            'uuid' => $user->uuid,
            'username' => $user->username,
            'group_uuid' => $user->group_uuid,
            'external_id' => $user->external_id,
            'language' => $user->language,
        ]);
    }

    protected function getRefreshToken(string $uuid, string $ip, string $agent): string
    {
        $payload = implode(';', [
            'uuid:' . $uuid,
            'ip:' . sha1($ip),
            'agent:' . sha1($agent),
            'microtime:' . intval(microtime(true)),
        ]);

        return hash('sha256', $payload);
    }
}
