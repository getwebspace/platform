<?php declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\Exceptions\HttpRedirectException;
use App\Domain\Models\User;
use App\Domain\Models\UserToken;
use App\Domain\Plugin\AbstractOAuthPlugin;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\EmailBannedException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\PhoneAlreadyExistsException;
use App\Domain\Service\User\Exception\TokenNotFoundException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\UserNotFoundException;
use App\Domain\Service\User\Exception\WrongEmailValueException;
use App\Domain\Service\User\Exception\WrongPasswordException;
use App\Domain\Service\User\Exception\WrongPhoneValueException;
use Psr\Container\ContainerInterface;

class OpenAuthProvider extends AbstractAuthProvider
{
    protected AbstractOAuthPlugin $plugin;

    public function __construct(ContainerInterface $container, AbstractOAuthPlugin $plugin)
    {
        parent::__construct($container);

        $this->plugin = $plugin;
    }

    protected function createState(): string
    {
        $_SESSION['auth_state'] = $state = bin2hex(random_bytes(8));
        $_SESSION['auth_provider'] = class_basename($this->plugin);

        return $state;
    }

    protected function checkState(string $state): bool
    {
        return isset($_SESSION['auth_state']) && $_SESSION['auth_state'] === $state;
    }

    protected function revokeState(): void
    {
        unset($_SESSION['auth_state'], $_SESSION['auth_provider']);
    }

    protected function getRedirectUrl($path): string
    {
        return rtrim($this->parameter('common_homepage', ''), '/') . $path;
    }

    /**
     * @throws WrongEmailValueException
     * @throws WrongPhoneValueException
     * @throws WrongPasswordException
     * @throws EmailBannedException
     * @throws EmailAlreadyExistsException
     * @throws PhoneAlreadyExistsException
     * @throws UsernameAlreadyExistsException
     * @throws MissingUniqueValueException
     * @throws \Exception
     */
    public function login(array $credentials, array $params): ?User
    {
        $default = [
            'code' => null,
            'state' => null,
        ];
        $credentials = array_merge($default, $credentials);
        $redirect = $this->getRedirectUrl($params['redirect']);

        if ($credentials['state'] && $this->checkState($credentials['state'])) {
            $this->revokeState();
            $token = $this->plugin->getToken($credentials, $redirect);

            if ($token) {
                $provider = class_basename($this->plugin);
                $info = $this->plugin->getInfo(['access_token' => $token]);

                try {
                    $user = $this->userService->read([
                        'provider' => $provider,
                        'unique' => $info['unique'],
                    ]);
                } catch (UserNotFoundException $e) {
                    $data = [
                        'email' => $info['email'],
                        'firstname' => $info['firstname'],
                        'lastname' => $info['lastname'],
                    ];

                    $user = $this->userService->create($data);
                    $user->integrations()->updateOrCreate([
                        'provider' => $provider,
                        'unique' => $info['unique'],
                    ]);
                }

                return $user;
            }
        } else {
            $state = $this->createState();

            if (($url = $this->plugin->getAuthUrl($redirect, $state)) !== null) {
                throw new HttpRedirectException($url);
            }
        }

        throw new \Exception('Unable to get authentication URL.');
    }

    public function register(array $data): ?User
    {
        return null;
    }

    public function logout(string $token): void
    {
        // nothing
    }

    /** @throws TokenNotFoundException */
    public function refresh(string $token, array $params): ?UserToken
    {
        return null;
    }

    public function revoke(string $token, ?string $uuid): void
    {
        // nothing
    }
}
