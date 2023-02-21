<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\AbstractAction;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Service\User\IntegrationService as UserIntegrationService;
use App\Domain\Service\User\SubscriberService as UserSubscriberService;
use App\Domain\Service\User\TokenService as UserTokenService;
use App\Domain\Service\User\UserService;
use Psr\Container\ContainerInterface;

abstract class UserAction extends AbstractAction
{
    protected UserService $userService;

    protected UserGroupService $userGroupService;

    protected UserSubscriberService $userSubscriberService;

    protected UserTokenService $userTokenService;

    protected UserIntegrationService $userIntegrationService;

    /**
     * {@inheritdoc}
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->userService = $container->get(UserService::class);
        $this->userGroupService = $container->get(UserGroupService::class);
        $this->userSubscriberService = $container->get(UserSubscriberService::class);
        $this->userTokenService = $container->get(UserTokenService::class);
        $this->userIntegrationService = $container->get(UserIntegrationService::class);
    }

    protected function getOAuthProviders($only_keys = false)
    {
        $list = json_decode($this->parameter('user_oauth', '[]'), true);

        return !json_last_error() ? ($only_keys ? array_keys($list) : $list) : [];
    }

    protected function getOAuthService(): \SocialConnect\Auth\Service
    {
        return new \SocialConnect\Auth\Service(
            new \SocialConnect\Common\HttpStack(
                new \SocialConnect\HttpClient\Curl([
                    CURLOPT_USERAGENT => 'WebSpaceEngine\Client ' . ($_ENV['COMMIT_SHA'] ?? 'specific'),
                ]),
                new \SocialConnect\HttpClient\RequestFactory(),
                new \SocialConnect\HttpClient\StreamFactory()
            ),
            new \SocialConnect\Provider\Session\Session(),
            [
                'redirectUri' => $this->parameter('common_homepage') . '/user/oauth/${provider}',
                'provider' => $this->getOAuthProviders(),
            ]
        );
    }
}
